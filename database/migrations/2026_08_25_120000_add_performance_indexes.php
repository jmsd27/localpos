<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['business_id', 'status', 'completed_at'], 'orders_business_status_completed_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['business_id', 'created_at'], 'audit_logs_business_created_idx');
        });

        Schema::table('print_jobs', function (Blueprint $table) {
            $table->index(['terminal_id', 'status', 'created_at'], 'print_jobs_terminal_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_business_status_completed_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_business_created_idx');
        });

        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropIndex('print_jobs_terminal_status_created_idx');
        });
    }
};
