<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Implements Financial Immutability Triggers for Payments and Allocation Synchronization (ADR-003, ADR-019).
     */
    public function up(): void
    {
        DB::connection('pgsql_owner')->unprepared("
            -- 1. Payment Immutability Function
            CREATE OR REPLACE FUNCTION trg_fn_enforce_payment_immutability()
            RETURNS TRIGGER AS $$
            BEGIN
                -- Prevent deletion of posted/reconciled payments
                IF (TG_OP = 'DELETE') THEN
                    IF (OLD.status IN ('posted', 'reconciled')) THEN
                        RAISE EXCEPTION 'Financial Immutability Violation: Posted Payment % cannot be deleted. Corrections must be made via reversal entries.', OLD.id;
                    END IF;
                    RETURN OLD;
                END IF;

                -- Guard updates on posted/reconciled payments
                IF (TG_OP = 'UPDATE') THEN
                    IF (OLD.status IN ('posted', 'reconciled')) THEN
                        -- Cannot modify financial attributes
                        IF (NEW.amount <> OLD.amount OR 
                            NEW.payment_type <> OLD.payment_type OR 
                            NEW.payment_method <> OLD.payment_method OR 
                            NEW.party_type <> OLD.party_type OR 
                            NEW.party_id <> OLD.party_id OR 
                            NEW.payment_date <> OLD.payment_date OR 
                            NEW.payment_number <> OLD.payment_number OR
                            NEW.company_id <> OLD.company_id OR
                            NEW.tenant_id <> OLD.tenant_id) THEN
                            RAISE EXCEPTION 'Financial Immutability Violation: Financial attributes of Posted Payment % cannot be modified.', OLD.id;
                        END IF;
                    END IF;
                    RETURN NEW;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_payment_immutability
            BEFORE UPDATE OR DELETE ON payments
            FOR EACH ROW
            EXECUTE FUNCTION trg_fn_enforce_payment_immutability();

            -- 2. Payment Allocation Balance Sync Function
            CREATE OR REPLACE FUNCTION trg_fn_sync_payment_allocation()
            RETURNS TRIGGER AS $$
            DECLARE
                v_rem NUMERIC(18, 4);
                v_orig NUMERIC(18, 4);
                v_alloc NUMERIC(18, 4);
            BEGIN
                IF (TG_OP = 'INSERT') THEN
                    -- Lock the open item row for update to prevent concurrent race conditions
                    SELECT remaining_amount, original_amount, allocated_amount
                    INTO v_rem, v_orig, v_alloc
                    FROM subledger_open_items
                    WHERE id = NEW.open_item_id
                    FOR UPDATE;

                    IF NOT FOUND THEN
                        RAISE EXCEPTION 'Subledger Error: Target open item % not found.', NEW.open_item_id;
                    END IF;

                    IF (NEW.allocated_amount > v_rem) THEN
                        RAISE EXCEPTION 'Allocation Violation: Allocated amount (%) exceeds remaining open balance (%) of open item %.', NEW.allocated_amount, v_rem, NEW.open_item_id;
                    END IF;

                    -- Update remaining and allocated amounts atomically
                    UPDATE subledger_open_items
                    SET allocated_amount = allocated_amount + NEW.allocated_amount,
                        remaining_amount = remaining_amount - NEW.allocated_amount,
                        status = CASE 
                            WHEN (remaining_amount - NEW.allocated_amount) = 0 THEN 'fully_allocated'
                            ELSE 'partially_allocated'
                        END,
                        updated_at = NOW()
                    WHERE id = NEW.open_item_id;

                    RETURN NEW;
                END IF;

                IF (TG_OP = 'DELETE') THEN
                    -- Restore open item balance on allocation removal/reversal
                    UPDATE subledger_open_items
                    SET allocated_amount = allocated_amount - OLD.allocated_amount,
                        remaining_amount = remaining_amount + OLD.allocated_amount,
                        status = CASE 
                            WHEN (allocated_amount - OLD.allocated_amount) = 0 THEN 'open'
                            ELSE 'partially_allocated'
                        END,
                        updated_at = NOW()
                    WHERE id = OLD.open_item_id;

                    RETURN OLD;
                END IF;

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_payment_allocation_sync
            AFTER INSERT OR DELETE ON payment_allocations
            FOR EACH ROW
            EXECUTE FUNCTION trg_fn_sync_payment_allocation();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('pgsql_owner')->unprepared('
            DROP TRIGGER IF EXISTS trg_payment_allocation_sync ON payment_allocations;
            DROP FUNCTION IF EXISTS trg_fn_sync_payment_allocation();

            DROP TRIGGER IF EXISTS trg_payment_immutability ON payments;
            DROP FUNCTION IF EXISTS trg_fn_enforce_payment_immutability();
        ');
    }
};
