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
        // 1. Add key_version column with default 0 (for legacy/historical records signed with APP_KEY)
        Schema::connection('pgsql_owner')->table('platform_audit_logs', function (Blueprint $table) {
            $table->unsignedInteger('key_version')->default(0)->after('payload_hash');
            $table->index('key_version', 'idx_platform_audit_logs_key_version');
        });

        // 2. Upgrade the immutability function to intercept and reject TRUNCATE
        DB::connection('pgsql_owner')->unprepared("
            CREATE OR REPLACE FUNCTION trg_fn_enforce_platform_audit_log_immutability()
            RETURNS TRIGGER AS $$
            BEGIN
                IF (TG_OP = 'UPDATE') THEN
                    RAISE EXCEPTION 'Platform Audit Security Violation: platform_audit_logs records are strictly immutable and cannot be updated.';
                ELSIF (TG_OP = 'DELETE') THEN
                    RAISE EXCEPTION 'Platform Audit Security Violation: platform_audit_logs records cannot be deleted from the audit ledger.';
                ELSIF (TG_OP = 'TRUNCATE') THEN
                    RAISE EXCEPTION 'Platform Audit Security Violation: platform_audit_logs table cannot be truncated.';
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;

            -- 3. Create statement-level trigger for TRUNCATE
            DROP TRIGGER IF EXISTS trg_platform_audit_logs_truncate ON platform_audit_logs;
            CREATE TRIGGER trg_platform_audit_logs_truncate
            BEFORE TRUNCATE ON platform_audit_logs
            FOR EACH STATEMENT EXECUTE FUNCTION trg_fn_enforce_platform_audit_log_immutability();
        ");

        // 4. Revoke TRUNCATE, UPDATE, DELETE permissions from application runtime roles
        DB::connection('pgsql_owner')->unprepared('
            REVOKE TRUNCATE, UPDATE, DELETE ON platform_audit_logs FROM PUBLIC;
            REVOKE TRUNCATE, UPDATE, DELETE ON platform_audit_logs FROM app_rw;
            REVOKE TRUNCATE, UPDATE, DELETE ON platform_audit_logs FROM reporting_ro;

            GRANT SELECT, INSERT ON platform_audit_logs TO app_rw;
            GRANT SELECT ON platform_audit_logs TO reporting_ro;
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('pgsql_owner')->unprepared("
            DROP TRIGGER IF EXISTS trg_platform_audit_logs_truncate ON platform_audit_logs;

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
        ");

        Schema::connection('pgsql_owner')->table('platform_audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_platform_audit_logs_key_version');
            $table->dropColumn('key_version');
        });
    }
};
