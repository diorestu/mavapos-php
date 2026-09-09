<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('tenant_owner_id')->constrained('branches')->nullOnDelete();
            $table->index(['tenant_owner_id', 'branch_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['users_tenant_owner_id_branch_id_role_index']);
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
