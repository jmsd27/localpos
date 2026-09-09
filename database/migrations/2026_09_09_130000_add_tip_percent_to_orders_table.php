<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Porcentaje de propina elegido en el cobro (null = propina fija en
            // pesos o sin propina). Se guarda para poder imprimirlo en el ticket.
            $table->decimal('tip_percent', 5, 2)->nullable()->after('tip_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tip_percent');
        });
    }
};
