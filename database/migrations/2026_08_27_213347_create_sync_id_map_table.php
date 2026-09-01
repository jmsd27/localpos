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
        Schema::create('sync_id_map', function (Blueprint $table) {
            $table->id();
            $table->string('branch_code');
            $table->string('model_type');
            $table->unsignedBigInteger('local_id');
            $table->unsignedBigInteger('cloud_id');
            $table->timestamps();

            $table->unique(['branch_code', 'model_type', 'local_id']);
            $table->index(['model_type', 'cloud_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_id_map');
    }
};
