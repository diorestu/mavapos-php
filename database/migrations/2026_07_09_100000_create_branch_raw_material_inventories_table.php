<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_raw_material_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained()->cascadeOnDelete();
            $table->decimal('stock', 12, 3)->default(0);
            $table->decimal('min_stock', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'raw_material_id'], 'branch_raw_material_unique');
            $table->index(['branch_id', 'stock']);
        });

        $defaultBranchId = DB::table('branches')->orderBy('id')->value('id');

        if (! $defaultBranchId) {
            return;
        }

        DB::table('raw_materials')
            ->orderBy('id')
            ->select(['id', 'stock', 'min_stock'])
            ->chunkById(100, function ($materials) use ($defaultBranchId): void {
                DB::table('branch_raw_material_inventories')->insert($materials->map(fn ($material): array => [
                    'branch_id' => $defaultBranchId,
                    'raw_material_id' => $material->id,
                    'stock' => (float) $material->stock,
                    'min_stock' => (float) $material->min_stock,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_raw_material_inventories');
    }
};
