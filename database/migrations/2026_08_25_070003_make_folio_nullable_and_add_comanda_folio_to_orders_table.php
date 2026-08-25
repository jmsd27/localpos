<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('folio')->nullable()->change();
            $table->string('comanda_folio')->nullable()->after('folio');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unique(['business_id', 'comanda_folio']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'comanda_folio']);
            $table->dropColumn('comanda_folio');
            $table->string('folio')->nullable(false)->change();
        });
    }
};
