<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->string('complimentary_category', 30)->nullable()->after('payment_method');
            $table->string('complimentary_recipient_name', 150)->nullable()->after('complimentary_category');
            $table->index(['complimentary_category', 'sold_at']);
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->dropIndex(['complimentary_category', 'sold_at']);
            $table->dropColumn(['complimentary_category', 'complimentary_recipient_name']);
        });
    }
};
