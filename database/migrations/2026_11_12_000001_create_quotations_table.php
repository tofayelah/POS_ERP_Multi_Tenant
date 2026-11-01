<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    /**
     * Run the migrations.
     * Implements Section 1.3 & 8.2: Quotations & Quotation Lines (O2C Document Chain - NEW-34).
     */
    public function up(): void
    {
        $this->createTenantTable('quotations', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('branch_id');
            $table->uuid('customer_id');
            $table->string('quotation_number', 100);
            $table->date('quotation_date');
            $table->date('valid_until');
            $table->decimal('subtotal', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0.0000);
            $table->decimal('grand_total', 18, 4);
            $table->string('status', 30)->default('draft');
            $table->uuid('created_by');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_quotations_company_id');
            $table->unique(['tenant_id', 'company_id', 'quotation_number'], 'uq_quotations_number_per_company');

            $table->foreign(['tenant_id', 'company_id'], 'fk_quotations_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'branch_id'], 'fk_quotations_branch')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('branches')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'customer_id'], 'fk_quotations_customer')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('customers')
                ->onDelete('restrict');

            $table->foreign('created_by', 'fk_quotations_creator')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
        });

        $this->createTenantTable('quotation_lines', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('quotation_id');
            $table->uuid('product_id');
            $table->uuid('variant_id');
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('subtotal', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0.0000);
            $table->decimal('total_amount', 18, 4);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_quotation_lines_company_id');

            $table->foreign(['tenant_id', 'company_id', 'quotation_id'], 'fk_quotation_lines_quotation')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('quotations')
                ->onDelete('cascade');

            $table->foreign(['tenant_id', 'company_id', 'product_id'], 'fk_quotation_lines_product')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('products')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'variant_id'], 'fk_quotation_lines_variant')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('product_variants')
                ->onDelete('restrict');

            $table->index(['tenant_id', 'quotation_id'], 'idx_quotation_lines_quotation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropTenantTable('quotation_lines');
        $this->dropTenantTable('quotations');
    }
};
