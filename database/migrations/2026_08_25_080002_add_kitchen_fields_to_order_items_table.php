<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('kitchen_station_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->string('kitchen_status')->default('nuevo')->after('subtotal');
            $table->timestamp('started_at')->nullable()->after('kitchen_status');
            $table->timestamp('ready_at')->nullable()->after('started_at');
            $table->timestamp('delivered_at')->nullable()->after('ready_at');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kitchen_station_id');
            $table->dropColumn(['kitchen_status', 'started_at', 'ready_at', 'delivered_at']);
        });
    }
};
