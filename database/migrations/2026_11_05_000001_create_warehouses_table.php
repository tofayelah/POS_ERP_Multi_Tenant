<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('warehouses', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('branch_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_warehouses_company_id');
            $table->unique(['tenant_id', 'company_id', 'code'], 'uq_warehouses_code');

            $table->foreign(['tenant_id', 'company_id'], 'fk_warehouses_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'branch_id'], 'fk_warehouses_branch')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('branches')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('warehouses');
    }
};
