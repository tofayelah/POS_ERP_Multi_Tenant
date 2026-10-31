<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('landed_costs', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->string('cost_type', 50);
            $table->decimal('amount', 18, 4);
            $table->string('allocation_method', 50)->default('by_quantity');
            $table->string('reference_type', 100)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestampTz('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_landed_costs_company_id');

            $table->foreign(['tenant_id', 'company_id'], 'fk_landed_costs_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');
        });

        $this->createTenantTable('landed_cost_allocations', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('landed_cost_id');
            $table->uuid('product_variant_id');
            $table->uuid('warehouse_id');
            $table->uuid('stock_location_id')->nullable();
            $table->string('batch_number', 100)->nullable();
            $table->decimal('allocated_amount', 18, 4);
            $table->decimal('adjustment_to_inventory', 18, 4)->default(0.0000);
            $table->decimal('adjustment_to_cogs', 18, 4)->default(0.0000);
            $table->boolean('is_posted')->default(false);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_lc_allocations_company_id');

            $table->foreign(['tenant_id', 'company_id'], 'fk_lc_alloc_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'landed_cost_id'], 'fk_lc_alloc_landed_cost')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('landed_costs')
                ->onDelete('cascade');

            $table->foreign(['tenant_id', 'company_id', 'product_variant_id'], 'fk_lc_alloc_variant')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('product_variants')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'warehouse_id'], 'fk_lc_alloc_warehouse')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('warehouses')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('landed_cost_allocations');
        $this->dropTenantTable('landed_costs');
    }
};
