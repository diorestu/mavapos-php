<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_api_key_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->text('signing_secret');
            $table->timestamps();
        });
        Schema::create('partner_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_webhook_id')->constrained()->cascadeOnDelete();
            $table->uuid('event_id')->unique();
            $table->json('payload');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('available_at')->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_webhook_deliveries');
        Schema::dropIfExists('partner_webhooks');
    }
};
