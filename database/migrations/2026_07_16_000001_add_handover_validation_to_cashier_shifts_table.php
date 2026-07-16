<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_shifts', function (Blueprint $table): void {
            $table->foreignId('previous_cashier_shift_id')->nullable()->after('branch_id')->constrained('cashier_shifts')->nullOnDelete();
            $table->unsignedBigInteger('validated_cash_amount')->nullable()->after('opening_cash_amount');
            $table->unsignedBigInteger('validated_card_amount')->nullable()->after('validated_cash_amount');
            $table->timestamp('handover_validated_at')->nullable()->after('validated_card_amount');
        });
    }

    public function down(): void
    {
        Schema::table('cashier_shifts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('previous_cashier_shift_id');
            $table->dropColumn([
                'validated_cash_amount',
                'validated_card_amount',
                'handover_validated_at',
            ]);
        });
    }
};
