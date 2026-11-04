<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('user_location_scopes', function (Blueprint $table) {
            $table->foreignUuid('membership_id')->constrained('tenant_memberships')->cascadeOnDelete();
            $table->uuid('company_id');
            $table->uuid('branch_id')->nullable();
            $table->timestampsTz();

            $table->foreign(['tenant_id', 'company_id'], 'fk_location_scopes_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('cascade');

            $table->foreign(['tenant_id', 'branch_id'], 'fk_location_scopes_branch')
                ->references(['tenant_id', 'id'])
                ->on('branches')
                ->onDelete('cascade');

            $table->unique(['tenant_id', 'membership_id', 'company_id', 'branch_id'], 'uq_user_location_scope');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('user_location_scopes');
    }
};
