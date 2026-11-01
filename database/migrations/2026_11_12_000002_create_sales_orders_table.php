<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    /**
     * Run the migrations.
     * Implements Section 1.3 & 8.2: Sales Orders & Sales Order Lines (O2C Document Chain - NEW-34).
     */
    public function up(): void
    {
        $this->createTenantTable('sales_orders', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('branch_id');
            $table->uuid('customer_id');
            $table->uuid('quotation_id')->nullable();
            $table->string('order_number', 100);
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->decimal('subtotal', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0.0000);
            $table->decimal('grand_total', 18, 4);
            $table->string('status', 30)->default('draft');
            $table->uuid('created_by');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_sales_orders_company_id');
            $table->unique(['tenant_id', 'company_id', 'order_number'], 'uq_sales_orders_number_per_company');

            $table->foreign(['tenant_id', 'company_id'], 'fk_sales_orders_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'branch_id'], 'fk_sales_orders_branch')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('branches')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'customer_id'], 'fk_sales_orders_customer')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('customers')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'quotation_id'], 'fk_sales_orders_quotation')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('quotations')
                ->onDelete('set null');

            $table->foreign('created_by', 'fk_sales_orders_creator')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
        });

        $this->createTenantTable('sales_order_lines', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('sales_order_id');
            $table->uuid('quotation_line_id')->nullable();
            $table->uuid('product_id');
            $table->uuid('variant_id');
            $table->decimal('ordered_quantity', 18, 4);
            $table->decimal('delivered_quantity', 18, 4)->default(0.0000);
            $table->decimal('invoiced_quantity', 18, 4)->default(0.0000);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('subtotal', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0.0000);
            $table->decimal('total_amount', 18, 4);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_sales_order_lines_company_id');

            $table->foreign(['tenant_id', 'company_id', 'sales_order_id'], 'fk_so_lines_order')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('sales_orders')
                ->onDelete('cascade');

            $table->foreign(['tenant_id', 'company_id', 'quotation_line_id'], 'fk_so_lines_quotation_line')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('quotation_lines')
                ->onDelete('set null');

            $table->foreign(['tenant_id', 'company_id', 'product_id'], 'fk_so_lines_product')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('products')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'variant_id'], 'fk_so_lines_variant')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('product_variants')
                ->onDelete('restrict');

            $table->index(['tenant_id', 'sales_order_id'], 'idx_sales_order_lines_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropTenantTable('sales_order_lines');
        $this->dropTenantTable('sales_orders');
    }
};
