<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table): void {
            $table->string('printer_label_type', 30)->default('none');
            $table->string('printer_label_language', 20)->nullable();
            $table->string('printer_label_template', 40)->nullable();
            $table->unsignedSmallInteger('printer_label_width_mm')->nullable();
            $table->unsignedSmallInteger('printer_label_height_mm')->nullable();
            $table->unsignedSmallInteger('printer_label_gap_mm')->nullable();
            $table->unsignedTinyInteger('printer_label_font')->nullable();
            $table->unsignedTinyInteger('printer_label_font_size')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'printer_label_type',
                'printer_label_language',
                'printer_label_template',
                'printer_label_width_mm',
                'printer_label_height_mm',
                'printer_label_gap_mm',
                'printer_label_font',
                'printer_label_font_size',
            ]);
        });
    }
};
