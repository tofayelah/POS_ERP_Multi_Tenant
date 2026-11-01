<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    /**
     * Run the migrations.
     * Implements Section 9.1 Table 14: Customers.
     */
    public function up(): void
    {
        $this->createTenantTable('customers', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->string('name', 255);
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('customer_bin', 50)->nullable();
            $table->decimal('credit_limit', 18, 4)->default(0.0000);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_customers_company_id');

            $table->foreign(['tenant_id', 'company_id'], 'fk_customers_company')
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
        $this->dropTenantTable('customers');
    }
};
