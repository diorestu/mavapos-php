<?php

namespace App\Services;

use App\Models\PosSale;
use App\Models\ProductRecipeItem;
use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\BranchInventoryManager;
use Illuminate\Support\Facades\DB;

class TransactionVoidService
{
    public function __construct(private CashierShiftSummaryService $shiftSummary) {}

    public function void(PosSale $sale, int $branchId, User $actor, string $reason): PosSale
    {
        return DB::transaction(function () use ($sale, $branchId, $actor, $reason): PosSale {
            $sale = PosSale::query()->with(['items.product', 'items.productVariant', 'rawMaterialUsages.rawMaterial', 'shift'])
                ->whereKey($sale->id)->where('branch_id', $branchId)->lockForUpdate()->firstOrFail();

            if ($actor->hasRole('kasir')) {
                abort_unless($sale->user_id === $actor->id && $sale->shift?->closed_at === null, 403);
            }

            abort_if($sale->voided_at, 409, 'Transaksi sudah dibatalkan sebelumnya.');

            foreach ($sale->items as $item) {
                $inventory = $item->product_variant_id && $item->productVariant
                    ? app(BranchInventoryManager::class)->forVariant($branchId, $item->productVariant, true)
                    : app(BranchInventoryManager::class)->forProduct($branchId, $item->product, true);
                $before = (int) $inventory->stock;
                $after = $before + (int) $item->quantity;
                $inventory->update(['stock' => $after]);
                ($item->productVariant ?: $item->product)->update(['stock' => $after]);
                StockMovement::query()->create([
                    'branch_id' => $branchId, 'created_by_user_id' => $actor->id,
                    'product_id' => $item->product_id, 'product_variant_id' => $item->product_variant_id,
                    'type' => 'in', 'quantity' => $item->quantity, 'stock_before' => $before, 'stock_after' => $after,
                    'reference' => $sale->invoice_number, 'note' => 'Pembalikan void: '.$reason, 'occurred_at' => now(),
                ]);
            }

            $usages = $sale->rawMaterialUsages;
            if ($usages->isEmpty()) {
                $usages = $this->legacyUsages($sale);
            }
            foreach ($usages as $usage) {
                $material = RawMaterial::query()->whereKey($usage->raw_material_id)->lockForUpdate()->first();
                if ($material) {
                    $material->update(['stock' => (float) $material->stock + (float) $usage->quantity]);
                }
            }

            $sale->update(['voided_at' => now(), 'voided_by_user_id' => $actor->id, 'void_reason' => $reason]);
            $this->shiftSummary->refresh($sale->shift);

            return $sale->refresh()->load(['items', 'voidedBy']);
        });
    }

    private function legacyUsages(PosSale $sale)
    {
        $totals = $sale->items->flatMap(function ($item) {
            return ProductRecipeItem::query()
                ->where('product_id', $item->product_id)
                ->whereNotNull('raw_material_id')
                ->get()
                ->map(fn ($recipe): array => [
                    'raw_material_id' => $recipe->raw_material_id,
                    'quantity' => (float) $recipe->quantity * (int) $item->quantity,
                    'unit' => $recipe->unit,
                ]);
        })->groupBy('raw_material_id');

        return $totals->map(fn ($rows, $rawMaterialId) => $sale->rawMaterialUsages()->create([
            'raw_material_id' => $rawMaterialId,
            'quantity' => $rows->sum('quantity'),
            'unit' => $rows->first()['unit'],
            'is_legacy_fallback' => true,
        ]))->values();
    }
}
