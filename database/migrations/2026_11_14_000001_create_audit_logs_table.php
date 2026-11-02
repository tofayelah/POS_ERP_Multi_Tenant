<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('audit_logs', function (Blueprint $table) {
            $table->bigInteger('chain_position')->nullable();
            $table->string('table_name', 64);
            $table->uuid('record_id');
            $table->string('action', 16);
            $table->uuid('actor_id');
            $table->string('actor_type', 32)->default('user');
            $table->ipAddress('client_ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('payload_hash', 64);
            $table->string('prev_hash', 64)->nullable();
            $table->string('current_hash', 64)->nullable();
            $table->string('status', 32)->default('pending_hash');
            $table->timestampTz('hashed_at')->nullable();
            $table->timestampsTz();
        });

        DB::connection('pgsql_owner')->unprepared("
            -- Unique constraint on chain_position per tenant for assigned rows
            CREATE UNIQUE INDEX uq_audit_logs_chain_position ON audit_logs (tenant_id, chain_position)
                WHERE chain_position IS NOT NULL;

            -- Indexes for pending hashing and verification traversal
            CREATE INDEX idx_audit_logs_pending_hash ON audit_logs (tenant_id, created_at ASC)
                WHERE status = 'pending_hash';

            CREATE INDEX idx_audit_logs_verified ON audit_logs (tenant_id, chain_position ASC)
                WHERE status = 'verified';

            CREATE INDEX idx_audit_logs_record ON audit_logs (tenant_id, table_name, record_id);

            -- Immutability Trigger: Prohibits DELETE completely, and allows UPDATE only for the one-time transition
            -- from 'pending_hash' to 'verified' with valid chain metrics.
            CREATE OR REPLACE FUNCTION trg_fn_enforce_audit_logs_immutability()
            RETURNS TRIGGER AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Audit Log Immutability Violation: Audit log records cannot be deleted (Record ID: %).', OLD.id;
                END IF;

                IF TG_OP = 'UPDATE' THEN
                    -- Allow only the single one-time transition from pending_hash to verified
                    IF OLD.status = 'pending_hash' AND NEW.status = 'verified' THEN
                        -- Ensure core audit facts were not altered
                        IF NEW.tenant_id = OLD.tenant_id
                           AND NEW.id = OLD.id
                           AND NEW.table_name = OLD.table_name
                           AND NEW.record_id = OLD.record_id
                           AND NEW.action = OLD.action
                           AND NEW.actor_id = OLD.actor_id
                           AND NEW.actor_type = OLD.actor_type
                           AND NEW.payload_hash = OLD.payload_hash
                           AND (NEW.old_values IS NOT DISTINCT FROM OLD.old_values)
                           AND (NEW.new_values IS NOT DISTINCT FROM OLD.new_values)
                           AND NEW.created_at = OLD.created_at
                           AND NEW.chain_position IS NOT NULL
                           AND NEW.current_hash IS NOT NULL
                           AND NEW.hashed_at IS NOT NULL
                        THEN
                            RETURN NEW;
                        ELSE
                            RAISE EXCEPTION 'Audit Log Immutability Violation: Core audit facts cannot be altered during hashing transition (Record ID: %).', OLD.id;
                        END IF;
                    END IF;

                    RAISE EXCEPTION 'Audit Log Immutability Violation: Audit log records cannot be modified (Record ID: %, Status: %).', OLD.id, OLD.status;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_audit_logs_immutable ON audit_logs;
            CREATE TRIGGER trg_audit_logs_immutable
            BEFORE UPDATE OR DELETE ON audit_logs
            FOR EACH ROW
            EXECUTE FUNCTION trg_fn_enforce_audit_logs_immutability();
        ");
    }

    public function down(): void
    {
        DB::connection('pgsql_owner')->unprepared('
            DROP TRIGGER IF EXISTS trg_audit_logs_immutable ON audit_logs;
            DROP FUNCTION IF EXISTS trg_fn_enforce_audit_logs_immutability();
        ');

        $this->dropTenantTable('audit_logs');
    }
};
