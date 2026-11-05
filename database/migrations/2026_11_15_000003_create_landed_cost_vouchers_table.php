<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('landed_cost_vouchers', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('branch_id');
            $table->string('voucher_number', 50);
            $table->date('voucher_date');
            $table->string('currency', 3)->default('BDT');
            $table->decimal('exchange_rate', 18, 6)->default(1.000000);
            $table->decimal('total_cost', 18, 4)->default(0.0000);
            $table->string('allocation_method', 50)->default('by_value');
            $table->string('status', 30)->default('draft');
            $table->uuid('created_by')->nullable();
            $table->uuid('posted_by')->nullable();
            $table->timestampTz('posted_at')->nullable();
            $table->uuid('gl_journal_entry_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_landed_cost_vouchers_company_id');
            $table->unique(['tenant_id', 'company_id', 'voucher_number'], 'uq_landed_cost_vouchers_number');

            $table->foreign(['tenant_id', 'company_id'], 'fk_lcv_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'branch_id'], 'fk_lcv_branch')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('branches')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'created_by'], 'fk_lcv_creator')
                ->references(['tenant_id', 'id'])
                ->on('users')
                ->onDelete('set null');

            $table->foreign(['tenant_id', 'posted_by'], 'fk_lcv_poster')
                ->references(['tenant_id', 'id'])
                ->on('users')
                ->onDelete('set null');

            $table->foreign(['tenant_id', 'company_id', 'gl_journal_entry_id'], 'fk_lcv_journal')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('journal_entries')
                ->onDelete('set null');
        });

        $this->createTenantTable('landed_cost_voucher_receipts', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('landed_cost_voucher_id');
            $table->uuid('goods_receipt_id');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_lcv_receipts_company_id');
            $table->unique(['tenant_id', 'company_id', 'landed_cost_voucher_id', 'goods_receipt_id'], 'uq_lcv_receipts_pair');

            $table->foreign(['tenant_id', 'company_id'], 'fk_lcv_receipts_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'landed_cost_voucher_id'], 'fk_lcv_receipts_voucher')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('landed_cost_vouchers')
                ->onDelete('cascade');

            $table->foreign(['tenant_id', 'company_id', 'goods_receipt_id'], 'fk_lcv_receipts_grn')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('goods_receipts')
                ->onDelete('restrict');
        });

        $this->createTenantTable('landed_cost_voucher_lines', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('landed_cost_voucher_id');
            $table->string('expense_type', 50);
            $table->string('description', 255);
            $table->decimal('amount', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0.0000);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_lcv_lines_company_id');

            $table->foreign(['tenant_id', 'company_id'], 'fk_lcv_lines_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'landed_cost_voucher_id'], 'fk_lcv_lines_voucher')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('landed_cost_vouchers')
                ->onDelete('cascade');
        });

        $this->createTenantTable('landed_cost_voucher_allocations', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('landed_cost_voucher_id');
            $table->uuid('goods_receipt_id');
            $table->uuid('goods_receipt_line_id');
            $table->uuid('product_id');
            $table->uuid('product_variant_id');
            $table->uuid('warehouse_id');
            $table->string('batch_number', 100)->nullable();
            $table->decimal('allocated_amount', 18, 4);
            $table->decimal('additional_unit_cost', 18, 4)->default(0.0000);
            $table->decimal('adjustment_to_inventory', 18, 4)->default(0.0000);
            $table->decimal('adjustment_to_cogs', 18, 4)->default(0.0000);
            $table->boolean('is_posted')->default(false);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_lcv_alloc_company_id');

            $table->foreign(['tenant_id', 'company_id'], 'fk_lcv_alloc_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'landed_cost_voucher_id'], 'fk_lcv_alloc_voucher')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('landed_cost_vouchers')
                ->onDelete('cascade');

            $table->foreign(['tenant_id', 'company_id', 'goods_receipt_id'], 'fk_lcv_alloc_grn')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('goods_receipts')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'goods_receipt_line_id'], 'fk_lcv_alloc_grn_line')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('goods_receipt_lines')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'product_variant_id'], 'fk_lcv_alloc_variant')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('product_variants')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'warehouse_id'], 'fk_lcv_alloc_warehouse')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('warehouses')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('landed_cost_voucher_allocations');
        $this->dropTenantTable('landed_cost_voucher_lines');
        $this->dropTenantTable('landed_cost_voucher_receipts');
        $this->dropTenantTable('landed_cost_vouchers');
    }
};
