<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kitchen_stations', function (Blueprint $table) {
            $table->foreignId('printer_terminal_id')->nullable()->after('name')->constrained('terminals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kitchen_stations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('printer_terminal_id');
        });
    }
};
