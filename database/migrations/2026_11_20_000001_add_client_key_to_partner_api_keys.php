<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_api_keys', function (Blueprint $table): void {
            $table->string('client_key_hash', 64)->nullable();
            $table->string('client_key_prefix', 24)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('partner_api_keys', fn (Blueprint $table) => $table->dropColumn(['client_key_hash', 'client_key_prefix']));
    }
};
