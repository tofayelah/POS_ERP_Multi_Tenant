<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('tenant_module_entitlements', function (Blueprint $table) {
            $table->string('module_identifier', 50);
            $table->boolean('is_enabled')->default(true);
            $table->string('source', 30)->default('plan');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'module_identifier'], 'uq_tenant_module_identifier');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('tenant_module_entitlements');
    }
};
