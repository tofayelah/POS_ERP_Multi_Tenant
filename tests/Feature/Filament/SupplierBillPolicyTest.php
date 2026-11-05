<?php

namespace Tests\Feature\Filament;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\UserLocationScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SupplierBillPolicyTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branchA;

    protected Branch $branchB;

    protected Supplier $supplier;

    protected User $apOfficerA;

    protected User $financialController;

    protected User $auditor;

    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create Tenant & bind context
        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'AP Policy Tenant',
            'subdomain' => 'appol-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        // 2. Create Company
        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'AP Policy Corp Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        // 3. Create Branches
        $this->branchA = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Branch A',
            'code' => 'BR-A',
            'is_active' => true,
        ]);

        $this->branchB = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Branch B',
            'code' => 'BR-B',
            'is_active' => true,
        ]);

        // 4. Create Supplier
        $this->supplier = Supplier::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Standard Food Grain Supplier',
            'is_active' => true,
        ]);

        // 5. Setup Roles
        Role::firstOrCreate(['name' => 'Tenant Admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        Role::firstOrCreate(['name' => 'Accounts Payable Officer', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        Role::firstOrCreate(['name' => 'Financial Controller', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        Role::firstOrCreate(['name' => 'Cashier', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);

        // AP Officer scoped to Branch A
        $this->apOfficerA = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'AP Officer Dhanmondi',
            'email' => 'ap-dhn-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);
        $this->apOfficerA->assignRole('Accounts Payable Officer');
        $m1 = TenantMembership::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->apOfficerA->id,
            'is_active' => true,
        ]);
        UserLocationScope::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'membership_id' => $m1->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
        ]);

        // Financial Controller (company-wide)
        $this->financialController = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'FC Controller',
            'email' => 'fc-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);
        $this->financialController->assignRole('Financial Controller');
        $m2 = TenantMembership::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->financialController->id,
            'is_active' => true,
        ]);
        UserLocationScope::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'membership_id' => $m2->id,
            'company_id' => $this->company->id,
            'branch_id' => null,
        ]);

        // Auditor (company-wide read-only)
        $this->auditor = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Audit User',
            'email' => 'aud-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);
        $this->auditor->assignRole('Auditor');
        $m3 = TenantMembership::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->auditor->id,
            'is_active' => true,
        ]);
        UserLocationScope::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'membership_id' => $m3->id,
            'company_id' => $this->company->id,
            'branch_id' => null,
        ]);

        // Cashier (restricted)
        $this->cashier = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Cashier POS',
            'email' => 'pos-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);
        $this->cashier->assignRole('Cashier');
        $m4 = TenantMembership::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->cashier->id,
            'is_active' => true,
        ]);
        UserLocationScope::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'membership_id' => $m4->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
        ]);
    }

    public function test_supplier_bill_view_any_and_create_permissions(): void
    {
        TenantContext::set($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        $this->assertTrue(Gate::forUser($this->apOfficerA)->allows('viewAny', SupplierBill::class));
        $this->assertTrue(Gate::forUser($this->financialController)->allows('viewAny', SupplierBill::class));
        $this->assertTrue(Gate::forUser($this->auditor)->allows('viewAny', SupplierBill::class));
        $this->assertFalse(Gate::forUser($this->cashier)->allows('viewAny', SupplierBill::class));

        $this->assertTrue(Gate::forUser($this->apOfficerA)->allows('create', SupplierBill::class));
        $this->assertTrue(Gate::forUser($this->financialController)->allows('create', SupplierBill::class));
        $this->assertFalse(Gate::forUser($this->auditor)->allows('create', SupplierBill::class));
        $this->assertFalse(Gate::forUser($this->cashier)->allows('create', SupplierBill::class));
    }

    public function test_supplier_bill_location_scoped_view_and_update(): void
    {
        TenantContext::set($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        $billA = SupplierBill::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'BILL-LOC-A',
            'bill_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => '1000.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '1000.0000',
            'status' => 'draft',
            'created_by' => $this->apOfficerA->id,
        ]);

        $billB = SupplierBill::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branchB->id,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'BILL-LOC-B',
            'bill_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => '2000.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '2000.0000',
            'status' => 'draft',
            'created_by' => $this->financialController->id,
        ]);

        // apOfficerA can view & update billA (same branch)
        $this->assertTrue(Gate::forUser($this->apOfficerA)->allows('view', $billA));
        $this->assertTrue(Gate::forUser($this->apOfficerA)->allows('update', $billA));

        // apOfficerA CANNOT view or update billB (different branch)
        $this->assertFalse(Gate::forUser($this->apOfficerA)->allows('view', $billB));
        $this->assertFalse(Gate::forUser($this->apOfficerA)->allows('update', $billB));

        // Financial controller has company-wide access
        $this->assertTrue(Gate::forUser($this->financialController)->allows('view', $billA));
        $this->assertTrue(Gate::forUser($this->financialController)->allows('view', $billB));
        $this->assertTrue(Gate::forUser($this->financialController)->allows('update', $billA));
        $this->assertTrue(Gate::forUser($this->financialController)->allows('update', $billB));
    }

    public function test_approved_supplier_bill_is_immutable(): void
    {
        TenantContext::set($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        $approvedBill = SupplierBill::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'BILL-IMMUTABLE-01',
            'bill_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => '5000.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '5000.0000',
            'status' => 'approved',
            'created_by' => $this->apOfficerA->id,
        ]);

        // Cannot update or delete approved bill
        $this->assertFalse(Gate::forUser($this->apOfficerA)->allows('update', $approvedBill));
        $this->assertFalse(Gate::forUser($this->apOfficerA)->allows('delete', $approvedBill));
        $this->assertFalse(Gate::forUser($this->financialController)->allows('update', $approvedBill));
        $this->assertFalse(Gate::forUser($this->financialController)->allows('delete', $approvedBill));
        $this->assertFalse(Gate::forUser($this->financialController)->allows('deleteAny', SupplierBill::class));
    }

    public function test_managerial_override_segregation_of_duties(): void
    {
        TenantContext::set($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        $bill = SupplierBill::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'BILL-SOD-01',
            'bill_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => '1000.0000',
            'tax_amount' => '0.0000',
            'total_amount' => '1000.0000',
            'status' => 'draft',
            'created_by' => $this->apOfficerA->id,
        ]);

        // Regular AP Officer cannot override
        $this->assertFalse(Gate::forUser($this->apOfficerA)->allows('override', $bill));
        $this->assertFalse(Gate::forUser($this->auditor)->allows('override', $bill));
        $this->assertFalse(Gate::forUser($this->cashier)->allows('override', $bill));

        // Financial Controller can override
        $this->assertTrue(Gate::forUser($this->financialController)->allows('override', $bill));
    }
}
