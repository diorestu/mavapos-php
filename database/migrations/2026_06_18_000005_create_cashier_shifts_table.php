<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedInteger('sales_count')->default(0);
            $table->unsignedBigInteger('gross_sales')->default(0);
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('net_sales')->default(0);
            $table->unsignedBigInteger('cash_total')->default(0);
            $table->unsignedBigInteger('qris_total')->default(0);
            $table->unsignedBigInteger('card_total')->default(0);
            $table->text('opening_note')->nullable();
            $table->text('closing_note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'opened_at']);
            $table->index(['closed_at', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_shifts');
    }
};
