<?php

namespace App\Services;

use App\Models\CashierShift;
use App\Models\PosSale;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\BranchInventoryManager;
use Illuminate\Support\Facades\DB;

class SaleBranchTransferService
{
    public function __construct(private CashierShiftSummaryService $shiftSummary) {}

    public function transfer(PosSale $sale, int $fromBranchId, int $targetShiftId, User $actor, string $reason): PosSale
    {
        return DB::transaction(function () use ($sale, $fromBranchId, $targetShiftId, $actor, $reason): PosSale {
            $sale = PosSale::query()->with(['items.product', 'items.productVariant', 'shift'])
                ->whereKey($sale->id)->where('branch_id', $fromBranchId)->lockForUpdate()->firstOrFail();
            abort_if($sale->voided_at, 422, 'Transaksi yang sudah di-void tidak dapat dipindahkan.');

            $targetShift = CashierShift::query()->with('branch')
                ->whereKey($targetShiftId)
                ->whereHas('branch', fn ($query) => $query->where('user_id', $actor->tenantOwnerId()))
                ->lockForUpdate()->firstOrFail();
            abort_if($targetShift->branch_id === $fromBranchId, 422, 'Pilih shift dari cabang lain.');

            foreach ($sale->items as $item) {
                if ($item->product?->stock_mode !== 'inventory') {
                    continue;
                }

                $model = $item->product_variant_id ? $item->productVariant : $item->product;
                $source = $item->product_variant_id
                    ? app(BranchInventoryManager::class)->forVariant($fromBranchId, $model, true)
                    : app(BranchInventoryManager::class)->forProduct($fromBranchId, $model, true);
                $target = $item->product_variant_id
                    ? app(BranchInventoryManager::class)->forVariant($targetShift->branch_id, $model, true)
                    : app(BranchInventoryManager::class)->forProduct($targetShift->branch_id, $model, true);
                $quantity = (int) $item->quantity;
                abort_if((int) $target->stock < $quantity, 422, 'Stok cabang tujuan tidak cukup untuk '.$item->name.'.');

                $sourceBefore = (int) $source->stock;
                $targetBefore = (int) $target->stock;
                $source->update(['stock' => $sourceBefore + $quantity]);
                $target->update(['stock' => $targetBefore - $quantity]);
                StockMovement::query()->create([
                    'branch_id' => $fromBranchId, 'created_by_user_id' => $actor->id,
                    'product_id' => $item->product_id, 'product_variant_id' => $item->product_variant_id,
                    'type' => 'in', 'quantity' => $quantity, 'stock_before' => $sourceBefore, 'stock_after' => $sourceBefore + $quantity,
                    'reference' => $sale->invoice_number, 'note' => 'Pemindahan ke cabang '.$targetShift->branch?->name.': '.$reason, 'occurred_at' => now(),
                ]);
                StockMovement::query()->create([
                    'branch_id' => $targetShift->branch_id, 'created_by_user_id' => $actor->id,
                    'product_id' => $item->product_id, 'product_variant_id' => $item->product_variant_id,
                    'type' => 'out', 'quantity' => $quantity, 'stock_before' => $targetBefore, 'stock_after' => $targetBefore - $quantity,
                    'reference' => $sale->invoice_number, 'note' => 'Penerimaan pemindahan dari cabang '.$sale->branch_id.': '.$reason, 'occurred_at' => now(),
                ]);
            }

            $sourceShift = $sale->shift;
            $sale->update([
                'branch_id' => $targetShift->branch_id,
                'cashier_shift_id' => $targetShift->id,
                'transferred_from_branch_id' => $fromBranchId,
                'transferred_at' => now(),
                'transfer_reason' => $reason,
            ]);
            $this->shiftSummary->refresh($sourceShift);
            $this->shiftSummary->refresh($targetShift);

            return $sale->refresh()->load(['items', 'payments', 'branch', 'shift']);
        });
    }
}
