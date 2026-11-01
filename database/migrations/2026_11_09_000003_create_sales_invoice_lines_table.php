<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    /**
     * Run the migrations.
     * Implements Section 9.1 Table 16: Sales Invoice Lines (Statutory Mushak-6.3 Breakdown - Resolving NEW-22, NEW-23, NEW-24).
     */
    public function up(): void
    {
        $this->createTenantTable('sales_invoice_lines', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('sales_invoice_id');
            $table->uuid('product_variant_id');
            $table->string('item_description', 255);
            $table->string('uom', 50);
            $table->string('batch_number', 100)->nullable();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->string('pricing_basis', 30)->default('tax_inclusive');
            $table->decimal('line_discount_amount', 18, 4)->default(0.0000);
            $table->uuid('tax_code_id');
            $table->decimal('vat_rate', 5, 2);
            $table->decimal('supplementary_duty_rate', 5, 2)->default(0.00);
            $table->decimal('supplementary_duty_amount', 18, 4)->default(0.0000);
            $table->decimal('tax_amount', 18, 4);
            $table->decimal('line_total', 18, 4);
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign(['tenant_id', 'company_id', 'sales_invoice_id'], 'fk_invoice_lines_invoice')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('sales_invoices')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'product_variant_id'], 'fk_invoice_lines_variant')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('product_variants')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'tax_code_id'], 'fk_invoice_lines_tax_code')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('tax_codes')
                ->onDelete('restrict');

            $table->index(['tenant_id', 'sales_invoice_id'], 'idx_lines_sales_invoice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropTenantTable('sales_invoice_lines');
    }
};
