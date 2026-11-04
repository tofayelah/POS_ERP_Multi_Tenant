<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    /**
     * Run the migrations.
     * Implements Section 9.1 Tables 22 & 23: Bank Statements & Bank Statement Lines.
     */
    public function up(): void
    {
        $this->createTenantTable('bank_statements', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('chart_of_account_id');
            $table->date('statement_date');
            $table->decimal('opening_balance', 18, 4);
            $table->decimal('closing_balance', 18, 4);
            $table->string('status', 20)->default('pending'); // pending, reconciled
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_bank_statements_company_id');

            $table->foreign(['tenant_id', 'company_id'], 'fk_bank_statements_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'chart_of_account_id'], 'fk_bank_statements_coa')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('chart_of_accounts')
                ->onDelete('restrict');
        });

        $this->createTenantTable('bank_statement_lines', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('bank_statement_id');
            $table->date('line_date');
            $table->string('description', 255);
            $table->string('reference_number', 100)->nullable();
            $table->decimal('deposit_amount', 18, 4)->default(0.0000);
            $table->decimal('withdrawal_amount', 18, 4)->default(0.0000);
            $table->decimal('balance', 18, 4);
            $table->boolean('is_reconciled')->default(false);
            $table->uuid('matched_payment_id')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_bank_statement_lines_company_id');

            $table->foreign(['tenant_id', 'company_id'], 'fk_bank_lines_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'bank_statement_id'], 'fk_bank_lines_statement')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('bank_statements')
                ->onDelete('cascade');

            $table->foreign(['tenant_id', 'company_id', 'matched_payment_id'], 'fk_bank_lines_payment')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('payments')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropTenantTable('bank_statement_lines');
        $this->dropTenantTable('bank_statements');
    }
};
