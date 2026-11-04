<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('journal_entries', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('period_id');
            $table->string('entry_number', 100);
            $table->date('posting_date');
            $table->date('business_date');
            $table->string('reference_type', 100);
            $table->uuid('reference_id')->nullable();
            $table->text('narration')->nullable();
            $table->boolean('is_posted')->default(false);
            $table->boolean('is_reversal')->default(false);
            $table->uuid('reversed_entry_id')->nullable();
            $table->uuid('posted_by');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_journal_entries_company_id');
            $table->unique(['tenant_id', 'company_id', 'entry_number'], 'uq_journal_no_per_company');

            $table->foreign(['tenant_id', 'company_id'], 'fk_journal_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'period_id'], 'fk_journal_period')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('accounting_periods')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'reversed_entry_id'], 'fk_journal_reversal')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('journal_entries')
                ->onDelete('restrict');
        });

        // Unique Reversal Protection: An entry can be reversed at most once (Resolving NEW-21)
        DB::connection('pgsql_owner')->statement('
            CREATE UNIQUE INDEX uq_unique_active_reversal ON journal_entries (tenant_id, reversed_entry_id)
            WHERE reversed_entry_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        $this->dropTenantTable('journal_entries');
    }
};
