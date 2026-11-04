<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Implements Section 9.2 Complete Immutability & Financial Balance Triggers (NEW-02, NEW-03, NEW-13, NEW-21).
     */
    public function up(): void
    {
        DB::connection('pgsql_owner')->unprepared("
            -- Trigger 1: Before INSERT Guard on journal_entries (NEW-13)
            CREATE OR REPLACE FUNCTION trg_fn_journal_entry_insert_guard()
            RETURNS TRIGGER AS $$
            BEGIN
                IF (NEW.is_posted = TRUE) THEN
                    RAISE EXCEPTION 'Financial Integrity Violation: Journal entries must be created in draft state (is_posted = FALSE) first.';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_journal_entry_insert_guard ON journal_entries;
            CREATE TRIGGER trg_journal_entry_insert_guard
            BEFORE INSERT ON journal_entries
            FOR EACH ROW
            EXECUTE FUNCTION trg_fn_journal_entry_insert_guard();

            -- Trigger 2: Before UPDATE or DELETE on journal_entries (NEW-02, NEW-03, NEW-13, NEW-21)
            CREATE OR REPLACE FUNCTION trg_fn_enforce_journal_entry_immutability()
            RETURNS TRIGGER AS $$
            DECLARE
                v_line_count INT;
                v_total_debit DECIMAL(18, 4);
                v_total_credit DECIMAL(18, 4);
                v_period_status VARCHAR(30);
                v_period_start DATE;
                v_period_end DATE;
                v_orig_is_posted BOOLEAN;
                v_orig_company_id UUID;
            BEGIN
                IF (TG_OP = 'DELETE') THEN
                    IF (OLD.is_posted = TRUE) THEN
                        RAISE EXCEPTION 'Audit Integrity Violation: Posted Journal Entry % cannot be deleted. Issue a reversing entry.', OLD.id;
                    END IF;
                    RETURN OLD;
                END IF;

                IF (TG_OP = 'UPDATE') THEN
                    IF (OLD.is_posted = TRUE) THEN
                        RAISE EXCEPTION 'Audit Integrity Violation: Posted Journal Entry % is immutable and cannot be updated.', OLD.id;
                    END IF;

                    -- Transitioning from draft to posted (is_posted FALSE -> TRUE)
                    IF (OLD.is_posted = FALSE AND NEW.is_posted = TRUE) THEN
                        -- Verify Period Status & Date Validity (Resolving NEW-21)
                        SELECT status, start_date, end_date INTO v_period_status, v_period_start, v_period_end
                        FROM accounting_periods
                        WHERE id = NEW.period_id;

                        IF v_period_status <> 'open' THEN
                            RAISE EXCEPTION 'Financial Integrity Violation: Cannot post to an accounting period that is not open (Period ID: %, Status: %).',
                                NEW.period_id, v_period_status;
                        END IF;

                        IF NEW.posting_date < v_period_start OR NEW.posting_date > v_period_end THEN
                            RAISE EXCEPTION 'Financial Integrity Violation: Posting date % is outside period bounds (% to %).',
                                NEW.posting_date, v_period_start, v_period_end;
                        END IF;

                        -- Verify Reversal Integrity (Resolving NEW-21)
                        IF NEW.is_reversal = TRUE THEN
                            IF NEW.reversed_entry_id IS NULL THEN
                                RAISE EXCEPTION 'Financial Integrity Violation: Reversal entry must specify reversed_entry_id.';
                            END IF;

                            SELECT is_posted, company_id INTO v_orig_is_posted, v_orig_company_id
                            FROM journal_entries
                            WHERE id = NEW.reversed_entry_id;

                            IF v_orig_is_posted <> TRUE THEN
                                RAISE EXCEPTION 'Financial Integrity Violation: Target of reversal entry % is not posted.', NEW.reversed_entry_id;
                            END IF;

                            IF v_orig_company_id <> NEW.company_id THEN
                                RAISE EXCEPTION 'Financial Integrity Violation: Cannot reverse an entry belonging to a different company.';
                            END IF;
                        END IF;

                        -- Verify Line Count and Debits = Credits
                        SELECT COUNT(*), COALESCE(SUM(base_debit_amount), 0), COALESCE(SUM(base_credit_amount), 0)
                        INTO v_line_count, v_total_debit, v_total_credit
                        FROM journal_lines
                        WHERE journal_entry_id = NEW.id;

                        IF v_line_count < 2 THEN
                            RAISE EXCEPTION 'Financial Integrity Violation: Journal Entry % must contain at least 2 lines to post (Found %).', NEW.id, v_line_count;
                        END IF;

                        IF v_total_debit <> v_total_credit THEN
                            RAISE EXCEPTION 'Financial Integrity Violation: Journal Entry % is unbalanced. Debits (%) != Credits (%)', NEW.id, v_total_debit, v_total_credit;
                        END IF;
                    END IF;

                    RETURN NEW;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_journal_entry_immutability ON journal_entries;
            CREATE TRIGGER trg_journal_entry_immutability
            BEFORE UPDATE OR DELETE ON journal_entries
            FOR EACH ROW
            EXECUTE FUNCTION trg_fn_enforce_journal_entry_immutability();

            -- Trigger 3: Before INSERT, UPDATE, or DELETE on journal_lines (NEW-02, NEW-13)
            CREATE OR REPLACE FUNCTION trg_fn_enforce_journal_lines_immutability()
            RETURNS TRIGGER AS $$
            DECLARE
                v_target_entry_id UUID;
                v_is_posted BOOLEAN;
            BEGIN
                IF (TG_OP = 'UPDATE') THEN
                    IF (NEW.journal_entry_id <> OLD.journal_entry_id) THEN
                        RAISE EXCEPTION 'Financial Integrity Violation: Cannot reassign journal_entry_id on a journal line.';
                    END IF;
                    IF (NEW.company_id <> OLD.company_id) THEN
                        RAISE EXCEPTION 'Financial Integrity Violation: Cannot change company_id on a journal line.';
                    END IF;
                END IF;

                IF (TG_OP = 'DELETE') THEN
                    v_target_entry_id := OLD.journal_entry_id;
                ELSE
                    v_target_entry_id := NEW.journal_entry_id;
                END IF;

                -- Lock parent entry FOR SHARE to prevent race conditions with concurrent posting (NEW-13)
                SELECT is_posted INTO v_is_posted
                FROM journal_entries
                WHERE id = v_target_entry_id
                FOR SHARE;

                IF v_is_posted = TRUE THEN
                    RAISE EXCEPTION 'Audit Integrity Violation: Cannot % lines on an already posted Journal Entry (ID: %).', TG_OP, v_target_entry_id;
                END IF;

                IF (TG_OP = 'DELETE') THEN
                    RETURN OLD;
                ELSE
                    RETURN NEW;
                END IF;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_journal_lines_immutability ON journal_lines;
            CREATE TRIGGER trg_journal_lines_immutability
            BEFORE INSERT OR UPDATE OR DELETE ON journal_lines
            FOR EACH ROW
            EXECUTE FUNCTION trg_fn_enforce_journal_lines_immutability();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('pgsql_owner')->unprepared('
            DROP TRIGGER IF EXISTS trg_journal_lines_immutability ON journal_lines;
            DROP FUNCTION IF EXISTS trg_fn_enforce_journal_lines_immutability();

            DROP TRIGGER IF EXISTS trg_journal_entry_immutability ON journal_entries;
            DROP FUNCTION IF EXISTS trg_fn_enforce_journal_entry_immutability();

            DROP TRIGGER IF EXISTS trg_journal_entry_insert_guard ON journal_entries;
            DROP FUNCTION IF EXISTS trg_fn_journal_entry_insert_guard();
        ');
    }
};
