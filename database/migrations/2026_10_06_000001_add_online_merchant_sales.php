<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table): void {
            $table->boolean('cashier_online_merchant_enabled')->default(false);
        });
        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->string('online_merchant', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', fn (Blueprint $table) => $table->dropColumn('online_merchant'));
        Schema::table('store_settings', fn (Blueprint $table) => $table->dropColumn('cashier_online_merchant_enabled'));
    }
};
