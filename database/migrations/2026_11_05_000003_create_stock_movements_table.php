<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('stock_movements', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('warehouse_id');
            $table->uuid('stock_location_id')->nullable();
            $table->uuid('product_variant_id');
            $table->string('batch_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('movement_type', 50);
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4)->default(0.0000);
            $table->decimal('total_cost', 18, 4)->default(0.0000);
            $table->string('reference_type', 100);
            $table->uuid('reference_id');
            $table->timestampTz('movement_date')->useCurrent();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign(['tenant_id', 'company_id'], 'fk_movements_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'warehouse_id'], 'fk_movements_warehouse')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('warehouses')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'stock_location_id'], 'fk_movements_location')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('stock_locations')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'product_variant_id'], 'fk_movements_variant')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('product_variants')
                ->onDelete('restrict');

            $table->index(['tenant_id', 'warehouse_id', 'product_variant_id', 'created_at'], 'idx_movements_lookup');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('stock_movements');
    }
};
