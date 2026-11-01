<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    /**
     * Run the migrations.
     * Implements Section 9.1 Table 17: Supplier Bills (Accounts Payable).
     */
    public function up(): void
    {
        $this->createTenantTable('supplier_bills', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('branch_id');
            $table->uuid('supplier_id');
            $table->string('bill_number', 100);
            $table->date('bill_date');
            $table->date('due_date');
            $table->decimal('subtotal', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0.0000);
            $table->decimal('total_amount', 18, 4);
            $table->string('status', 30)->default('draft');
            $table->uuid('created_by');
            $table->uuid('approved_by')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->uuid('gl_journal_entry_id')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_supplier_bills_company_id');
            $table->unique(['tenant_id', 'company_id', 'bill_number'], 'uq_supplier_bills_number_per_company');

            $table->foreign(['tenant_id', 'company_id'], 'fk_supplier_bills_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'branch_id'], 'fk_supplier_bills_branch')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('branches')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'supplier_id'], 'fk_supplier_bills_supplier')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('suppliers')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'created_by'], 'fk_supplier_bills_creator')
                ->references(['tenant_id', 'id'])
                ->on('users')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropTenantTable('supplier_bills');
    }
};
