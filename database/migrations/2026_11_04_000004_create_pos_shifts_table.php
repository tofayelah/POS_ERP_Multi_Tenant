<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('pos_shifts', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('branch_id');
            $table->uuid('terminal_id');
            $table->uuid('user_id');
            $table->string('status', 30)->default('open');
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->decimal('opening_cash', 18, 4)->default(0.0000);
            $table->decimal('closing_cash', 18, 4)->nullable();
            $table->decimal('expected_cash', 18, 4)->nullable();
            $table->timestampsTz();

            $table->foreign(['tenant_id', 'company_id'], 'fk_pos_shifts_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->restrictOnDelete();

            $table->foreign(['tenant_id', 'company_id', 'branch_id'], 'fk_pos_shifts_branch')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('branches')
                ->restrictOnDelete();

            $table->foreign(['tenant_id', 'company_id', 'terminal_id'], 'fk_pos_shifts_terminal')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('pos_terminals')
                ->restrictOnDelete();

            $table->foreign(['tenant_id', 'user_id'], 'fk_pos_shifts_user')
                ->references(['tenant_id', 'user_id'])
                ->on('tenant_memberships')
                ->restrictOnDelete();
        });

        DB::connection('pgsql_owner')->statement("
            ALTER TABLE pos_shifts
            ADD CONSTRAINT chk_pos_shift_status
            CHECK (status IN ('open', 'closed'))
        ");
    }

    public function down(): void
    {
        $this->dropTenantTable('pos_shifts');
    }
};
