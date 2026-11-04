<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('stock_reservations', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('warehouse_id');
            $table->uuid('stock_location_id')->nullable();
            $table->uuid('product_variant_id');
            $table->string('batch_number', 100)->nullable();
            $table->decimal('quantity', 18, 4);
            $table->string('document_type', 50);
            $table->uuid('document_id');
            $table->timestampTz('expires_at');
            $table->string('status', 30)->default('active'); // active, consumed, expired, cancelled
            $table->timestampsTz();

            $table->foreign(['tenant_id', 'company_id'], 'fk_reservations_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'warehouse_id'], 'fk_reservations_warehouse')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('warehouses')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'stock_location_id'], 'fk_reservations_location')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('stock_locations')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'product_variant_id'], 'fk_reservations_variant')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('product_variants')
                ->onDelete('restrict');

            $table->index(['tenant_id', 'status', 'expires_at'], 'idx_reservations_expiry');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('stock_reservations');
    }
};
