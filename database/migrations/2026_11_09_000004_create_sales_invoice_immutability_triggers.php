<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Implements Section 9.2 Triggers 4 and 5 as written in design (naive versions).
     */
    public function up(): void
    {
        DB::connection('pgsql_owner')->unprepared("
            -- Trigger 4: Sales Invoice Immutability Guard (Design §9.2 as written)
            CREATE OR REPLACE FUNCTION trg_fn_enforce_sales_invoice_immutability()
            RETURNS TRIGGER AS $$
            BEGIN
                IF (TG_OP = 'DELETE') THEN
                    IF (OLD.status = 'issued') THEN
                        RAISE EXCEPTION 'Statutory Audit Violation: Issued Sales Invoice % cannot be deleted. Issue a Mushak 6.7 Credit Note.', OLD.id;
                    END IF;
                    RETURN OLD;
                END IF;

                IF (TG_OP = 'UPDATE') THEN
                    IF (OLD.status = 'issued') THEN
                        -- Allow one-time fiscal enrichment strictly for offline synced invoices
                        IF (OLD.fiscal_signature IS NULL AND NEW.fiscal_signature IS NOT NULL) OR
                           (OLD.sdc_invoice_number IS NULL AND NEW.sdc_invoice_number IS NOT NULL) THEN
                            IF (NEW.subtotal <> OLD.subtotal OR NEW.tax_amount <> OLD.tax_amount OR NEW.total_payable <> OLD.total_payable) THEN
                                RAISE EXCEPTION 'Audit Violation: Cannot modify invoice amounts during fiscal enrichment.';
                            END IF;
                            RETURN NEW;
                        ELSE
                            RAISE EXCEPTION 'Statutory Audit Violation: Issued Sales Invoice % is immutable and cannot be modified.', OLD.id;
                        END IF;
                    END IF;
                    RETURN NEW;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_sales_invoice_immutability ON sales_invoices;
            CREATE TRIGGER trg_sales_invoice_immutability
            BEFORE UPDATE OR DELETE ON sales_invoices
            FOR EACH ROW
            EXECUTE FUNCTION trg_fn_enforce_sales_invoice_immutability();

            -- Trigger 5: Sales Invoice Lines Immutability Guard (Design §9.2 as written)
            CREATE OR REPLACE FUNCTION trg_fn_enforce_invoice_lines_immutability()
            RETURNS TRIGGER AS $$
            DECLARE
                v_invoice_status VARCHAR(30);
                v_invoice_id UUID;
            BEGIN
                IF (TG_OP = 'DELETE') THEN
                    v_invoice_id := OLD.sales_invoice_id;
                ELSE
                    v_invoice_id := NEW.sales_invoice_id;
                END IF;

                SELECT status INTO v_invoice_status
                FROM sales_invoices
                WHERE id = v_invoice_id;

                IF v_invoice_status = 'issued' THEN
                    RAISE EXCEPTION 'Statutory Audit Violation: Cannot % line items on an already issued Sales Invoice (ID: %).', TG_OP, v_invoice_id;
                END IF;

                IF (TG_OP = 'DELETE') THEN
                    RETURN OLD;
                ELSE
                    RETURN NEW;
                END IF;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_invoice_lines_immutability ON sales_invoice_lines;
            CREATE TRIGGER trg_invoice_lines_immutability
            BEFORE INSERT OR UPDATE OR DELETE ON sales_invoice_lines
            FOR EACH ROW
            EXECUTE FUNCTION trg_fn_enforce_invoice_lines_immutability();

            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'app_rw') THEN
                    REVOKE TRUNCATE ON sales_invoices FROM app_rw;
                    REVOKE TRUNCATE ON sales_invoice_lines FROM app_rw;
                END IF;
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'reporting_ro') THEN
                    REVOKE TRUNCATE ON sales_invoices FROM reporting_ro;
                    REVOKE TRUNCATE ON sales_invoice_lines FROM reporting_ro;
                END IF;
            END
            $$;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('pgsql_owner')->unprepared('
            DROP TRIGGER IF EXISTS trg_sales_invoice_immutability ON sales_invoices;
            DROP FUNCTION IF EXISTS trg_fn_enforce_sales_invoice_immutability();
            DROP TRIGGER IF EXISTS trg_invoice_lines_immutability ON sales_invoice_lines;
            DROP FUNCTION IF EXISTS trg_fn_enforce_invoice_lines_immutability();
        ');
    }
};
