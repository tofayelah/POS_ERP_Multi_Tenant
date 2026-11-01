<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    /**
     * Run the migrations.
     * Implements Section 1.3 & 8.2: Delivery Challans & Challan Lines (O2C Document Chain - NEW-34).
     */
    public function up(): void
    {
        $this->createTenantTable('delivery_challans', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('branch_id');
            $table->uuid('warehouse_id');
            $table->uuid('customer_id');
            $table->uuid('sales_order_id')->nullable();
            $table->string('challan_number', 100);
            $table->date('challan_date');
            $table->string('status', 30)->default('draft');
            $table->string('vehicle_number', 50)->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->string('driver_phone', 50)->nullable();
            $table->uuid('created_by');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_delivery_challans_company_id');
            $table->unique(['tenant_id', 'company_id', 'challan_number'], 'uq_challans_number_per_company');

            $table->foreign(['tenant_id', 'company_id'], 'fk_challans_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'branch_id'], 'fk_challans_branch')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('branches')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'warehouse_id'], 'fk_challans_warehouse')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('warehouses')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'customer_id'], 'fk_challans_customer')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('customers')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'sales_order_id'], 'fk_challans_sales_order')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('sales_orders')
                ->onDelete('set null');

            $table->foreign('created_by', 'fk_challans_creator')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
        });

        $this->createTenantTable('delivery_challan_lines', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('delivery_challan_id');
            $table->uuid('sales_order_line_id')->nullable();
            $table->uuid('product_id');
            $table->uuid('variant_id');
            $table->decimal('quantity', 18, 4);
            $table->decimal('invoiced_quantity', 18, 4)->default(0.0000);
            $table->string('batch_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_delivery_challan_lines_company_id');

            $table->foreign(['tenant_id', 'company_id', 'delivery_challan_id'], 'fk_dc_lines_challan')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('delivery_challans')
                ->onDelete('cascade');

            $table->foreign(['tenant_id', 'company_id', 'sales_order_line_id'], 'fk_dc_lines_so_line')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('sales_order_lines')
                ->onDelete('set null');

            $table->foreign(['tenant_id', 'company_id', 'product_id'], 'fk_dc_lines_product')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('products')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'variant_id'], 'fk_dc_lines_variant')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('product_variants')
                ->onDelete('restrict');

            $table->index(['tenant_id', 'delivery_challan_id'], 'idx_delivery_challan_lines_challan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropTenantTable('delivery_challan_lines');
        $this->dropTenantTable('delivery_challans');
    }
};
