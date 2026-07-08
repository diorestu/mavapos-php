<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TABLES = [
        'products',
        'product_categories',
        'suppliers',
        'customers',
        'expenses',
        'raw_materials',
        'store_settings',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $tableGroup) {
                $tableGroup->foreignId('user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
            });
        }

        // Backfill existing data to the first owner/admin user
        $firstOwnerId = DB::table('users')->where('role', 'owner')->orderBy('id')->value('id')
            ?? DB::table('users')->orderBy('id')->value('id');

        if ($firstOwnerId) {
            foreach (self::TABLES as $table) {
                DB::table($table)->whereNull('user_id')->update(['user_id' => $firstOwnerId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $tableGroup) {
                $tableGroup->dropConstrainedForeignId('user_id');
            });
        }
    }
};
