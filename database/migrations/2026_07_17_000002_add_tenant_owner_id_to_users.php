<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->index('tenant_owner_id');
        });
        DB::table('users')->where('role', 'owner')->update(['tenant_owner_id' => DB::raw('id')]);
        DB::table('users')->where('role', '!=', 'owner')->whereNull('tenant_owner_id')->get()->each(function ($user): void {
            $ownerId = DB::table('users')->where('role', 'owner')->where('trial_ends_at', $user->trial_ends_at)->value('id');
            if ($ownerId) DB::table('users')->where('id', $user->id)->update(['tenant_owner_id' => $ownerId]);
        });

        $firstOwnerId = DB::table('users')->where('role', 'owner')->orderBy('id')->value('id');
        foreach (['products', 'product_categories', 'suppliers', 'customers', 'expenses', 'raw_materials', 'store_settings', 'branches'] as $table) {
            if ($firstOwnerId && Schema::hasColumn($table, 'user_id')) {
                DB::table($table)->whereNull('user_id')->update(['user_id' => $firstOwnerId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_owner_id']);
            $table->dropConstrainedForeignId('tenant_owner_id');
        });
    }
};
