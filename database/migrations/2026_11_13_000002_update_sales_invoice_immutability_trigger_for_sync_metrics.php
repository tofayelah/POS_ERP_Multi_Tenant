<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Updates sales invoice immutability trigger to protect offline sync and clock-skew metrics from post-issuance tampering.
     */
    public function up(): void
    {
        DB::connection('pgsql_owner')->unprepared("
            CREATE OR REPLACE FUNCTION trg_fn_enforce_sales_invoice_immutability()
            RETURNS TRIGGER AS $$
            DECLARE
                v_line_count INT;
                v_sum_line_total DECIMAL(18, 4);
                v_sum_tax DECIMAL(18, 4);
                v_sum_sd DECIMAL(18, 4);
            BEGIN
                -- Immutability on DELETE: Invoices are NEVER deleted from the audit ledger.
                -- Draft invoices must use the void flow (status = 'cancelled') to preserve gapless numbering.
                IF (TG_OP = 'DELETE') THEN
                    IF (OLD.status = 'issued') THEN
                        RAISE EXCEPTION 'Statutory Audit Violation: Issued Sales Invoice % cannot be deleted. Issue a Mushak 6.7 Credit Note.', OLD.id;
                    ELSIF (OLD.status = 'cancelled') THEN
                        RAISE EXCEPTION 'Statutory Audit Violation: Cancelled Sales Invoice % cannot be deleted. Audit ledger records must be retained.', OLD.id;
                    ELSE
                        RAISE EXCEPTION 'Statutory Audit Violation: Draft Sales Invoice % cannot be deleted. Use the void flow (update status to cancelled) to preserve gapless sequence.', OLD.id;
                    END IF;
                END IF;

                -- Immutability on UPDATE
                IF (TG_OP = 'UPDATE') THEN
                    -- Rule 1: Cancelled invoices are strictly immutable
                    IF (OLD.status = 'cancelled') THEN
                        RAISE EXCEPTION 'Statutory Audit Violation: Cancelled Sales Invoice % is immutable and cannot be updated.', OLD.id;
                    END IF;

                    -- Rule 2: Draft to Issued transition checks
                    IF (OLD.status = 'draft' AND NEW.status = 'issued') THEN
                        -- Must have at least one line item
                        SELECT COUNT(*),
                               COALESCE(SUM(line_total), 0.0000),
                               COALESCE(SUM(tax_amount), 0.0000),
                               COALESCE(SUM(supplementary_duty_amount), 0.0000)
                        INTO v_line_count, v_sum_line_total, v_sum_tax, v_sum_sd
                        FROM sales_invoice_lines
                        WHERE sales_invoice_id = NEW.id;

                        IF (v_line_count = 0) THEN
                            RAISE EXCEPTION 'Statutory Validation Violation: Cannot issue Sales Invoice % with 0 line items.', NEW.id;
                        END IF;

                        -- Header totals must match line item sums
                        IF (NEW.subtotal <> v_sum_line_total) THEN
                            RAISE EXCEPTION 'Header-Line Imbalance Violation: Sales Invoice % subtotal (%) does not equal sum of lines (%).',
                                NEW.id, NEW.subtotal, v_sum_line_total;
                        END IF;

                        IF (NEW.tax_amount <> v_sum_tax) THEN
                            RAISE EXCEPTION 'Tax Amount Imbalance Violation: Sales Invoice % tax amount (%) does not equal sum of lines (%).',
                                NEW.id, NEW.tax_amount, v_sum_tax;
                        END IF;

                        IF (NEW.supplementary_duty_amount <> v_sum_sd) THEN
                            RAISE EXCEPTION 'Supplementary Duty Imbalance Violation: Sales Invoice % duty amount (%) does not equal sum of lines (%).',
                                NEW.id, NEW.supplementary_duty_amount, v_sum_sd;
                        END IF;

                        IF (NEW.total_payable <> (NEW.subtotal - NEW.discount_amount + NEW.supplementary_duty_amount + NEW.tax_amount)) THEN
                            RAISE EXCEPTION 'Total Payable Imbalance Violation: Sales Invoice % total payable (%) does not equal subtotal - discount + duty + tax (%).',
                                NEW.id, NEW.total_payable, (NEW.subtotal - NEW.discount_amount + NEW.supplementary_duty_amount + NEW.tax_amount);
                        END IF;

                        RETURN NEW;
                    END IF;

                    -- Rule 3: Legitimate void flow (draft -> cancelled)
                    IF (OLD.status = 'draft' AND NEW.status = 'cancelled') THEN
                        RETURN NEW;
                    END IF;

                    -- Rule 4: Normal draft edits (while staying draft)
                    IF (OLD.status = 'draft' AND NEW.status = 'draft') THEN
                        RETURN NEW;
                    END IF;

                    -- Rule 5: Issued invoice protections and fiscal enrichment whitelist
                    IF (OLD.status = 'issued') THEN
                        -- Check if this is a valid one-time fiscal enrichment
                        IF ((OLD.fiscal_signature IS NULL AND NEW.fiscal_signature IS NOT NULL) OR
                            (OLD.sdc_invoice_number IS NULL AND NEW.sdc_invoice_number IS NOT NULL)) THEN

                            -- Overwriting existing non-null fiscal fields is strictly prohibited
                            IF (OLD.fiscal_signature IS NOT NULL AND NEW.fiscal_signature <> OLD.fiscal_signature) THEN
                                RAISE EXCEPTION 'Statutory Audit Violation: fiscal_signature on Sales Invoice % cannot be overwritten once set.', OLD.id;
                            END IF;

                            IF (OLD.sdc_invoice_number IS NOT NULL AND NEW.sdc_invoice_number <> OLD.sdc_invoice_number) THEN
                                RAISE EXCEPTION 'Statutory Audit Violation: sdc_invoice_number on Sales Invoice % cannot be overwritten once set.', OLD.id;
                            END IF;

                            -- Every other column MUST be identical to OLD
                            IF (
                                NEW.id <> OLD.id OR
                                NEW.tenant_id <> OLD.tenant_id OR
                                NEW.company_id <> OLD.company_id OR
                                NEW.branch_id <> OLD.branch_id OR
                                NEW.terminal_id IS DISTINCT FROM OLD.terminal_id OR
                                NEW.client_txn_id IS DISTINCT FROM OLD.client_txn_id OR
                                NEW.invoice_number <> OLD.invoice_number OR
                                NEW.invoice_date <> OLD.invoice_date OR
                                NEW.business_date <> OLD.business_date OR
                                NEW.customer_id IS DISTINCT FROM OLD.customer_id OR
                                NEW.status <> OLD.status OR
                                NEW.subtotal <> OLD.subtotal OR
                                NEW.discount_amount <> OLD.discount_amount OR
                                NEW.supplementary_duty_amount <> OLD.supplementary_duty_amount OR
                                NEW.tax_amount <> OLD.tax_amount OR
                                NEW.total_payable <> OLD.total_payable OR
                                NEW.payment_status <> OLD.payment_status OR
                                NEW.is_synced_from_offline <> OLD.is_synced_from_offline OR
                                NEW.device_time IS DISTINCT FROM OLD.device_time OR
                                NEW.server_received_time IS DISTINCT FROM OLD.server_received_time OR
                                NEW.clock_skew_seconds IS DISTINCT FROM OLD.clock_skew_seconds OR
                                NEW.is_clock_skew_flagged <> OLD.is_clock_skew_flagged
                            ) THEN
                                RAISE EXCEPTION 'Statutory Audit Violation: Fiscal enrichment on Sales Invoice % may only fill fiscal_signature or sdc_invoice_number from NULL. Modifying status, number, dates, customer, discounts, duty or totals is prohibited.', OLD.id;
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
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original trigger function definition without sync metrics checks
    }
};
