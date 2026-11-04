<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('products', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('category_id')->nullable();
            $table->string('code', 50);
            $table->string('name_en', 255);
            $table->string('name_bn', 255)->nullable();
            $table->string('base_uom', 50)->default('Pcs');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_products_company_id');
            $table->unique(['tenant_id', 'company_id', 'code'], 'uq_product_code_per_company');

            $table->foreign(['tenant_id', 'company_id'], 'fk_products_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'category_id'], 'fk_products_category')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('categories')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('products');
    }
};
