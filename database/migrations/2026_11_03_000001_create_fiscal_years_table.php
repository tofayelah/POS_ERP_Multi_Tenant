<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('fiscal_years', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->string('title', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_closed')->default(false);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_fiscal_years_company_id');
            $table->unique(['tenant_id', 'company_id', 'start_date', 'end_date'], 'uq_fiscal_year_dates');

            $table->foreign(['tenant_id', 'company_id'], 'fk_fiscal_years_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('fiscal_years');
    }
};
