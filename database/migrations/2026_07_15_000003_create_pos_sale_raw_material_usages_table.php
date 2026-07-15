<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sale_raw_material_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pos_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raw_material_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 30)->nullable();
            $table->boolean('is_legacy_fallback')->default(false);
            $table->timestamps();
            $table->unique(['pos_sale_id', 'raw_material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sale_raw_material_usages');
    }
};
