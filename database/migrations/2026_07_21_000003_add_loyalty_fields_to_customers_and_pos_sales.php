<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('loyalty_cup_balance')->default(0)->after('status');
        });

        Schema::table('pos_sales', function (Blueprint $table) {
            $table->string('loyalty_reward', 20)->nullable()->after('buyer_nationality');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropColumn('loyalty_reward');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('loyalty_cup_balance');
        });
    }
};
