<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('accounting_periods', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('fiscal_year_id');
            $table->integer('period_number');
            $table->string('period_name', 50);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 30)->default('open');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_accounting_periods_company_id');
            $table->unique(['tenant_id', 'company_id', 'fiscal_year_id', 'period_number'], 'uq_period_per_year');

            $table->foreign(['tenant_id', 'company_id'], 'fk_periods_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'fiscal_year_id'], 'fk_periods_year')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('fiscal_years')
                ->onDelete('restrict');
        });

        DB::connection('pgsql_owner')->statement("
            ALTER TABLE accounting_periods
            ADD CONSTRAINT chk_accounting_period_status
            CHECK (status IN ('open', 'soft-closed', 'closed'))
        ");
    }

    public function down(): void
    {
        $this->dropTenantTable('accounting_periods');
    }
};
