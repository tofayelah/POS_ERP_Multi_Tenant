<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('tax_codes', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->string('code', 50);
            $table->string('name_en', 255);
            $table->string('name_bn', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_tax_codes_company_id');
            $table->unique(['tenant_id', 'company_id', 'code'], 'uq_tax_code_per_company');

            $table->foreign(['tenant_id', 'company_id'], 'fk_tax_codes_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('tax_codes');
    }
};
