<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('categories', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('parent_id')->nullable();
            $table->string('code', 50);
            $table->string('name_en', 255);
            $table->string('name_bn', 255)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_categories_company_id');
            $table->unique(['tenant_id', 'company_id', 'code'], 'uq_category_code_per_company');

            $table->foreign(['tenant_id', 'company_id'], 'fk_categories_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'parent_id'], 'fk_categories_parent')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('categories')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('categories');
    }
};
