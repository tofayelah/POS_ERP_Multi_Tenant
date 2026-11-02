<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('transactional_outbox', function (Blueprint $table) {
            $table->string('aggregate_type', 64);
            $table->uuid('aggregate_id');
            $table->string('event_type', 64);
            $table->jsonb('payload');
            $table->jsonb('headers')->nullable();
            $table->string('status', 32)->default('pending');
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestampTz('scheduled_at')->useCurrent();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();
        });

        DB::connection('pgsql_owner')->unprepared("
            -- Index for worker polling with SKIP LOCKED
            CREATE INDEX idx_transactional_outbox_pending ON transactional_outbox (tenant_id, scheduled_at ASC)
                WHERE status = 'pending';

            -- Index for aggregate lookups and audit tracing
            CREATE INDEX idx_transactional_outbox_aggregate ON transactional_outbox (tenant_id, aggregate_type, aggregate_id);

            -- Index for status filtering and dead-letter monitoring
            CREATE INDEX idx_transactional_outbox_status ON transactional_outbox (tenant_id, status);
        ");
    }

    public function down(): void
    {
        $this->dropTenantTable('transactional_outbox');
    }
};
