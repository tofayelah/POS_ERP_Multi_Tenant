<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubledgerSchemaTenantIsolationTest extends TestCase
{
    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected Company $companyA;

    protected Company $companyB;

    protected Branch $branchA;

    protected Branch $branchB;

    protected User $userA;

    protected User $userB;

    protected ChartOfAccount $bankAccountA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Subledger Tenant A',
            'subdomain' => 'subl-a-'.Str::random(6),
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Subledger Tenant B',
            'subdomain' => 'subl-b-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenantA);

        $this->companyA = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'legal_name' => 'Subledger Company A',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branchA = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'name' => 'Dhanmondi Branch A',
            'code' => 'DHA-01',
        ]);

        $this->userA = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Accountant A',
            'email' => 'acct-a-'.Str::random(6).'@example.com',
            'password' => 'password',
        ]);

        $this->bankAccountA = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'account_code' => '1020',
            'account_name' => 'Bank Current Account A',
            'account_type' => 'asset',
        ]);

        TenantContext::set($this->tenantB);

        $this->companyB = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'legal_name' => 'Subledger Company B',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branchB = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'company_id' => $this->companyB->id,
            'name' => 'Gulshan Branch B',
            'code' => 'GUL-01',
        ]);

        $this->userB = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Accountant B',
            'email' => 'acct-b-'.Str::random(6).'@example.com',
            'password' => 'password',
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_subledger_tables_isolate_tenant_records_completely(): void
    {
        TenantContext::set($this->tenantA);

        $supplierId = (string) Str::uuid();
        DB::table('suppliers')->insert([
            'id' => $supplierId,
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'name' => 'Rahim Brothers Ltd',
            'code' => 'SUP-001',
            'phone' => '+8801700000001',
            'email' => 'rahim@example.com',
            'supplier_bin' => '1234567890123',
            'credit_period_days' => 30,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $billId = (string) Str::uuid();
        DB::table('supplier_bills')->insert([
            'id' => $billId,
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'supplier_id' => $supplierId,
            'bill_number' => 'BILL-2026-0001',
            'bill_date' => '2026-11-01',
            'due_date' => '2026-12-01',
            'subtotal' => '50000.0000',
            'tax_amount' => '7500.0000',
            'total_amount' => '57500.0000',
            'status' => 'draft',
            'created_by' => $this->userA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentId = (string) Str::uuid();
        DB::table('payments')->insert([
            'id' => $paymentId,
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'payment_number' => 'PAY-2026-0001',
            'payment_type' => 'outbound',
            'payment_method' => 'bank',
            'party_type' => 'supplier',
            'party_id' => $supplierId,
            'payment_date' => '2026-11-02',
            'amount' => '20000.0000',
            'reference' => 'CHQ-987654',
            'status' => 'draft',
            'created_by' => $this->userA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $openItemId = (string) Str::uuid();
        DB::table('subledger_open_items')->insert([
            'id' => $openItemId,
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'party_type' => 'supplier',
            'party_id' => $supplierId,
            'source_type' => 'supplier_bill',
            'source_id' => $billId,
            'document_number' => 'BILL-2026-0001',
            'document_date' => '2026-11-01',
            'due_date' => '2026-12-01',
            'direction' => 'credit',
            'gl_control_account_code' => '2010',
            'original_amount' => '57500.0000',
            'allocated_amount' => '20000.0000',
            'remaining_amount' => '37500.0000',
            'status' => 'partially_allocated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $allocId = (string) Str::uuid();
        DB::table('payment_allocations')->insert([
            'id' => $allocId,
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'payment_id' => $paymentId,
            'open_item_id' => $openItemId,
            'allocated_amount' => '20000.0000',
            'allocation_date' => '2026-11-02',
            'notes' => 'Partial payment via Cheque',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $vdsId = (string) Str::uuid();
        DB::table('vds_certificates')->insert([
            'id' => $vdsId,
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'certificate_number' => 'VDS-6.6-2026-0001',
            'direction' => 'issued_to_supplier',
            'supplier_id' => $supplierId,
            'withholding_entity_name' => 'Subledger Company A',
            'withholding_entity_bin' => '9998887776665',
            'issue_date' => '2026-11-02',
            'tax_period' => 'November 2026',
            'total_invoice_amount' => '57500.0000',
            'total_vat_amount' => '7500.0000',
            'vds_amount' => '7500.0000',
            'supplier_bill_id' => $billId,
            'payment_id' => $paymentId,
            'status' => 'valid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $statementId = (string) Str::uuid();
        DB::table('bank_statements')->insert([
            'id' => $statementId,
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'chart_of_account_id' => $this->bankAccountA->id,
            'statement_date' => '2026-11-01',
            'opening_balance' => '100000.0000',
            'closing_balance' => '150000.0000',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stmtLineId = (string) Str::uuid();
        DB::table('bank_statement_lines')->insert([
            'id' => $stmtLineId,
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'bank_statement_id' => $statementId,
            'line_date' => '2026-11-01',
            'description' => 'Deposit from Client',
            'deposit_amount' => '50000.0000',
            'withdrawal_amount' => '0.0000',
            'balance' => '150000.0000',
            'is_reconciled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Switch to Tenant B and assert all tables return 0 rows
        TenantContext::set($this->tenantB);

        $this->assertEquals(0, DB::table('suppliers')->count());
        $this->assertEquals(0, DB::table('supplier_bills')->count());
        $this->assertEquals(0, DB::table('payments')->count());
        $this->assertEquals(0, DB::table('subledger_open_items')->count());
        $this->assertEquals(0, DB::table('payment_allocations')->count());
        $this->assertEquals(0, DB::table('vds_certificates')->count());
        $this->assertEquals(0, DB::table('bank_statements')->count());
        $this->assertEquals(0, DB::table('bank_statement_lines')->count());

        // 3. Clear tenant context (fail-closed)
        TenantContext::clear();

        $this->assertEquals(0, DB::table('suppliers')->count());
        $this->assertEquals(0, DB::table('supplier_bills')->count());
        $this->assertEquals(0, DB::table('payments')->count());
        $this->assertEquals(0, DB::table('subledger_open_items')->count());
        $this->assertEquals(0, DB::table('payment_allocations')->count());
        $this->assertEquals(0, DB::table('vds_certificates')->count());
        $this->assertEquals(0, DB::table('bank_statements')->count());
        $this->assertEquals(0, DB::table('bank_statement_lines')->count());
    }

    public function test_cross_company_references_are_rejected_by_composite_fks(): void
    {
        TenantContext::set($this->tenantA);

        $this->expectException(QueryException::class);

        // Attempt to insert supplier for Company B under Tenant A context
        DB::table('suppliers')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyB->id, // Belongs to Tenant B!
            'name' => 'Illegal Cross Company Supplier',
            'credit_period_days' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
