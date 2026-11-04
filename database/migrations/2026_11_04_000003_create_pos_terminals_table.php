<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('pos_terminals', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('branch_id');
            $table->string('terminal_code', 50);
            $table->string('name', 100);
            $table->string('device_token_hash', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_pos_terminals_company_id');
            $table->unique(['tenant_id', 'company_id', 'branch_id', 'terminal_code'], 'uq_terminal_code_per_branch');

            $table->foreign(['tenant_id', 'company_id'], 'fk_pos_terminals_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->restrictOnDelete();

            $table->foreign(['tenant_id', 'company_id', 'branch_id'], 'fk_pos_terminals_branch')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('branches')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('pos_terminals');
    }
};
