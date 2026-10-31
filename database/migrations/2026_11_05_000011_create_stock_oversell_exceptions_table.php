<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('stock_oversell_exceptions', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('branch_id')->nullable();
            $table->uuid('warehouse_id');
            $table->uuid('stock_location_id')->nullable();
            $table->uuid('product_variant_id');
            $table->decimal('quantity_oversold', 18, 4);
            $table->uuid('sales_invoice_id')->nullable();
            $table->decimal('cost_basis_used', 18, 4)->default(0.0000);
            $table->string('status', 50)->default('OPEN');
            $table->timestampTz('resolved_at')->nullable();
            $table->string('resolved_by_document_type', 100)->nullable();
            $table->uuid('resolved_by_document_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_oversell_exc_company_id');

            $table->foreign(['tenant_id', 'company_id'], 'fk_oversell_exc_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'warehouse_id'], 'fk_oversell_exc_warehouse')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('warehouses')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'product_variant_id'], 'fk_oversell_exc_variant')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('product_variants')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('stock_oversell_exceptions');
    }
};
