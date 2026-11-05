<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    /**
     * Run the migrations.
     * Implements P2P Supplier Quotations & Lines (REQ-PROC-03).
     */
    public function up(): void
    {
        $this->createTenantTable('supplier_quotations', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('branch_id');
            $table->uuid('supplier_id');
            $table->string('rfq_number', 100);
            $table->string('supplier_quote_reference', 100)->nullable();
            $table->date('quote_date');
            $table->date('valid_until');
            $table->string('currency', 3)->default('BDT');
            $table->decimal('exchange_rate', 18, 6)->default(1.000000);
            $table->decimal('subtotal', 18, 4)->default(0.0000);
            $table->decimal('tax_amount', 18, 4)->default(0.0000);
            $table->decimal('grand_total', 18, 4)->default(0.0000);
            $table->string('status', 30)->default('draft');
            $table->timestampTz('awarded_at')->nullable();
            $table->uuid('converted_po_id')->nullable();
            $table->uuid('created_by');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_supplier_quotations_company_id');
            $table->unique(['tenant_id', 'company_id', 'rfq_number', 'supplier_id'], 'uq_sq_rfq_per_supplier');

            $table->foreign(['tenant_id', 'company_id'], 'fk_sq_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'branch_id'], 'fk_sq_branch')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('branches')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'supplier_id'], 'fk_sq_supplier')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('suppliers')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'converted_po_id'], 'fk_sq_converted_po')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('purchase_orders')
                ->onDelete('set null');

            $table->foreign('created_by', 'fk_sq_creator')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
        });

        $this->createTenantTable('supplier_quotation_lines', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('supplier_quotation_id');
            $table->uuid('product_id');
            $table->uuid('variant_id');
            $table->decimal('requested_quantity', 18, 4);
            $table->decimal('quoted_unit_price', 18, 4);
            $table->decimal('discount_amount', 18, 4)->default(0.0000);
            $table->decimal('tax_rate', 6, 4)->default(0.0000);
            $table->decimal('tax_amount', 18, 4)->default(0.0000);
            $table->decimal('line_total', 18, 4);
            $table->integer('lead_time_days')->default(0);
            $table->boolean('is_selected')->default(false);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_sq_lines_company_id');

            $table->foreign(['tenant_id', 'company_id', 'supplier_quotation_id'], 'fk_sq_lines_quote')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('supplier_quotations')
                ->onDelete('cascade');

            $table->foreign(['tenant_id', 'company_id', 'product_id'], 'fk_sq_lines_product')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('products')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'variant_id'], 'fk_sq_lines_variant')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('product_variants')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropTenantTable('supplier_quotation_lines');
        $this->dropTenantTable('supplier_quotations');
    }
};
