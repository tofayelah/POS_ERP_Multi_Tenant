<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('stock_locations', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('warehouse_id');
            $table->uuid('parent_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->string('location_type', 50)->default('picking');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_stock_locations_company_id');
            $table->unique(['tenant_id', 'warehouse_id', 'code'], 'uq_stock_locations_code');

            $table->foreign(['tenant_id', 'company_id'], 'fk_stock_locations_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'warehouse_id'], 'fk_stock_locations_warehouse')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('warehouses')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'parent_id'], 'fk_stock_locations_parent')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('stock_locations')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('stock_locations');
    }
};
