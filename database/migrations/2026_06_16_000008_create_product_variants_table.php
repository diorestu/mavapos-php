<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable();
            $table->string('unit', 40)->nullable();
            $table->unsignedInteger('unit_conversion')->default(1);
            $table->json('attributes')->nullable();
            $table->unsignedBigInteger('buy_price')->default(0);
            $table->unsignedBigInteger('sell_price');
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('min_stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_discountable')->default(true);
            $table->unsignedSmallInteger('serving_time_minutes')->nullable();
            $table->boolean('available_for_dine_in')->default(true);
            $table->boolean('available_for_takeaway')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
