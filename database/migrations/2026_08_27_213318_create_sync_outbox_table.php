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
        Schema::create('sync_outbox', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('operation');
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('synced_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('synced_at');
            $table->index(['branch_id', 'model_type', 'model_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_outbox');
    }
};
