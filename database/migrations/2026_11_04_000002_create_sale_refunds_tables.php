<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('pos_sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->uuid('client_refund_id');
            $table->string('request_hash', 64);
            $table->string('refund_number')->unique();
            $table->unsignedBigInteger('amount');
            $table->string('refund_method', 30);
            $table->string('reason', 500);
            $table->timestamp('refunded_at');
            $table->timestamps();
            $table->unique(['tenant_id', 'client_refund_id']);
            $table->index(['branch_id', 'refunded_at']);
        });
        Schema::create('sale_refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_refund_id')->constrained()->restrictOnDelete();
            $table->foreignId('pos_sale_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('amount');
            $table->boolean('restock')->default(false);
            $table->timestamps();
            $table->unique(['sale_refund_id', 'pos_sale_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_refund_items');
        Schema::dropIfExists('sale_refunds');
    }
};
