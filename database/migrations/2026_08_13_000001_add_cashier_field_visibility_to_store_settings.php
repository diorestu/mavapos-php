<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table): void {
            $table->boolean('cashier_buyer_nationality_enabled')->default(true);
            $table->boolean('cashier_loyalty_card_enabled')->default(true);
            $table->boolean('cashier_split_payment_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'cashier_buyer_nationality_enabled',
                'cashier_loyalty_card_enabled',
                'cashier_split_payment_enabled',
            ]);
        });
    }
};
