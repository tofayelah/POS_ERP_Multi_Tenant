<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends TenantMigration
{
    /**
     * Run the migrations.
     * Implements Section 9.1 Table 18: Payments (Inbound Customer Receipts & Outbound Supplier Vouchers).
     */
    public function up(): void
    {
        $this->createTenantTable('payments', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('branch_id');
            $table->string('payment_number', 100);
            $table->string('payment_type', 20); // inbound, outbound
            $table->string('payment_method', 30); // cash, bank, cheque, bkash, nagad
            $table->string('party_type', 20); // customer, supplier
            $table->uuid('party_id');
            $table->date('payment_date');
            $table->decimal('amount', 18, 4);
            $table->string('reference', 255)->nullable();
            $table->string('status', 30)->default('draft'); // draft, pending_approval, posted, cancelled, reconciled
            $table->uuid('created_by');
            $table->uuid('approved_by')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->uuid('gl_journal_entry_id')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_payments_company_id');
            $table->unique(['tenant_id', 'company_id', 'payment_number'], 'uq_payments_number_per_company');

            $table->foreign(['tenant_id', 'company_id'], 'fk_payments_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'branch_id'], 'fk_payments_branch')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('branches')
                ->onDelete('restrict');

            $table->foreign('created_by', 'fk_payments_creator')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
        });

        DB::connection('pgsql_owner')->statement("
            ALTER TABLE payments
            ADD CONSTRAINT chk_payments_amount CHECK (amount > 0),
            ADD CONSTRAINT chk_payments_type CHECK (payment_type IN ('inbound', 'outbound')),
            ADD CONSTRAINT chk_payments_method CHECK (payment_method IN ('cash', 'bank', 'cheque', 'bkash', 'nagad')),
            ADD CONSTRAINT chk_payments_party_type CHECK (party_type IN ('customer', 'supplier')),
            ADD CONSTRAINT chk_payments_status CHECK (status IN ('draft', 'pending_approval', 'posted', 'cancelled', 'reconciled'));
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropTenantTable('payments');
    }
};
