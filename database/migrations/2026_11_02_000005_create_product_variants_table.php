<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('product_variants', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('product_id');
            $table->uuid('tax_code_id');
            $table->string('sku', 100);
            $table->string('name_en', 255);
            $table->string('name_bn', 255)->nullable();
            $table->string('base_uom', 50)->default('Pcs');
            $table->decimal('mrp', 18, 4)->default(0.0000);
            $table->decimal('cost_price', 18, 4)->default(0.0000);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_product_variants_company_id');
            $table->unique(['tenant_id', 'company_id', 'sku'], 'uq_variant_sku_per_company');

            $table->foreign(['tenant_id', 'company_id', 'product_id'], 'fk_variants_product')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('products')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'tax_code_id'], 'fk_variants_tax_code')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('tax_codes')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('product_variants');
    }
};
