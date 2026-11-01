<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds dual timestamps, clock skew tracking, and metrics for POS offline sync (Design §10.2, §11.2, POS-10).
     */
    public function up(): void
    {
        Schema::connection('pgsql_owner')->table('sales_invoices', function (Blueprint $table) {
            $table->timestampTz('device_time')->nullable()->after('is_synced_from_offline');
            $table->timestampTz('server_received_time')->nullable()->after('device_time');
            $table->integer('clock_skew_seconds')->nullable()->after('server_received_time');
            $table->boolean('is_clock_skew_flagged')->default(false)->after('clock_skew_seconds');

            $table->index(['tenant_id', 'is_clock_skew_flagged'], 'idx_sales_invoices_clock_skew');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql_owner')->table('sales_invoices', function (Blueprint $table) {
            $table->dropIndex('idx_sales_invoices_clock_skew');
            $table->dropColumn([
                'device_time',
                'server_received_time',
                'clock_skew_seconds',
                'is_clock_skew_flagged',
            ]);
        });
    }
};
