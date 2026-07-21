<?php

namespace App\Services;

use App\Models\PosSale;
use App\Models\Product;
use App\Models\ProductRecipeItem;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\BranchInventoryManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminSaleEditorService
{
    public function __construct(private CashierShiftSummaryService $shiftSummary) {}

    public function update(PosSale $sale, int $branchId, User $actor, array $data): PosSale
    {
        return DB::transaction(function () use ($sale, $branchId, $actor, $data): PosSale {
            $sale = PosSale::query()->with(['items.product', 'items.productVariant', 'rawMaterialUsages', 'shift'])
                ->whereKey($sale->id)->where('branch_id', $branchId)->lockForUpdate()->firstOrFail();
            abort_if($sale->voided_at, 422, 'Transaksi yang sudah di-void tidak dapat diedit.');

            $this->restorePreviousState($sale, $branchId, $actor, $data['reason']);
            $sale->items()->delete();
            $sale->rawMaterialUsages()->delete();

            $lines = [];
            $subtotal = 0;
            foreach ($data['items'] as $line) {
                $sellable = $this->lockSellable($line['id'], $branchId);
                $quantity = (int) $line['quantity'];
                if ($sellable['stock_mode'] === 'inventory' && (int) $sellable['inventory']->stock < $quantity) {
                    abort(422, 'Stok '.$sellable['name'].' tidak cukup.');
                }
                if ($sellable['stock_mode'] !== 'inventory' && ! ProductRecipeItem::query()->where('product_id', $sellable['product_id'])->exists()) {
                    abort(422, 'Resep '.$sellable['name'].' belum diatur.');
                }
                $lineTotal = $sellable['price'] * $quantity;
                $subtotal += $lineTotal;
                $lines[] = compact('sellable', 'quantity', 'lineTotal');
            }

            $isFree = $data['payment_method'] === 'free';
            $discount = $isFree ? $subtotal : min((int) ($data['discount'] ?? 0), $subtotal);
            $total = $subtotal - $discount;
            $paid = $data['payment_method'] === 'cash' ? (int) ($data['paid_amount'] ?? 0) : $total;
            if ($data['payment_method'] === 'cash' && $paid < $total) {
                abort(422, 'Uang diterima kurang dari total transaksi.');
            }

            $sale->update([
                'payment_method' => $data['payment_method'],
                'complimentary_category' => $isFree ? ($data['complimentary_category'] ?? null) : null,
                'complimentary_recipient_name' => $isFree ? ($data['complimentary_recipient_name'] ?? null) : null,
                'buyer_nationality' => $data['buyer_nationality'] ?? null,
                'subtotal' => $subtotal, 'discount' => $discount, 'total' => $total,
                'paid_amount' => $paid, 'change_amount' => max(0, $paid - $total),
            ]);

            foreach ($lines as $line) {
                $sellable = $line['sellable'];
                $inventory = $sellable['inventory'];
                $before = (int) $inventory->stock;
                if ($sellable['stock_mode'] === 'inventory') {
                    $inventory->update(['stock' => $before - $line['quantity']]);
                    $sellable['model']->update(['stock' => $inventory->stock]);
                    StockMovement::query()->create(['branch_id' => $branchId, 'created_by_user_id' => $actor->id, 'product_id' => $sellable['product_id'], 'product_variant_id' => $sellable['variant_id'], 'type' => 'out', 'quantity' => $line['quantity'], 'stock_before' => $before, 'stock_after' => $before - $line['quantity'], 'reference' => $sale->invoice_number, 'note' => 'Koreksi transaksi: '.$data['reason'], 'occurred_at' => now()]);
                }
                $sale->items()->create(['product_id' => $sellable['product_id'], 'product_variant_id' => $sellable['variant_id'], 'item_type' => $sellable['type'], 'name' => $sellable['name'], 'sku' => $sellable['sku'], 'quantity' => $line['quantity'], 'unit_price' => $sellable['price'], 'line_total' => $line['lineTotal']]);
                $this->consumeRawMaterials($sale, $sellable['product_id'], $line['quantity']);
            }

            $this->shiftSummary->refresh($sale->shift);

            return $sale->refresh()->load('items');
        });
    }

    private function restorePreviousState(PosSale $sale, int $branchId, User $actor, string $reason): void
    {
        foreach ($sale->items as $item) {
            if ($item->product?->stock_mode !== 'inventory') {
                continue;
            }

            $model = $item->product_variant_id ? $item->productVariant : $item->product;
            if (! $model) {
                continue;
            }
            $inventory = $item->product_variant_id ? app(BranchInventoryManager::class)->forVariant($branchId, $model, true) : app(BranchInventoryManager::class)->forProduct($branchId, $model, true);
            $before = (int) $inventory->stock;
            $inventory->update(['stock' => $before + $item->quantity]);
            $model->update(['stock' => $inventory->stock]);
            StockMovement::query()->create(['branch_id' => $branchId, 'created_by_user_id' => $actor->id, 'product_id' => $item->product_id, 'product_variant_id' => $item->product_variant_id, 'type' => 'in', 'quantity' => $item->quantity, 'stock_before' => $before, 'stock_after' => $before + $item->quantity, 'reference' => $sale->invoice_number, 'note' => 'Pembalikan koreksi: '.$reason, 'occurred_at' => now()]);
        }
        foreach ($sale->rawMaterialUsages as $usage) {
            RawMaterial::query()->whereKey($usage->raw_material_id)->lockForUpdate()->first()?->increment('stock', $usage->quantity);
        }
    }

    private function lockSellable(string $id, int $branchId): array
    {
        if (Str::startsWith($id, 'product-')) {
            $product = Product::query()->where('sku', Str::after($id, 'product-'))->lockForUpdate()->firstOrFail();

            return ['model' => $product, 'inventory' => app(BranchInventoryManager::class)->forProduct($branchId, $product, true), 'stock_mode' => $product->stock_mode, 'type' => 'product', 'product_id' => $product->id, 'variant_id' => null, 'name' => $product->name, 'sku' => $product->sku, 'price' => (int) $product->sell_price];
        }
        if (Str::startsWith($id, 'variant-')) {
            $variant = ProductVariant::query()->with('product')->whereKey((int) Str::after($id, 'variant-'))->lockForUpdate()->firstOrFail();

            return ['model' => $variant, 'inventory' => app(BranchInventoryManager::class)->forVariant($branchId, $variant, true), 'stock_mode' => $variant->product->stock_mode, 'type' => 'variant', 'product_id' => $variant->product_id, 'variant_id' => $variant->id, 'name' => $variant->product->name.' · '.$variant->name, 'sku' => $variant->sku ?? $variant->product->sku.'-'.$variant->id, 'price' => (int) $variant->product->sell_price + (int) $variant->sell_price];
        }
        abort(422, 'Item transaksi tidak valid.');
    }

    private function consumeRawMaterials(PosSale $sale, int $productId, int $quantity): void
    {
        ProductRecipeItem::query()->where('product_id', $productId)->whereNotNull('raw_material_id')->get()->each(function (ProductRecipeItem $item) use ($sale, $quantity): void {
            $material = RawMaterial::query()->whereKey($item->raw_material_id)->lockForUpdate()->first();
            if (! $material) {
                return;
            }
            $used = (float) $item->quantity * $quantity;
            $material->decrement('stock', $used);
            $sale->rawMaterialUsages()->create(['raw_material_id' => $material->id, 'quantity' => $used, 'unit' => $item->unit, 'is_legacy_fallback' => false]);
        });
    }
}
