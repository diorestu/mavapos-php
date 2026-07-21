<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete()->index();
        });

        DB::table('billings')->whereNotNull('customer_id')->orderBy('id')->each(function (object $billing): void {
            $ownerId = DB::table('customers')->where('id', $billing->customer_id)->value('user_id');
            if ($ownerId) {
                DB::table('billings')->where('id', $billing->id)->update(['user_id' => $ownerId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
