<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            $table->string('printer_name')->nullable()->after('ip_address');
            $table->string('api_token', 64)->nullable()->unique()->after('printer_name');
        });
    }

    public function down(): void
    {
        Schema::table('terminals', function (Blueprint $table) {
            $table->dropColumn(['printer_name', 'api_token']);
        });
    }
};
