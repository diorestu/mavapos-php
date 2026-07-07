<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $branches = DB::table('branches')->orderBy('id')->get(['id']);
        $products = DB::table('products')->orderBy('id')->get(['id', 'min_stock']);
        $variants = DB::table('product_variants')->orderBy('id')->get(['id', 'min_stock']);

        foreach ($branches as $branch) {
            foreach ($products as $product) {
                $exists = DB::table('branch_inventories')
                    ->where('branch_id', $branch->id)
                    ->where('product_id', $product->id)
                    ->whereNull('product_variant_id')
                    ->exists();

                if (! $exists) {
                    DB::table('branch_inventories')->insert([
                        'branch_id' => $branch->id,
                        'product_id' => $product->id,
                        'product_variant_id' => null,
                        'stock' => 0,
                        'min_stock' => (int) $product->min_stock,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            foreach ($variants as $variant) {
                $exists = DB::table('branch_inventories')
                    ->where('branch_id', $branch->id)
                    ->whereNull('product_id')
                    ->where('product_variant_id', $variant->id)
                    ->exists();

                if (! $exists) {
                    DB::table('branch_inventories')->insert([
                        'branch_id' => $branch->id,
                        'product_id' => null,
                        'product_variant_id' => $variant->id,
                        'stock' => 0,
                        'min_stock' => (int) $variant->min_stock,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        //
    }
};
