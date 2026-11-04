<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('branches', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->string('name', 255);
            $table->string('code', 50);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->foreign(['tenant_id', 'company_id'], 'fk_branches_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('branches');
    }
};
