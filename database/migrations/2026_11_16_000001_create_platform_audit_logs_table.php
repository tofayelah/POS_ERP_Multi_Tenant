<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pgsql_owner')->create('platform_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('platform_user_id')
                ->nullable()
                ->constrained('platform_users')
                ->nullOnDelete();
            $table->string('actor_name', 150);
            $table->string('actor_email', 150);
            $table->string('action', 100)->index();
            $table->string('resource_type', 150)->index();
            $table->string('resource_id', 100)->index();
            $table->string('resource_description', 255)->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('status', 30)->default('success');
            $table->text('error_message')->nullable();
            $table->string('payload_hash', 64);
            $table->timestampTz('created_at')->index();
        });

        // Add PostgreSQL immutability guard trigger
        DB::connection('pgsql_owner')->unprepared("
            CREATE OR REPLACE FUNCTION trg_fn_enforce_platform_audit_log_immutability()
            RETURNS TRIGGER AS $$
            BEGIN
                IF (TG_OP = 'UPDATE') THEN
                    RAISE EXCEPTION 'Platform Audit Security Violation: platform_audit_logs records are strictly immutable and cannot be updated.';
                ELSIF (TG_OP = 'DELETE') THEN
                    RAISE EXCEPTION 'Platform Audit Security Violation: platform_audit_logs records cannot be deleted from the audit ledger.';
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_platform_audit_logs_immutability ON platform_audit_logs;
            CREATE TRIGGER trg_platform_audit_logs_immutability
            BEFORE UPDATE OR DELETE ON platform_audit_logs
            FOR EACH ROW EXECUTE FUNCTION trg_fn_enforce_platform_audit_log_immutability();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('pgsql_owner')->unprepared("
            DROP TRIGGER IF EXISTS trg_platform_audit_logs_immutability ON platform_audit_logs;
            DROP FUNCTION IF EXISTS trg_fn_enforce_platform_audit_log_immutability();
        ");

        Schema::connection('pgsql_owner')->dropIfExists('platform_audit_logs');
    }
};
