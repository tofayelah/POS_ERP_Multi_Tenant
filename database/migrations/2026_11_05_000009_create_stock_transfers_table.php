<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('stock_transfers', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->string('transfer_number', 100);
            $table->uuid('source_warehouse_id');
            $table->uuid('dest_warehouse_id');
            $table->string('status', 30)->default('draft');
            $table->timestampTz('shipped_at')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->string('vehicle_details', 255)->nullable();
            $table->string('driver_phone', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_stock_transfers_company_id');
            $table->unique(['tenant_id', 'company_id', 'transfer_number'], 'uq_stock_transfers_number');

            $table->foreign(['tenant_id', 'company_id'], 'fk_transfers_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'source_warehouse_id'], 'fk_transfers_source_wh')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('warehouses')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'dest_warehouse_id'], 'fk_transfers_dest_wh')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('warehouses')
                ->onDelete('restrict');
        });

        $this->createTenantTable('stock_transfer_lines', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('transfer_id');
            $table->uuid('product_variant_id');
            $table->uuid('stock_location_id')->nullable();
            $table->uuid('dest_stock_location_id')->nullable();
            $table->string('batch_number', 100)->nullable();
            $table->decimal('quantity_shipped', 18, 4);
            $table->decimal('quantity_received', 18, 4)->default(0.0000);
            $table->decimal('discrepancy_quantity', 18, 4)->default(0.0000);
            $table->decimal('unit_cost', 18, 4)->default(0.0000);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_transfer_lines_company_id');

            $table->foreign(['tenant_id', 'company_id'], 'fk_transfer_lines_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'transfer_id'], 'fk_transfer_lines_transfer')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('stock_transfers')
                ->onDelete('cascade');

            $table->foreign(['tenant_id', 'company_id', 'product_variant_id'], 'fk_transfer_lines_variant')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('product_variants')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('stock_transfer_lines');
        $this->dropTenantTable('stock_transfers');
    }
};
