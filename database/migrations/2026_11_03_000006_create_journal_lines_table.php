<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('journal_lines', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('journal_entry_id');
            $table->uuid('account_id');
            $table->decimal('debit_amount', 18, 4)->default(0.0000);
            $table->decimal('credit_amount', 18, 4)->default(0.0000);
            $table->string('currency', 10)->default('BDT');
            $table->decimal('exchange_rate', 18, 6)->default(1.000000);
            $table->decimal('base_debit_amount', 18, 4)->default(0.0000);
            $table->decimal('base_credit_amount', 18, 4)->default(0.0000);
            $table->uuid('partner_id')->nullable();
            $table->uuid('tax_code_id')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->string('line_description', 255)->nullable();
            $table->timestampsTz();

            $table->foreign(['tenant_id', 'company_id', 'journal_entry_id'], 'fk_lines_entry')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('journal_entries')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'account_id'], 'fk_lines_account')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('chart_of_accounts')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'tax_code_id'], 'fk_lines_tax_code')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('tax_codes')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'branch_id'], 'fk_lines_branch')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('branches')
                ->onDelete('restrict');
        });

        // Add check constraint debit > 0 xor credit > 0
        \Illuminate\Support\Facades\DB::connection('pgsql_owner')->statement('
            ALTER TABLE journal_lines
            ADD CONSTRAINT chk_debit_or_credit
            CHECK (
                (debit_amount > 0 AND credit_amount = 0) OR
                (credit_amount > 0 AND debit_amount = 0)
            )
        ');
    }

    public function down(): void
    {
        $this->dropTenantTable('journal_lines');
    }
};
