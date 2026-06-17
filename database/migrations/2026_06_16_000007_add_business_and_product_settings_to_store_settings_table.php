<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('store_name');
            $table->string('business_type')->default('retail')->after('owner_name');
            $table->string('currency', 10)->default('IDR')->after('business_type');
            $table->text('operational_hours')->nullable()->after('tax_number');
            $table->text('product_categories')->nullable()->after('notes');
            $table->text('product_units')->nullable()->after('product_categories');
            $table->text('product_brands')->nullable()->after('product_units');
            $table->text('product_variants')->nullable()->after('product_brands');
            $table->text('product_modifiers')->nullable()->after('product_variants');
            $table->string('sku_mode', 20)->default('manual')->after('product_modifiers');
            $table->boolean('barcode_enabled')->default(true)->after('sku_mode');
            $table->boolean('selling_price_enabled')->default(true)->after('barcode_enabled');
            $table->boolean('cost_price_enabled')->default(true)->after('selling_price_enabled');
            $table->boolean('product_status_enabled')->default(true)->after('cost_price_enabled');
            $table->boolean('cashier_favorite_enabled')->default(false)->after('product_status_enabled');
            $table->boolean('taxable_default')->default(false)->after('cashier_favorite_enabled');
            $table->boolean('discountable_default')->default(true)->after('taxable_default');
            $table->text('spicy_levels')->nullable()->after('discountable_default');
            $table->text('toppings')->nullable()->after('spicy_levels');
            $table->text('size_options')->nullable()->after('toppings');
            $table->boolean('kitchen_notes_enabled')->default(false)->after('size_options');
            $table->boolean('dine_in_takeaway_enabled')->default(false)->after('kitchen_notes_enabled');
            $table->boolean('serving_time_enabled')->default(false)->after('dine_in_takeaway_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn([
                'logo_path',
                'business_type',
                'currency',
                'operational_hours',
                'product_categories',
                'product_units',
                'product_brands',
                'product_variants',
                'product_modifiers',
                'sku_mode',
                'barcode_enabled',
                'selling_price_enabled',
                'cost_price_enabled',
                'product_status_enabled',
                'cashier_favorite_enabled',
                'taxable_default',
                'discountable_default',
                'spicy_levels',
                'toppings',
                'size_options',
                'kitchen_notes_enabled',
                'dine_in_takeaway_enabled',
                'serving_time_enabled',
            ]);
        });
    }
};
