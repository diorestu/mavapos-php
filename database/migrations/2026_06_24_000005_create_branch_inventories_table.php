<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('min_stock')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
            $table->unique(['branch_id', 'product_variant_id']);
            $table->index(['branch_id', 'stock']);
        });

        $branchId = DB::table('branches')->orderBy('id')->value('id');

        if (! $branchId) {
            return;
        }

        DB::table('products')
            ->orderBy('id')
            ->select(['id', 'stock', 'min_stock'])
            ->chunkById(100, function ($products) use ($branchId): void {
                DB::table('branch_inventories')->insert($products->map(fn ($product): array => [
                    'branch_id' => $branchId,
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'stock' => (int) $product->stock,
                    'min_stock' => (int) $product->min_stock,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            });

        DB::table('product_variants')
            ->orderBy('id')
            ->select(['id', 'product_id', 'stock', 'min_stock'])
            ->chunkById(100, function ($variants) use ($branchId): void {
                DB::table('branch_inventories')->insert($variants->map(fn ($variant): array => [
                    'branch_id' => $branchId,
                    'product_id' => null,
                    'product_variant_id' => $variant->id,
                    'stock' => (int) $variant->stock,
                    'min_stock' => (int) $variant->min_stock,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_inventories');
    }
};
