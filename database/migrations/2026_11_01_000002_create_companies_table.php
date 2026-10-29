<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->createTenantTable('companies', function (Blueprint $table) {
            $table->string('legal_name', 255);
            $table->string('bin_number', 50)->nullable();
            $table->string('base_currency', 10)->default('BDT');
            $table->integer('fiscal_year_start_month')->default(7);
            $table->string('tax_regime', 50)->default('standard_vat');
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropTenantTable('companies');
    }
};
