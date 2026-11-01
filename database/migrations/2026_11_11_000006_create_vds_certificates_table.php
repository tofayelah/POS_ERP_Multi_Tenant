<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends TenantMigration
{
    /**
     * Run the migrations.
     * Implements Section 9.1 Table 21: Mushak 6.6 VDS Withholding Certificates.
     */
    public function up(): void
    {
        $this->createTenantTable('vds_certificates', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('branch_id');
            $table->string('certificate_number', 100);
            $table->string('direction', 30); // received_from_customer, issued_to_supplier
            $table->uuid('customer_id')->nullable();
            $table->uuid('supplier_id')->nullable();
            $table->string('withholding_entity_name', 255);
            $table->string('withholding_entity_bin', 50);
            $table->date('issue_date');
            $table->string('tax_period', 20);
            $table->decimal('total_invoice_amount', 18, 4);
            $table->decimal('total_vat_amount', 18, 4);
            $table->decimal('vds_amount', 18, 4);
            $table->uuid('sales_invoice_id')->nullable();
            $table->uuid('supplier_bill_id')->nullable();
            $table->uuid('payment_id')->nullable();
            $table->string('status', 20)->default('valid'); // valid, cancelled
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_vds_certificates_company_id');

            $table->foreign(['tenant_id', 'company_id'], 'fk_vds_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'branch_id'], 'fk_vds_branch')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('branches')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'customer_id'], 'fk_vds_customer')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('customers')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'supplier_id'], 'fk_vds_supplier')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('suppliers')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'sales_invoice_id'], 'fk_vds_sales_invoice')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('sales_invoices')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'supplier_bill_id'], 'fk_vds_supplier_bill')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('supplier_bills')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'payment_id'], 'fk_vds_payment')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('payments')
                ->onDelete('restrict');
        });

        DB::connection('pgsql_owner')->statement("
            ALTER TABLE vds_certificates
            ADD CONSTRAINT chk_vds_amount CHECK (vds_amount > 0),
            ADD CONSTRAINT chk_vds_direction CHECK (direction IN ('received_from_customer', 'issued_to_supplier')),
            ADD CONSTRAINT chk_vds_status CHECK (status IN ('valid', 'cancelled'));
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropTenantTable('vds_certificates');
    }
};
