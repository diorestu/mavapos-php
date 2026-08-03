<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period_month');
            $table->unsignedBigInteger('basic_salary')->default(0);
            $table->unsignedBigInteger('fixed_allowance')->default(0);
            $table->unsignedInteger('sales_count')->default(0);
            $table->unsignedBigInteger('sales_bonus')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'branch_id', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
