<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
        });

        // Assign existing branches to the first owner/admin user
        $firstOwnerId = DB::table('users')->where('role', 'owner')->orderBy('id')->value('id') 
            ?? DB::table('users')->orderBy('id')->value('id');

        if ($firstOwnerId) {
            DB::table('branches')->whereNull('user_id')->update(['user_id' => $firstOwnerId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
