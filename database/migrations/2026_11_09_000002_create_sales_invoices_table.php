<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends TenantMigration
{
    /**
     * Run the migrations.
     * Implements Section 9.1 Table 15: Sales Invoices (as written in design with DEFAULT 'issued').
     */
    public function up(): void
    {
        $this->createTenantTable('sales_invoices', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('branch_id');
            $table->uuid('terminal_id')->nullable();
            $table->string('client_txn_id', 100)->nullable();
            $table->string('invoice_number', 100);
            $table->string('sdc_invoice_number', 100)->nullable();
            $table->date('invoice_date');
            $table->date('business_date');
            $table->uuid('customer_id')->nullable();
            $table->string('status', 30)->default('issued');
            $table->decimal('subtotal', 18, 4);
            $table->decimal('discount_amount', 18, 4)->default(0.0000);
            $table->decimal('supplementary_duty_amount', 18, 4)->default(0.0000);
            $table->decimal('tax_amount', 18, 4)->default(0.0000);
            $table->decimal('total_payable', 18, 4);
            $table->string('payment_status', 50)->default('paid');
            $table->text('fiscal_signature')->nullable();
            $table->boolean('is_synced_from_offline')->default(false);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_sales_invoices_company_id');
            $table->unique(['tenant_id', 'company_id', 'invoice_number'], 'uq_invoice_no_per_company');
            $table->unique(['tenant_id', 'terminal_id', 'client_txn_id'], 'uq_pos_offline_idempotency');

            $table->foreign(['tenant_id', 'company_id'], 'fk_invoices_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'branch_id'], 'fk_invoices_branch')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('branches')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'terminal_id'], 'fk_invoices_terminal')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('pos_terminals')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'customer_id'], 'fk_invoices_customer')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('customers')
                ->onDelete('restrict');
        });

        DB::connection('pgsql_owner')->statement("
            ALTER TABLE sales_invoices
            ADD CONSTRAINT chk_terminal_and_client_txn CHECK (
                (terminal_id IS NULL AND client_txn_id IS NULL) OR
                (terminal_id IS NOT NULL AND client_txn_id IS NOT NULL)
            );
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropTenantTable('sales_invoices');
    }
};
