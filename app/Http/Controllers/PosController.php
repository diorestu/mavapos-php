<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRecipeItem;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(): View
    {
        $products = Product::query()
            ->with(['category', 'variants'])
            ->orderBy('name')
            ->get();
        $openShift = CashierShift::query()
            ->with('user')
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->first();
        $activeShift = $openShift?->user_id === auth()->id() ? $openShift : null;
        $blockingShift = $openShift && $openShift->user_id !== auth()->id() ? $openShift : null;

        return view('pages.pos.index', [
            'title' => 'Kasir',
            'activeShift' => $activeShift ? $this->shiftPayload($activeShift) : null,
            'blockingShift' => $blockingShift ? $this->shiftPayload($blockingShift) : null,
            'categories' => ProductCategory::query()
                ->where('status', 'aktif')
                ->orderBy('name')
                ->get()
                ->map(fn (ProductCategory $category): array => [
                    'code' => $category->code,
                    'name' => $category->name,
                ])
                ->values(),
            'items' => $products
                ->flatMap(function (Product $product): array {
                    $base = [$this->productPayload($product)];

                    $variants = $product->variants
                        ->where('is_active', true)
                        ->map(fn (ProductVariant $variant): array => $this->variantPayload($product, $variant))
                        ->values()
                        ->all();

                    return array_merge($base, $variants);
                })
                ->values(),
        ]);
    }

    public function startShift(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'opening_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $shift = DB::transaction(function () use ($validated): CashierShift {
            $openShift = CashierShift::query()
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->latest('opened_at')
                ->first();

            if ($openShift && $openShift->user_id !== auth()->id()) {
                abort(409, 'Kasir '.$openShift->user?->name.' masih aktif. Tutup kasir tersebut sebelum memulai shift baru.');
            }

            if ($openShift) {
                return $openShift->load('user');
            }

            return CashierShift::query()->create([
                'user_id' => auth()->id(),
                'opened_at' => now(),
                'opening_note' => $validated['opening_note'] ?? null,
            ])->load('user');
        });

        return response()->json([
            'message' => 'Shift kasir dimulai.',
            'shift' => $this->shiftPayload($shift),
        ]);
    }

    public function closeShift(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'closing_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $shift = DB::transaction(function () use ($validated): CashierShift {
            $shift = CashierShift::query()
                ->where('user_id', auth()->id())
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            if (! $shift) {
                abort(422, 'Tidak ada shift kasir aktif untuk ditutup.');
            }

            $totals = PosSale::query()
                ->where('cashier_shift_id', $shift->id)
                ->selectRaw('COUNT(*) as sales_count')
                ->selectRaw('COALESCE(SUM(subtotal), 0) as gross_sales')
                ->selectRaw('COALESCE(SUM(discount), 0) as discount_total')
                ->selectRaw('COALESCE(SUM(total), 0) as net_sales')
                ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END), 0) as cash_total")
                ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'qris' THEN total ELSE 0 END), 0) as qris_total")
                ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total ELSE 0 END), 0) as card_total")
                ->first();

            $shift->update([
                'closed_at' => now(),
                'sales_count' => (int) $totals->sales_count,
                'gross_sales' => (int) $totals->gross_sales,
                'discount_total' => (int) $totals->discount_total,
                'net_sales' => (int) $totals->net_sales,
                'cash_total' => (int) $totals->cash_total,
                'qris_total' => (int) $totals->qris_total,
                'card_total' => (int) $totals->card_total,
                'closing_note' => $validated['closing_note'] ?? null,
            ]);

            return $shift->load('user');
        });

        return response()->json([
            'message' => 'Shift kasir ditutup.',
            'shift' => $this->shiftPayload($shift),
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'in:cash,qris,card'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'paid_amount' => ['nullable', 'integer', 'min:0'],
        ]);

        $sale = DB::transaction(function () use ($validated): PosSale {
            $shift = CashierShift::query()
                ->where('user_id', auth()->id())
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            if (! $shift) {
                abort(422, 'Mulai shift kasir sebelum menyelesaikan transaksi.');
            }

            $saleItems = [];
            $subtotal = 0;

            foreach ($validated['items'] as $line) {
                $quantity = (int) $line['quantity'];
                $sellable = $this->lockSellable($line['id']);
                $availableStock = (int) $sellable['model']->stock;

                if ($availableStock < $quantity) {
                    abort(422, 'Stok '.$sellable['name'].' tidak cukup.');
                }

                $lineTotal = $sellable['price'] * $quantity;
                $subtotal += $lineTotal;

                $saleItems[] = [
                    'sellable' => $sellable,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ];
            }

            $discount = min((int) ($validated['discount'] ?? 0), $subtotal);
            $total = $subtotal - $discount;
            $paidAmount = $validated['payment_method'] === 'cash'
                ? (int) ($validated['paid_amount'] ?? 0)
                : $total;

            if ($validated['payment_method'] === 'cash' && $paidAmount < $total) {
                abort(422, 'Uang diterima kurang dari total transaksi.');
            }

            $sale = PosSale::query()->create([
                'cashier_shift_id' => $shift->id,
                'user_id' => auth()->id(),
                'invoice_number' => $this->nextInvoiceNumber(),
                'payment_method' => $validated['payment_method'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'paid_amount' => $paidAmount,
                'change_amount' => max(0, $paidAmount - $total),
                'sold_at' => now(),
            ]);

            foreach ($saleItems as $line) {
                $sellable = $line['sellable'];
                $quantity = $line['quantity'];
                $model = $sellable['model'];
                $stockBefore = (int) $model->stock;

                $model->update(['stock' => $stockBefore - $quantity]);

                $sale->items()->create([
                    'product_id' => $sellable['product_id'],
                    'product_variant_id' => $sellable['variant_id'],
                    'item_type' => $sellable['type'],
                    'name' => $sellable['name'],
                    'sku' => $sellable['sku'],
                    'quantity' => $quantity,
                    'unit_price' => $sellable['price'],
                    'line_total' => $line['line_total'],
                ]);

                if ($sellable['type'] === 'product') {
                    StockMovement::query()->create([
                        'product_id' => $sellable['product_id'],
                        'type' => 'out',
                        'quantity' => $quantity,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockBefore - $quantity,
                        'reference' => $sale->invoice_number,
                        'note' => 'Penjualan POS oleh '.auth()->user()->name,
                        'occurred_at' => $sale->sold_at,
                    ]);
                }

                $this->consumeRawMaterials($sellable['product_id'], $quantity);
            }

            $this->refreshShiftTotals($shift);

            return $sale->load('items', 'shift.user');
        });

        return response()->json([
            'message' => 'Transaksi '.$sale->invoice_number.' berhasil diselesaikan.',
            'sale' => [
                'invoice_number' => $sale->invoice_number,
                'cashier' => $sale->shift?->user?->name,
                'sold_at' => $sale->sold_at?->format('d M Y H:i'),
                'payment_method' => $sale->payment_method,
                'subtotal' => $sale->subtotal,
                'discount' => $sale->discount,
                'total' => $sale->total,
                'paid_amount' => $sale->paid_amount,
                'change_amount' => $sale->change_amount,
                'items' => $sale->items->map(fn ($item): array => [
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ])->values(),
            ],
            'shift' => $this->shiftPayload($sale->shift),
            'items' => $this->itemsPayload(),
        ]);
    }

    private function productPayload(Product $product): array
    {
        return [
            'id' => 'product-'.$product->sku,
            'sourceType' => 'product',
            'sourceId' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode ?? '',
            'category' => $product->category?->code ?? 'umum',
            'categoryName' => $product->category?->name ?? 'Umum',
            'price' => $product->sell_price,
            'stock' => $product->stock,
            'type' => 'Produk',
            'unit' => 'pcs',
            'isFavorite' => false,
        ];
    }

    private function variantPayload(Product $product, ProductVariant $variant): array
    {
        return [
            'id' => 'variant-'.$variant->id,
            'sourceType' => 'variant',
            'sourceId' => $variant->id,
            'name' => $product->name.' · '.$variant->name,
            'sku' => $variant->sku ?? $product->sku.'-'.$variant->id,
            'barcode' => $variant->barcode ?? '',
            'category' => $product->category?->code ?? 'umum',
            'categoryName' => $product->category?->name ?? 'Umum',
            'price' => $variant->sell_price,
            'stock' => $variant->stock,
            'type' => 'Varian',
            'unit' => $variant->unit ?: 'pcs',
            'isFavorite' => $variant->is_favorite,
        ];
    }

    private function itemsPayload()
    {
        return Product::query()
            ->with(['category', 'variants'])
            ->orderBy('name')
            ->get()
            ->flatMap(function (Product $product): array {
                $base = [$this->productPayload($product)];
                $variants = $product->variants
                    ->where('is_active', true)
                    ->map(fn (ProductVariant $variant): array => $this->variantPayload($product, $variant))
                    ->values()
                    ->all();

                return array_merge($base, $variants);
            })
            ->values();
    }

    private function lockSellable(string $id): array
    {
        if (Str::startsWith($id, 'product-')) {
            $sku = Str::after($id, 'product-');
            $product = Product::query()->where('sku', $sku)->lockForUpdate()->firstOrFail();

            return [
                'model' => $product,
                'type' => 'product',
                'product_id' => $product->id,
                'variant_id' => null,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => (int) $product->sell_price,
            ];
        }

        if (Str::startsWith($id, 'variant-')) {
            $variant = ProductVariant::query()
                ->with('product')
                ->whereKey((int) Str::after($id, 'variant-'))
                ->lockForUpdate()
                ->firstOrFail();

            return [
                'model' => $variant,
                'type' => 'variant',
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
                'name' => $variant->product->name.' · '.$variant->name,
                'sku' => $variant->sku ?? $variant->product->sku.'-'.$variant->id,
                'price' => (int) $variant->sell_price,
            ];
        }

        abort(422, 'Item transaksi tidak valid.');
    }

    private function consumeRawMaterials(int $productId, int $soldQuantity): void
    {
        ProductRecipeItem::query()
            ->with('rawMaterial')
            ->where('product_id', $productId)
            ->whereNotNull('raw_material_id')
            ->get()
            ->each(function (ProductRecipeItem $recipeItem) use ($soldQuantity): void {
                $rawMaterial = RawMaterial::query()
                    ->whereKey($recipeItem->raw_material_id)
                    ->lockForUpdate()
                    ->first();

                if (! $rawMaterial) {
                    return;
                }

                $used = (float) $recipeItem->quantity * $soldQuantity;
                $rawMaterial->update([
                    'stock' => max(0, (float) $rawMaterial->stock - $used),
                ]);
            });
    }

    private function refreshShiftTotals(CashierShift $shift): void
    {
        $totals = PosSale::query()
            ->where('cashier_shift_id', $shift->id)
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM(discount), 0) as discount_total')
            ->selectRaw('COALESCE(SUM(total), 0) as net_sales')
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END), 0) as cash_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'qris' THEN total ELSE 0 END), 0) as qris_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total ELSE 0 END), 0) as card_total")
            ->first();

        $shift->update([
            'sales_count' => (int) $totals->sales_count,
            'gross_sales' => (int) $totals->gross_sales,
            'discount_total' => (int) $totals->discount_total,
            'net_sales' => (int) $totals->net_sales,
            'cash_total' => (int) $totals->cash_total,
            'qris_total' => (int) $totals->qris_total,
            'card_total' => (int) $totals->card_total,
        ]);
    }

    private function nextInvoiceNumber(): string
    {
        return 'POS-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4));
    }

    private function shiftPayload(CashierShift $shift): array
    {
        return [
            'id' => $shift->id,
            'cashier' => $shift->user?->name ?? 'Kasir',
            'openedAt' => $shift->opened_at?->format('d M Y H:i'),
            'closedAt' => $shift->closed_at?->format('d M Y H:i'),
            'salesCount' => $shift->sales_count,
            'netSales' => $shift->net_sales,
            'cashTotal' => $shift->cash_total,
            'qrisTotal' => $shift->qris_total,
            'cardTotal' => $shift->card_total,
        ];
    }
}
