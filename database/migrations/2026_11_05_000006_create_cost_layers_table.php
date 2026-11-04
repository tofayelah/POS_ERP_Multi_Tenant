<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('cost_layers', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('warehouse_id');
            $table->uuid('stock_location_id')->nullable();
            $table->uuid('product_variant_id');
            $table->string('batch_number', 100)->nullable();
            $table->decimal('quantity_received', 18, 4);
            $table->decimal('quantity_remaining', 18, 4);
            $table->decimal('unit_cost', 18, 4);
            $table->date('receipt_date');
            $table->boolean('is_provisional')->default(false);
            $table->string('reference_type', 100);
            $table->uuid('reference_id');
            $table->timestampsTz();

            $table->foreign(['tenant_id', 'company_id'], 'fk_layers_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'warehouse_id'], 'fk_layers_warehouse')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('warehouses')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'stock_location_id'], 'fk_layers_location')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('stock_locations')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'product_variant_id'], 'fk_layers_variant')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('product_variants')
                ->onDelete('restrict');

            $table->index(['tenant_id', 'warehouse_id', 'product_variant_id', 'receipt_date', 'created_at'], 'idx_cost_layers_fifo');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('cost_layers');
    }
};
