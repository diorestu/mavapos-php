<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::table('pos_sales', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('cashier_shift_id')->constrained()->nullOnDelete();
        });

        $defaultBranchId = DB::table('branches')->orderBy('id')->value('id');

        if ($defaultBranchId) {
            DB::table('cashier_shifts')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
            DB::table('pos_sales')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
        }

        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->index(['branch_id', 'closed_at', 'opened_at']);
        });

        Schema::table('pos_sales', function (Blueprint $table) {
            $table->index(['branch_id', 'sold_at']);
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'sold_at']);
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'closed_at', 'opened_at']);
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
