<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('basic_salary')->default(0)->after('role');
            $table->unsignedBigInteger('fixed_allowance')->default(0)->after('basic_salary');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['basic_salary', 'fixed_allowance']);
        });
    }
};
