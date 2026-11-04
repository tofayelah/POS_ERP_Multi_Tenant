<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends TenantMigration
{
    public function up(): void
    {
        // Ensure btree_gist extension exists for range exclusion constraint
        DB::connection('pgsql_owner')->statement('CREATE EXTENSION IF NOT EXISTS "btree_gist"');

        $this->createTenantTable('tax_code_rates', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('tax_code_id');
            $table->decimal('rate_percent', 5, 2)->default(0.00);
            $table->decimal('supplementary_duty_percent', 5, 2)->default(0.00);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestampsTz();

            $table->foreign(['tenant_id', 'company_id', 'tax_code_id'], 'fk_tax_rates_code')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('tax_codes')
                ->onDelete('restrict');
        });

        // Add btree_gist exclusion constraint preventing overlapping date periods for the same tax code (NEW-20)
        DB::connection('pgsql_owner')->statement("
            ALTER TABLE tax_code_rates
            ADD CONSTRAINT uq_no_overlapping_tax_rates EXCLUDE USING gist (
                tenant_id WITH =,
                company_id WITH =,
                tax_code_id WITH =,
                daterange(effective_from, COALESCE(effective_to, '9999-12-31'::date), '[]') WITH &&
            )
        ");
    }

    public function down(): void
    {
        $this->dropTenantTable('tax_code_rates');
    }
};
