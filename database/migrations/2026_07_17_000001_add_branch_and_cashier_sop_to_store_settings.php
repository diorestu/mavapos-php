<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->cascadeOnDelete();
            $table->longText('cashier_sop_html')->nullable()->after('notes');
            $table->index(['user_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'branch_id']);
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn('cashier_sop_html');
        });
    }
};
