<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->foreignId('from_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('from_stock_before');
            $table->unsignedInteger('from_stock_after');
            $table->unsignedInteger('to_stock_before');
            $table->unsignedInteger('to_stock_after');
            $table->text('note')->nullable();
            $table->timestamp('transferred_at')->useCurrent();
            $table->timestamps();

            $table->index(['from_branch_id', 'transferred_at']);
            $table->index(['to_branch_id', 'transferred_at']);
            $table->index(['product_id', 'transferred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
