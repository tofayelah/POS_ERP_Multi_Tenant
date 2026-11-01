<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Links Sales Invoices and Supplier Bills to upstream document chain entities (NEW-34).
     */
    public function up(): void
    {
        Schema::connection('pgsql_owner')->table('sales_invoices', function (Blueprint $table) {
            $table->uuid('sales_order_id')->nullable()->after('customer_id');
            $table->uuid('delivery_challan_id')->nullable()->after('sales_order_id');

            $table->foreign(['tenant_id', 'company_id', 'sales_order_id'], 'fk_invoices_sales_order')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('sales_orders')
                ->onDelete('set null');

            $table->foreign(['tenant_id', 'company_id', 'delivery_challan_id'], 'fk_invoices_delivery_challan')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('delivery_challans')
                ->onDelete('set null');
        });

        Schema::connection('pgsql_owner')->table('sales_invoice_lines', function (Blueprint $table) {
            $table->uuid('sales_order_line_id')->nullable()->after('sales_invoice_id');
            $table->uuid('delivery_challan_line_id')->nullable()->after('sales_order_line_id');

            $table->foreign(['tenant_id', 'company_id', 'sales_order_line_id'], 'fk_invoice_lines_so_line')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('sales_order_lines')
                ->onDelete('set null');

            $table->foreign(['tenant_id', 'company_id', 'delivery_challan_line_id'], 'fk_invoice_lines_dc_line')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('delivery_challan_lines')
                ->onDelete('set null');
        });

        Schema::connection('pgsql_owner')->table('supplier_bills', function (Blueprint $table) {
            $table->uuid('purchase_order_id')->nullable()->after('supplier_id');
            $table->uuid('goods_receipt_id')->nullable()->after('purchase_order_id');

            $table->foreign(['tenant_id', 'company_id', 'purchase_order_id'], 'fk_bills_purchase_order')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('purchase_orders')
                ->onDelete('set null');

            $table->foreign(['tenant_id', 'company_id', 'goods_receipt_id'], 'fk_bills_goods_receipt')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('goods_receipts')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql_owner')->table('sales_invoices', function (Blueprint $table) {
            $table->dropForeign('fk_invoices_delivery_challan');
            $table->dropForeign('fk_invoices_sales_order');
            $table->dropColumn(['delivery_challan_id', 'sales_order_id']);
        });

        Schema::connection('pgsql_owner')->table('sales_invoice_lines', function (Blueprint $table) {
            $table->dropForeign('fk_invoice_lines_dc_line');
            $table->dropForeign('fk_invoice_lines_so_line');
            $table->dropColumn(['delivery_challan_line_id', 'sales_order_line_id']);
        });

        Schema::connection('pgsql_owner')->table('supplier_bills', function (Blueprint $table) {
            $table->dropForeign('fk_bills_goods_receipt');
            $table->dropForeign('fk_bills_purchase_order');
            $table->dropColumn(['goods_receipt_id', 'purchase_order_id']);
        });
    }
};
