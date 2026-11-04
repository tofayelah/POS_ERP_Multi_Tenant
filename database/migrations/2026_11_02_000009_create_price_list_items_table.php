<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('price_list_items', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('price_list_id');
            $table->uuid('variant_id');
            $table->decimal('unit_price', 18, 4);
            $table->decimal('min_quantity', 18, 4)->default(1.0000);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'price_list_id', 'variant_id', 'min_quantity'], 'uq_price_list_item_tier');

            $table->foreign(['tenant_id', 'company_id', 'price_list_id'], 'fk_price_items_list')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('price_lists')
                ->onDelete('cascade');

            $table->foreign(['tenant_id', 'company_id', 'variant_id'], 'fk_price_items_variant')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('product_variants')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('price_list_items');
    }
};
