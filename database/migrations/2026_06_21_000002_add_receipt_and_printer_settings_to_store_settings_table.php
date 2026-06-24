<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->text('receipt_footer_note')->nullable()->after('serving_time_enabled');
            $table->string('receipt_paper_width', 10)->default('58')->after('receipt_footer_note');
            $table->boolean('receipt_show_logo')->default(true)->after('receipt_paper_width');
            $table->boolean('receipt_show_store_address')->default(true)->after('receipt_show_logo');
            $table->boolean('receipt_show_cashier')->default(true)->after('receipt_show_store_address');
            $table->boolean('printer_auto_print')->default(false)->after('receipt_show_cashier');
            $table->boolean('printer_close_after_print')->default(false)->after('printer_auto_print');
            $table->string('printer_connection_mode', 20)->default('browser')->after('printer_close_after_print');
            $table->string('printer_bluetooth_service_uuid')->nullable()->after('printer_connection_mode');
            $table->string('printer_bluetooth_characteristic_uuid')->nullable()->after('printer_bluetooth_service_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn([
                'receipt_footer_note',
                'receipt_paper_width',
                'receipt_show_logo',
                'receipt_show_store_address',
                'receipt_show_cashier',
                'printer_auto_print',
                'printer_close_after_print',
                'printer_connection_mode',
                'printer_bluetooth_service_uuid',
                'printer_bluetooth_characteristic_uuid',
            ]);
        });
    }
};
