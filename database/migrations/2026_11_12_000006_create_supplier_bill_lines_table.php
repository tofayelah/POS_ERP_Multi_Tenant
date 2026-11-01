<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    /**
     * Run the migrations.
     * Implements Section 1.3 & 8.2: Supplier Bill Lines (P2P Document Chain - NEW-34).
     */
    public function up(): void
    {
        $this->createTenantTable('supplier_bill_lines', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('supplier_bill_id');
            $table->uuid('goods_receipt_line_id')->nullable();
            $table->uuid('purchase_order_line_id')->nullable();
            $table->uuid('product_id');
            $table->uuid('variant_id');
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('subtotal', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0.0000);
            $table->decimal('total_amount', 18, 4);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_supplier_bill_lines_company_id');

            $table->foreign(['tenant_id', 'company_id', 'supplier_bill_id'], 'fk_bill_lines_bill')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('supplier_bills')
                ->onDelete('cascade');

            $table->foreign(['tenant_id', 'company_id', 'goods_receipt_line_id'], 'fk_bill_lines_grn_line')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('goods_receipt_lines')
                ->onDelete('set null');

            $table->foreign(['tenant_id', 'company_id', 'purchase_order_line_id'], 'fk_bill_lines_po_line')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('purchase_order_lines')
                ->onDelete('set null');

            $table->foreign(['tenant_id', 'company_id', 'product_id'], 'fk_bill_lines_product')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('products')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'variant_id'], 'fk_bill_lines_variant')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('product_variants')
                ->onDelete('restrict');

            $table->index(['tenant_id', 'supplier_bill_id'], 'idx_bill_lines_bill');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropTenantTable('supplier_bill_lines');
    }
};
