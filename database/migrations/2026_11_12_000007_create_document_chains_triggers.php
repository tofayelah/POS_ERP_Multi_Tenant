<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Implements Document Immutability Triggers for Sales Orders, Challans, POs, and GRNs (NEW-34).
     */
    public function up(): void
    {
        DB::connection('pgsql_owner')->unprepared("
            -- 1. Delivery Challan Immutability
            CREATE OR REPLACE FUNCTION trg_fn_enforce_delivery_challan_immutability()
            RETURNS TRIGGER AS $$
            BEGIN
                IF (TG_OP = 'DELETE') THEN
                    IF (OLD.status IN ('confirmed', 'invoiced')) THEN
                        RAISE EXCEPTION 'Document Immutability Violation: Confirmed/Invoiced Delivery Challan % cannot be deleted. Corrections must be handled through returns or credit adjustments.', OLD.id;
                    END IF;
                    RETURN OLD;
                END IF;

                IF (TG_OP = 'UPDATE') THEN
                    IF (OLD.status IN ('confirmed', 'invoiced')) THEN
                        IF (NEW.tenant_id <> OLD.tenant_id OR 
                            NEW.company_id <> OLD.company_id OR 
                            NEW.warehouse_id <> OLD.warehouse_id OR 
                            NEW.customer_id <> OLD.customer_id OR 
                            NEW.challan_number <> OLD.challan_number OR 
                            NEW.challan_date <> OLD.challan_date) THEN
                            RAISE EXCEPTION 'Document Immutability Violation: Core attributes of Confirmed Delivery Challan % cannot be modified.', OLD.id;
                        END IF;
                    END IF;
                    RETURN NEW;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_delivery_challan_immutability
            BEFORE UPDATE OR DELETE ON delivery_challans
            FOR EACH ROW
            EXECUTE FUNCTION trg_fn_enforce_delivery_challan_immutability();

            -- 2. Goods Receipt (GRN) Immutability
            CREATE OR REPLACE FUNCTION trg_fn_enforce_goods_receipt_immutability()
            RETURNS TRIGGER AS $$
            BEGIN
                IF (TG_OP = 'DELETE') THEN
                    IF (OLD.status IN ('received', 'billed')) THEN
                        RAISE EXCEPTION 'Document Immutability Violation: Received/Billed Goods Receipt % cannot be deleted. Corrections must be handled through debit notes or return challans.', OLD.id;
                    END IF;
                    RETURN OLD;
                END IF;

                IF (TG_OP = 'UPDATE') THEN
                    IF (OLD.status IN ('received', 'billed')) THEN
                        IF (NEW.tenant_id <> OLD.tenant_id OR 
                            NEW.company_id <> OLD.company_id OR 
                            NEW.warehouse_id <> OLD.warehouse_id OR 
                            NEW.supplier_id <> OLD.supplier_id OR 
                            NEW.grn_number <> OLD.grn_number OR 
                            NEW.receipt_date <> OLD.receipt_date) THEN
                            RAISE EXCEPTION 'Document Immutability Violation: Core attributes of Received Goods Receipt % cannot be modified.', OLD.id;
                        END IF;
                    END IF;
                    RETURN NEW;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_goods_receipt_immutability
            BEFORE UPDATE OR DELETE ON goods_receipts
            FOR EACH ROW
            EXECUTE FUNCTION trg_fn_enforce_goods_receipt_immutability();

            -- 3. Sales Order Immutability
            CREATE OR REPLACE FUNCTION trg_fn_enforce_sales_order_immutability()
            RETURNS TRIGGER AS $$
            BEGIN
                IF (TG_OP = 'DELETE') THEN
                    IF (OLD.status IN ('confirmed', 'partially_delivered', 'delivered', 'closed')) THEN
                        RAISE EXCEPTION 'Document Immutability Violation: Confirmed Sales Order % cannot be deleted. Cancellation must occur via status update.', OLD.id;
                    END IF;
                    RETURN OLD;
                END IF;

                IF (TG_OP = 'UPDATE') THEN
                    IF (OLD.status IN ('confirmed', 'partially_delivered', 'delivered', 'closed')) THEN
                        IF (NEW.tenant_id <> OLD.tenant_id OR 
                            NEW.company_id <> OLD.company_id OR 
                            NEW.customer_id <> OLD.customer_id OR 
                            NEW.order_number <> OLD.order_number OR
                            NEW.grand_total <> OLD.grand_total OR
                            NEW.subtotal <> OLD.subtotal) THEN
                            RAISE EXCEPTION 'Document Immutability Violation: Financial attributes of Confirmed Sales Order % cannot be modified directly.', OLD.id;
                        END IF;
                    END IF;
                    RETURN NEW;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_sales_order_immutability
            BEFORE UPDATE OR DELETE ON sales_orders
            FOR EACH ROW
            EXECUTE FUNCTION trg_fn_enforce_sales_order_immutability();

            -- 4. Purchase Order Immutability
            CREATE OR REPLACE FUNCTION trg_fn_enforce_purchase_order_immutability()
            RETURNS TRIGGER AS $$
            BEGIN
                IF (TG_OP = 'DELETE') THEN
                    IF (OLD.status IN ('confirmed', 'partially_received', 'received', 'closed')) THEN
                        RAISE EXCEPTION 'Document Immutability Violation: Confirmed Purchase Order % cannot be deleted. Cancellation must occur via status update.', OLD.id;
                    END IF;
                    RETURN OLD;
                END IF;

                IF (TG_OP = 'UPDATE') THEN
                    IF (OLD.status IN ('confirmed', 'partially_received', 'received', 'closed')) THEN
                        IF (NEW.tenant_id <> OLD.tenant_id OR 
                            NEW.company_id <> OLD.company_id OR 
                            NEW.supplier_id <> OLD.supplier_id OR 
                            NEW.order_number <> OLD.order_number OR
                            NEW.grand_total <> OLD.grand_total OR
                            NEW.subtotal <> OLD.subtotal) THEN
                            RAISE EXCEPTION 'Document Immutability Violation: Financial attributes of Confirmed Purchase Order % cannot be modified directly.', OLD.id;
                        END IF;
                    END IF;
                    RETURN NEW;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_purchase_order_immutability
            BEFORE UPDATE OR DELETE ON purchase_orders
            FOR EACH ROW
            EXECUTE FUNCTION trg_fn_enforce_purchase_order_immutability();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('pgsql_owner')->unprepared("
            DROP TRIGGER IF EXISTS trg_delivery_challan_immutability ON delivery_challans;
            DROP FUNCTION IF EXISTS trg_fn_enforce_delivery_challan_immutability();

            DROP TRIGGER IF EXISTS trg_goods_receipt_immutability ON goods_receipts;
            DROP FUNCTION IF EXISTS trg_fn_enforce_goods_receipt_immutability();

            DROP TRIGGER IF EXISTS trg_sales_order_immutability ON sales_orders;
            DROP FUNCTION IF EXISTS trg_fn_enforce_sales_order_immutability();

            DROP TRIGGER IF EXISTS trg_purchase_order_immutability ON purchase_orders;
            DROP FUNCTION IF EXISTS trg_fn_enforce_purchase_order_immutability();
        ");
    }
};
