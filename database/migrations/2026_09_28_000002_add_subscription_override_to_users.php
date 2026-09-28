<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('subscription_override_until')->nullable()->after('trial_ends_at');
            $table->text('subscription_override_reason')->nullable()->after('subscription_override_until');
            $table->foreignId('subscription_override_by')->nullable()->after('subscription_override_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['subscription_override_by']);
            $table->dropColumn(['subscription_override_until', 'subscription_override_reason', 'subscription_override_by']);
        });
    }
};
