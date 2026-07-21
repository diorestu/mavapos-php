<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedTinyInteger('loyalty_stamp_count')->default(0)->after('loyalty_cup_balance');
            $table->boolean('loyalty_fifty_reward_available')->default(false)->after('loyalty_stamp_count');
            $table->boolean('loyalty_free_reward_available')->default(false)->after('loyalty_fifty_reward_available');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['loyalty_stamp_count', 'loyalty_fifty_reward_available', 'loyalty_free_reward_available']);
        });
    }
};
