<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $t) {
            $t->string('payment_status', 30)->default('paid')->index();
        });
        Schema::create('gateway_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('pos_sale_id')->unique()->constrained()->restrictOnDelete();
            $t->string('environment', 16);
            $t->string('order_id', 50)->unique();
            $t->uuid('client_request_id');
            $t->string('request_hash', 64);
            $t->unsignedBigInteger('amount');
            $t->unsignedBigInteger('refunded_amount')->default(0);
            $t->unsignedBigInteger('fee_amount')->nullable();
            $t->string('fee_reference', 200)->nullable();
            $t->foreignId('fee_confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('fee_reconciled_at')->nullable();
            $t->boolean('review_required')->default(false);
            $t->string('status', 40)->default('pending');
            $t->string('creation_status', 20)->default('creating');
            $t->string('payment_type')->nullable();
            $t->string('transaction_id')->nullable();
            $t->text('snap_token')->nullable();
            $t->text('payment_url')->nullable();
            $t->json('provider_payload')->nullable();
            $t->timestamp('expires_at');
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('settled_at')->nullable();
            $t->timestamp('last_checked_at')->nullable();
            $t->timestamps();
            $t->unique(['owner_id', 'client_request_id']);
            $t->unique(['environment', 'transaction_id']);
            $t->index(['owner_id', 'environment', 'status']);
        });
        Schema::create('wallet_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->string('environment', 16);
            $t->timestamps();
            $t->unique(['owner_id', 'environment']);
        });
        Schema::create('wallet_withdrawals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wallet_account_id')->constrained()->restrictOnDelete();
            $t->uuid('request_id');
            $t->string('request_hash', 64);
            $t->unsignedBigInteger('amount');
            $t->string('bank_name', 100);
            $t->text('account_number');
            $t->string('account_holder', 150);
            $t->string('status', 20)->default('pending');
            $t->string('reference', 200)->nullable();
            $t->foreignId('processed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
            $t->unique(['wallet_account_id', 'request_id']);
        });
        Schema::create('wallet_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wallet_account_id')->constrained()->restrictOnDelete();
            $t->foreignId('gateway_payment_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('wallet_withdrawal_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('bucket', 30);
            $t->bigInteger('amount');
            $t->string('kind', 40);
            $t->string('posting_key', 160);
            $t->timestamps();
            $t->unique(['wallet_account_id', 'posting_key']);
            $t->index(['wallet_account_id', 'branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_entries');
        Schema::dropIfExists('wallet_withdrawals');
        Schema::dropIfExists('wallet_accounts');
        Schema::dropIfExists('gateway_payments');
        Schema::table('pos_sales', function (Blueprint $t): void {
            $t->dropIndex(['payment_status']);
            $t->dropColumn('payment_status');
        });
    }
};
