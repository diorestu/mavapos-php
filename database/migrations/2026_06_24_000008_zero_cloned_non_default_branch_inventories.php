<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaultBranchId = DB::table('branches')->orderBy('id')->value('id');

        if (! $defaultBranchId) {
            return;
        }

        DB::table('branch_inventories')
            ->join('products', 'branch_inventories.product_id', '=', 'products.id')
            ->where('branch_inventories.branch_id', '!=', $defaultBranchId)
            ->whereNull('branch_inventories.product_variant_id')
            ->whereColumn('branch_inventories.stock', 'products.stock')
            ->whereColumn('branch_inventories.created_at', 'branch_inventories.updated_at')
            ->update([
                'branch_inventories.stock' => 0,
                'branch_inventories.updated_at' => now(),
            ]);

        DB::table('branch_inventories')
            ->join('product_variants', 'branch_inventories.product_variant_id', '=', 'product_variants.id')
            ->where('branch_inventories.branch_id', '!=', $defaultBranchId)
            ->whereNull('branch_inventories.product_id')
            ->whereColumn('branch_inventories.stock', 'product_variants.stock')
            ->whereColumn('branch_inventories.created_at', 'branch_inventories.updated_at')
            ->update([
                'branch_inventories.stock' => 0,
                'branch_inventories.updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        //
    }
};
