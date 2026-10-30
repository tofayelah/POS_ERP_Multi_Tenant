<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('number_series', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('branch_id')->nullable();
            $table->string('document_type', 50);
            $table->string('prefix', 20);
            $table->string('fiscal_year', 20);
            $table->bigInteger('current_number')->default(0);
            $table->integer('padding')->default(6);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_number_series_company_id');

            $table->foreign(['tenant_id', 'company_id'], 'fk_series_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'branch_id'], 'fk_series_branch')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('branches')
                ->onDelete('restrict');
        });

        // PostgreSQL 15+ UNIQUE NULLS NOT DISTINCT constraint for gapless series
        DB::connection('pgsql_owner')->statement('
            ALTER TABLE number_series
            ADD CONSTRAINT uq_series_lookup
            UNIQUE NULLS NOT DISTINCT (tenant_id, company_id, branch_id, document_type, fiscal_year)
        ');
    }

    public function down(): void
    {
        $this->dropTenantTable('number_series');
    }
};
