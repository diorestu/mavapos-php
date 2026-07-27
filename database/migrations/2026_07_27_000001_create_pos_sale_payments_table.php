<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_sale_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method', 20);
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->unique(['pos_sale_id', 'payment_method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sale_payments');
    }
};
