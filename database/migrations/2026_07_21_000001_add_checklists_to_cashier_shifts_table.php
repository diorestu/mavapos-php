<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_shifts', function (Blueprint $table): void {
            $table->json('opening_checklist')->nullable()->after('opening_note');
            $table->json('closing_checklist')->nullable()->after('closing_note');
        });
    }

    public function down(): void
    {
        Schema::table('cashier_shifts', function (Blueprint $table): void {
            $table->dropColumn(['opening_checklist', 'closing_checklist']);
        });
    }
};
