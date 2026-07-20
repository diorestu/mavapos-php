<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->string('buyer_nationality', 20)->nullable()->after('customer_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->dropIndex(['buyer_nationality']);
            $table->dropColumn('buyer_nationality');
        });
    }
};
