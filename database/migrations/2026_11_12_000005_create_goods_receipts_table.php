<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    /**
     * Run the migrations.
     * Implements Section 1.3 & 8.2: Goods Receipts (GRN) & Lines (P2P Document Chain - NEW-34).
     */
    public function up(): void
    {
        $this->createTenantTable('goods_receipts', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('branch_id');
            $table->uuid('warehouse_id');
            $table->uuid('supplier_id');
            $table->uuid('purchase_order_id')->nullable();
            $table->string('grn_number', 100);
            $table->date('receipt_date');
            $table->string('challan_number', 100)->nullable();
            $table->string('status', 30)->default('draft');
            $table->uuid('created_by');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_goods_receipts_company_id');
            $table->unique(['tenant_id', 'company_id', 'grn_number'], 'uq_grn_number_per_company');

            $table->foreign(['tenant_id', 'company_id'], 'fk_grn_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'branch_id'], 'fk_grn_branch')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('branches')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'warehouse_id'], 'fk_grn_warehouse')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('warehouses')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'supplier_id'], 'fk_grn_supplier')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('suppliers')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'purchase_order_id'], 'fk_grn_purchase_order')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('purchase_orders')
                ->onDelete('set null');

            $table->foreign('created_by', 'fk_grn_creator')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
        });

        $this->createTenantTable('goods_receipt_lines', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('goods_receipt_id');
            $table->uuid('purchase_order_line_id')->nullable();
            $table->uuid('product_id');
            $table->uuid('variant_id');
            $table->decimal('received_quantity', 18, 4);
            $table->decimal('billed_quantity', 18, 4)->default(0.0000);
            $table->decimal('unit_cost', 18, 4);
            $table->string('batch_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_goods_receipt_lines_company_id');

            $table->foreign(['tenant_id', 'company_id', 'goods_receipt_id'], 'fk_grn_lines_receipt')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('goods_receipts')
                ->onDelete('cascade');

            $table->foreign(['tenant_id', 'company_id', 'purchase_order_line_id'], 'fk_grn_lines_po_line')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('purchase_order_lines')
                ->onDelete('set null');

            $table->foreign(['tenant_id', 'company_id', 'product_id'], 'fk_grn_lines_product')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('products')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'variant_id'], 'fk_grn_lines_variant')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('product_variants')
                ->onDelete('restrict');

            $table->index(['tenant_id', 'goods_receipt_id'], 'idx_goods_receipt_lines_receipt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropTenantTable('goods_receipt_lines');
        $this->dropTenantTable('goods_receipts');
    }
};
