<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            $table->unsignedInteger('printer_port')->nullable()->default(9100)->after('printer_name');
            $table->string('connection_type')->default('red')->after('printer_port');
            $table->string('usb_path')->nullable()->after('connection_type');
            $table->unsignedTinyInteger('paper_width_chars')->default(48)->after('usb_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            $table->dropColumn(['printer_port', 'connection_type', 'usb_path', 'paper_width_chars']);
        });
    }
};
