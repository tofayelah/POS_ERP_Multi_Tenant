<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends TenantMigration
{
    /**
     * Run the migrations.
     * Implements Section 9.1 Table 20: Payment Allocations (Multi-Invoice, Partial, and Credit Note Settlements).
     */
    public function up(): void
    {
        $this->createTenantTable('payment_allocations', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('payment_id')->nullable();
            $table->uuid('credit_note_id')->nullable();
            $table->uuid('open_item_id');
            $table->decimal('allocated_amount', 18, 4);
            $table->date('allocation_date');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_payment_allocations_company_id');

            $table->foreign(['tenant_id', 'company_id'], 'fk_allocations_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'payment_id'], 'fk_allocations_payment')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('payments')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'credit_note_id'], 'fk_allocations_credit_note')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('credit_notes')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'open_item_id'], 'fk_allocations_open_item')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('subledger_open_items')
                ->onDelete('restrict');
        });

        DB::connection('pgsql_owner')->statement('
            ALTER TABLE payment_allocations
            ADD CONSTRAINT chk_allocations_amount CHECK (allocated_amount > 0),
            ADD CONSTRAINT chk_allocations_source CHECK (
                (payment_id IS NOT NULL AND credit_note_id IS NULL) OR
                (payment_id IS NULL AND credit_note_id IS NOT NULL)
            );
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropTenantTable('payment_allocations');
    }
};
