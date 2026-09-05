<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('client_uuid')->nullable()->after('comanda_folio');
            $table->unique(['business_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
