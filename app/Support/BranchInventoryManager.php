<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Product;
use App\Models\ProductVariant;

class BranchInventoryManager
{
    public function stockForProduct(int $branchId, Product $product): int
    {
        $stock = BranchInventory::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->value('stock');

        return $stock === null
            ? $this->defaultStockForBranch($branchId, (int) $product->stock)
            : (int) $stock;
    }

    public function minStockForProduct(int $branchId, Product $product): int
    {
        $minStock = BranchInventory::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->value('min_stock');

        return $minStock === null
            ? (int) $product->min_stock
            : (int) $minStock;
    }

    public function forProduct(int $branchId, Product $product, bool $lock = false): BranchInventory
    {
        $query = BranchInventory::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $product->id)
            ->whereNull('product_variant_id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first()
            ?? BranchInventory::query()->create([
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'product_variant_id' => null,
                'stock' => $this->defaultStockForBranch($branchId, (int) $product->stock),
                'min_stock' => (int) $product->min_stock,
            ]);
    }

    public function forVariant(int $branchId, ProductVariant $variant, bool $lock = false): BranchInventory
    {
        $query = BranchInventory::query()
            ->where('branch_id', $branchId)
            ->where('product_variant_id', $variant->id)
            ->whereNull('product_id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first()
            ?? BranchInventory::query()->create([
                'branch_id' => $branchId,
                'product_id' => null,
                'product_variant_id' => $variant->id,
                'stock' => $this->defaultStockForBranch($branchId, (int) $variant->stock),
                'min_stock' => (int) $variant->min_stock,
            ]);
    }

    public function initializeBranch(int $branchId): void
    {
        Product::query()
            ->orderBy('id')
            ->each(fn (Product $product) => BranchInventory::query()->firstOrCreate([
                'branch_id' => $branchId,
                'product_id' => $product->id,
                'product_variant_id' => null,
            ], [
                'stock' => 0,
                'min_stock' => (int) $product->min_stock,
            ]));

        ProductVariant::query()
            ->orderBy('id')
            ->each(fn (ProductVariant $variant) => BranchInventory::query()->firstOrCreate([
                'branch_id' => $branchId,
                'product_id' => null,
                'product_variant_id' => $variant->id,
            ], [
                'stock' => 0,
                'min_stock' => (int) $variant->min_stock,
            ]));
    }

    private function defaultStockForBranch(int $branchId, int $legacyStock): int
    {
        $defaultBranchId = Branch::query()->orderBy('id')->value('id');

        return $defaultBranchId === $branchId ? $legacyStock : 0;
    }
}
