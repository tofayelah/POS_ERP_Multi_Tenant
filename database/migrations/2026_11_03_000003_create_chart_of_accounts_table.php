<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('chart_of_accounts', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->string('account_code', 50);
            $table->string('account_name', 255);
            $table->string('account_name_bn', 255)->nullable();
            $table->string('account_type', 50);
            $table->uuid('parent_id')->nullable();
            $table->boolean('is_reconcilable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_coa_company_id');
            $table->unique(['tenant_id', 'company_id', 'account_code'], 'uq_coa_code_per_company');

            $table->foreign(['tenant_id', 'company_id'], 'fk_coa_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'parent_id'], 'fk_coa_parent')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('chart_of_accounts')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('chart_of_accounts');
    }
};
