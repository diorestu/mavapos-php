<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billings', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('fee')->nullable();
            $table->unsignedBigInteger('total_payment')->nullable();
            $table->string('payment_provider')->default('pakasir');
            $table->string('payment_method')->default('qris');
            $table->string('payment_status')->default('pending');
            $table->string('payment_url')->nullable();
            $table->string('payment_number')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();

            $table->index(['payment_provider', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billings');
    }
};
