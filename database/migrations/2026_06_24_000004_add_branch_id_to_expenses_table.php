<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        $defaultBranchId = DB::table('branches')->orderBy('id')->value('id');

        if ($defaultBranchId) {
            DB::table('expenses')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->index(['branch_id', 'spent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'spent_at']);
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
