<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('payment_method', 20);
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->unsignedBigInteger('change_amount')->default(0);
            $table->timestamp('sold_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'sold_at']);
            $table->index(['cashier_shift_id', 'sold_at']);
            $table->index(['payment_method', 'sold_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sales');
    }
};
