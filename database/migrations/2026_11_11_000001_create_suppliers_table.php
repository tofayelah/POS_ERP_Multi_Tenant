<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    /**
     * Run the migrations.
     * Implements Section 9.1 Table 16: Suppliers.
     */
    public function up(): void
    {
        $this->createTenantTable('suppliers', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->string('name', 255);
            $table->string('code', 50)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('supplier_bin', 50)->nullable();
            $table->integer('credit_period_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_suppliers_company_id');
            $table->unique(['tenant_id', 'company_id', 'name'], 'uq_suppliers_name_per_company');

            $table->foreign(['tenant_id', 'company_id'], 'fk_suppliers_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropTenantTable('suppliers');
    }
};
