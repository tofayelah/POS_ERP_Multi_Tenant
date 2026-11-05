<?php

namespace Tests\Feature\Filament;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierBillDuplicateInvoiceTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected Supplier $supplier;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Dup Invoice Tenant',
            'subdomain' => 'dupinv-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Dup Invoice Corp Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Main Commercial Branch',
            'code' => 'BR-DUP',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Delta Grain Mills Ltd',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'AP Clerk',
            'email' => 'ap-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);
    }

    public function test_duplicate_vendor_invoice_number_for_same_supplier_is_blocked(): void
    {
        TenantContext::set($this->tenant);

        // 1. Create first bill with vendor_invoice_number INV-DELTA-9988
        $bill1 = SupplierBill::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'BILL-2026-0001',
            'vendor_invoice_number' => 'INV-DELTA-9988',
            'bill_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => '1000.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '1000.0000',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $this->assertNotNull($bill1->id);
        $this->assertEquals('INV-DELTA-9988', $bill1->vendor_invoice_number);

        // 2. Attempt to create second bill for same supplier with duplicate vendor_invoice_number
        $this->expectException(QueryException::class);

        SupplierBill::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'BILL-2026-0002', // Different internal bill number
            'vendor_invoice_number' => 'INV-DELTA-9988', // Identical vendor invoice number
            'bill_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => '1000.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '1000.0000',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);
    }
}
