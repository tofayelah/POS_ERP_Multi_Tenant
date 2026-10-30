<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('price_lists', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->string('code', 50);
            $table->string('name_en', 255);
            $table->string('name_bn', 255)->nullable();
            $table->string('currency', 3)->default('BDT');
            $table->boolean('is_tax_inclusive')->default(false);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_price_lists_company_id');
            $table->unique(['tenant_id', 'company_id', 'code'], 'uq_price_list_code_per_company');

            $table->foreign(['tenant_id', 'company_id'], 'fk_price_lists_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('price_lists');
    }
};
