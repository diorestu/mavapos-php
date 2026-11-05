<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_owner_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('client_transaction_id');
            $table->unsignedBigInteger('actor_id');
            $table->unsignedBigInteger('cashier_id');
            $table->unsignedBigInteger('branch_id');
            $table->char('payload_hash', 64);
            $table->unsignedSmallInteger('response_status');
            $table->longText('response_body');
            $table->timestamps();
            $table->unique(['tenant_owner_id', 'client_transaction_id'], 'checkout_requests_tenant_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_requests');
    }
};
