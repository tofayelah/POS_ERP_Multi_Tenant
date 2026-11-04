<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends TenantMigration
{
    /**
     * Run the migrations.
     * Implements Section 9.1 Table 19: Subledger Open Items (AR & AP Single Source of Truth).
     */
    public function up(): void
    {
        $this->createTenantTable('subledger_open_items', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->string('party_type', 20); // customer, supplier
            $table->uuid('party_id');
            $table->string('source_type', 50); // sales_invoice, supplier_bill, credit_note, debit_note, payment
            $table->uuid('source_id');
            $table->string('document_number', 100);
            $table->date('document_date');
            $table->date('due_date');
            $table->string('direction', 10); // debit, credit
            $table->string('gl_control_account_code', 20); // 1030 (AR), 2010 (AP)
            $table->decimal('original_amount', 18, 4);
            $table->decimal('allocated_amount', 18, 4)->default(0.0000);
            $table->decimal('remaining_amount', 18, 4);
            $table->string('status', 30)->default('open'); // open, partially_allocated, fully_allocated, reversed
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_subledger_open_items_company_id');

            $table->foreign(['tenant_id', 'company_id'], 'fk_subledger_open_items_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');
        });

        DB::connection('pgsql_owner')->statement("
            ALTER TABLE subledger_open_items
            ADD CONSTRAINT chk_open_items_original_amount CHECK (original_amount > 0),
            ADD CONSTRAINT chk_open_items_allocated_amount CHECK (allocated_amount >= 0),
            ADD CONSTRAINT chk_open_items_remaining_amount CHECK (remaining_amount >= 0),
            ADD CONSTRAINT chk_open_items_allocation_ceiling CHECK (allocated_amount <= original_amount),
            ADD CONSTRAINT chk_open_items_remaining_equality CHECK (remaining_amount = original_amount - allocated_amount),
            ADD CONSTRAINT chk_open_items_direction CHECK (direction IN ('debit', 'credit')),
            ADD CONSTRAINT chk_open_items_party_type CHECK (party_type IN ('customer', 'supplier')),
            ADD CONSTRAINT chk_open_items_status CHECK (status IN ('open', 'partially_allocated', 'fully_allocated', 'reversed'));
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropTenantTable('subledger_open_items');
    }
};
