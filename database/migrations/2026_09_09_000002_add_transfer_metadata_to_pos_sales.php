<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->foreignId('transferred_from_branch_id')->nullable()->after('branch_id')->constrained('branches')->nullOnDelete();
            $table->timestamp('transferred_at')->nullable();
            $table->text('transfer_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->dropForeign(['transferred_from_branch_id']);
            $table->dropColumn(['transferred_from_branch_id', 'transferred_at', 'transfer_reason']);
        });
    }
};
