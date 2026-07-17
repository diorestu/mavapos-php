<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->json('companion_staff_ids')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('cashier_shifts', fn (Blueprint $table) => $table->dropColumn('companion_staff_ids'));
    }
};
