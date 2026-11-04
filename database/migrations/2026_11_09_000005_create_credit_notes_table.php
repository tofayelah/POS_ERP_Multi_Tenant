<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends TenantMigration
{
    /**
     * Run the migrations.
     * Implements Mushak 6.7 Credit Notes and Lines for statutory returns and reversals (NEW-23, NEW-24, TAX-03).
     */
    public function up(): void
    {
        $this->createTenantTable('credit_notes', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('branch_id');
            $table->uuid('sales_invoice_id');
            $table->uuid('customer_id')->nullable();
            $table->string('credit_note_number', 100);
            $table->date('credit_note_date');
            $table->string('reason', 255)->default('Sales Return');
            $table->string('status', 30)->default('issued');
            $table->decimal('subtotal', 18, 4);
            $table->decimal('discount_amount', 18, 4)->default(0.0000);
            $table->decimal('supplementary_duty_amount', 18, 4)->default(0.0000);
            $table->decimal('tax_amount', 18, 4)->default(0.0000);
            $table->decimal('total_amount', 18, 4);
            $table->string('mushak_6_7_number', 100)->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_credit_notes_company_id');
            $table->unique(['tenant_id', 'company_id', 'credit_note_number'], 'uq_credit_note_no_per_company');

            $table->foreign(['tenant_id', 'company_id'], 'fk_credit_notes_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'branch_id'], 'fk_credit_notes_branch')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('branches')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'sales_invoice_id'], 'fk_credit_notes_invoice')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('sales_invoices')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'customer_id'], 'fk_credit_notes_customer')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('customers')
                ->onDelete('restrict');
        });

        $this->createTenantTable('credit_note_lines', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('credit_note_id');
            $table->uuid('sales_invoice_line_id')->nullable();
            $table->uuid('product_variant_id');
            $table->string('item_description', 255);
            $table->string('uom', 50)->default('Pcs');
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('subtotal', 18, 4);
            $table->decimal('vat_rate', 5, 2);
            $table->decimal('tax_amount', 18, 4);
            $table->decimal('supplementary_duty_rate', 5, 2)->default(0.00);
            $table->decimal('supplementary_duty_amount', 18, 4)->default(0.0000);
            $table->decimal('line_total', 18, 4);
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign(['tenant_id', 'company_id', 'credit_note_id'], 'fk_cn_lines_credit_note')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('credit_notes')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'product_variant_id'], 'fk_cn_lines_variant')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('product_variants')
                ->onDelete('restrict');
        });

        DB::connection('pgsql_owner')->unprepared("
            -- Immutability guard for credit notes (Mushak 6.7 cannot be altered or deleted)
            CREATE OR REPLACE FUNCTION trg_fn_enforce_credit_notes_immutability()
            RETURNS TRIGGER AS $$
            BEGIN
                IF (TG_OP = 'DELETE') THEN
                    RAISE EXCEPTION 'Statutory Audit Violation: Credit Note % cannot be deleted.', OLD.id;
                END IF;

                IF (TG_OP = 'UPDATE') THEN
                    RAISE EXCEPTION 'Statutory Audit Violation: Credit Note % is immutable once created.', OLD.id;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_credit_notes_immutability ON credit_notes;
            CREATE TRIGGER trg_credit_notes_immutability
            BEFORE UPDATE OR DELETE ON credit_notes
            FOR EACH ROW
            EXECUTE FUNCTION trg_fn_enforce_credit_notes_immutability();

            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'app_rw') THEN
                    REVOKE TRUNCATE ON credit_notes FROM app_rw;
                    REVOKE TRUNCATE ON credit_note_lines FROM app_rw;
                END IF;
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'reporting_ro') THEN
                    REVOKE TRUNCATE ON credit_notes FROM reporting_ro;
                    REVOKE TRUNCATE ON credit_note_lines FROM reporting_ro;
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
            DROP TRIGGER IF EXISTS trg_credit_notes_immutability ON credit_notes;
            DROP FUNCTION IF EXISTS trg_fn_enforce_credit_notes_immutability();
        ');

        $this->dropTenantTable('credit_note_lines');
        $this->dropTenantTable('credit_notes');
    }
};
