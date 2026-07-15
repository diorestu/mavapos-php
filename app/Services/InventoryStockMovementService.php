<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Support\BranchInventoryManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryStockMovementService
{
    public function record(
        string $sku,
        string $type,
        int $quantity,
        int $branchId,
        ?int $actorId = null,
        ?string $reference = null,
        ?string $note = null,
        mixed $occurredAt = null,
    ): array {
        abort_unless(in_array($type, ['in', 'out'], true), 422, 'Jenis pergerakan stok tidak valid.');

        return DB::transaction(function () use ($sku, $type, $quantity, $branchId, $actorId, $reference, $note, $occurredAt): array {
            $resolved = $this->resolve($sku);
            abort_unless($resolved, 404, 'Item tidak ditemukan.');

            $model = $resolved['model'];
            $product = $resolved['type'] === 'variant' ? $model->product : $model;
            $inventory = $resolved['type'] === 'variant'
                ? app(BranchInventoryManager::class)->forVariant($branchId, $model, true)
                : app(BranchInventoryManager::class)->forProduct($branchId, $model, true);
            $stockBefore = (int) $inventory->stock;

            if ($type === 'out' && $quantity > $stockBefore) {
                throw ValidationException::withMessages(['quantity' => 'Stok keluar melebihi stok tersedia.']);
            }

            $stockAfter = $type === 'in' ? $stockBefore + $quantity : $stockBefore - $quantity;
            $inventory->update(['stock' => $stockAfter]);
            $model->update(['stock' => $stockAfter]);

            $movement = StockMovement::query()->create([
                'branch_id' => $branchId,
                'created_by_user_id' => $actorId,
                'product_id' => $product->id,
                'product_variant_id' => $resolved['type'] === 'variant' ? $model->id : null,
                'type' => $type,
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reference' => $reference,
                'note' => $note,
                'occurred_at' => $occurredAt ? Carbon::parse($occurredAt) : now(),
            ]);

            return [
                'product' => $product,
                'variant' => $resolved['type'] === 'variant' ? $model : null,
                'movement' => $movement,
                'stockBefore' => $stockBefore,
                'stockAfter' => $stockAfter,
            ];
        });
    }

    private function resolve(string $sku): ?array
    {
        $variant = ProductVariant::query()->with('product')->where('sku', $sku)->where('is_active', true)->first();

        if (! $variant && Str::contains($sku, '-')) {
            $variant = ProductVariant::query()->with('product')
                ->whereKey((int) Str::afterLast($sku, '-'))
                ->whereHas('product', fn ($query) => $query->where('sku', Str::beforeLast($sku, '-')))
                ->where('is_active', true)->first();
        }

        if ($variant) {
            return ['type' => 'variant', 'model' => $variant];
        }

        $product = Product::query()->where('sku', $sku)->first();

        return $product ? ['type' => 'product', 'model' => $product] : null;
    }
}
