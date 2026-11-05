<?php

namespace Tests\Feature\Filament;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use App\Models\TaxCode;
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

class SupplierQuotationPolicyTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branchA;

    protected Branch $branchB;

    protected Supplier $supplier;

    protected TaxCode $taxCode;

    protected Product $product;

    protected ProductVariant $variant;

    protected User $procurementMgr;

    protected User $procurementOfficerBranchA;

    protected User $cashier;

    protected User $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create Tenant & bind context
        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Supplier Quote Policy Tenant',
            'subdomain' => 'sqpol-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        // 2. Create Company
        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'SQ Policy Corp Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        // 3. Create Branches
        $this->branchA = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Dhanmondi Branch',
            'code' => 'BR-DHN',
            'is_active' => true,
        ]);

        $this->branchB = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Gulshan Branch',
            'code' => 'BR-GLS',
            'is_active' => true,
        ]);

        // 4. Create Supplier
        $this->supplier = Supplier::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Standard Grain Traders',
            'is_active' => true,
        ]);

        // 5. Setup Tax & Product
        $this->taxCode = TaxCode::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'TAX-EX-SQ',
            'name_en' => 'Exempt SQ Code',
            'description' => 'Exempt code',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'PRD-FLOUR',
            'name_en' => 'Wheat Flour',
            'base_uom' => 'KG',
        ]);

        $this->variant = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'tax_code_id' => $this->taxCode->id,
            'sku' => 'SKU-FLOUR-50',
            'name_en' => '50kg Flour Bag',
            'cost_price' => '2000.0000',
        ]);

        // 6. Setup Roles
        Role::firstOrCreate(['name' => 'Procurement Manager', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        Role::firstOrCreate(['name' => 'Procurement Officer', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        Role::firstOrCreate(['name' => 'Cashier', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);

        // Procurement Manager (company-wide)
        $this->procurementMgr = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Procurement Head',
            'email' => 'pmgr-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);
        $this->procurementMgr->assignRole('Procurement Manager');
        $m1 = TenantMembership::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->procurementMgr->id,
            'is_active' => true,
        ]);
        UserLocationScope::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'membership_id' => $m1->id,
            'company_id' => $this->company->id,
            'branch_id' => null,
        ]);

        // Procurement Officer (scoped to Branch A)
        $this->procurementOfficerBranchA = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Procurement Officer Dhanmondi',
            'email' => 'poff-dhn-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);
        $this->procurementOfficerBranchA->assignRole('Procurement Officer');
        $m2 = TenantMembership::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->procurementOfficerBranchA->id,
            'is_active' => true,
        ]);
        UserLocationScope::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'membership_id' => $m2->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
        ]);

        // Auditor (read-only)
        $this->auditor = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Auditor User',
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

        // Cashier (unauthorized)
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

    public function test_supplier_quotation_view_any_and_create_permissions(): void
    {
        TenantContext::set($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        $this->assertTrue(Gate::forUser($this->procurementMgr)->allows('viewAny', SupplierQuotation::class));
        $this->assertTrue(Gate::forUser($this->procurementOfficerBranchA)->allows('viewAny', SupplierQuotation::class));
        $this->assertTrue(Gate::forUser($this->auditor)->allows('viewAny', SupplierQuotation::class));
        $this->assertFalse(Gate::forUser($this->cashier)->allows('viewAny', SupplierQuotation::class));

        $this->assertTrue(Gate::forUser($this->procurementMgr)->allows('create', SupplierQuotation::class));
        $this->assertTrue(Gate::forUser($this->procurementOfficerBranchA)->allows('create', SupplierQuotation::class));
        $this->assertFalse(Gate::forUser($this->auditor)->allows('create', SupplierQuotation::class));
        $this->assertFalse(Gate::forUser($this->cashier)->allows('create', SupplierQuotation::class));
    }

    public function test_supplier_quotation_location_scoped_view_and_update(): void
    {
        TenantContext::set($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        $quoteA = SupplierQuotation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
            'supplier_id' => $this->supplier->id,
            'rfq_number' => 'RFQ-2026-0001',
            'supplier_quote_reference' => 'QUOTE-VEND-A',
            'quote_date' => Carbon::today()->toDateString(),
            'valid_until' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => '2000.0000',
            'tax_amount' => '0.0000',
            'grand_total' => '2000.0000',
            'status' => 'draft',
            'created_by' => $this->procurementOfficerBranchA->id,
        ]);

        $quoteB = SupplierQuotation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branchB->id,
            'supplier_id' => $this->supplier->id,
            'rfq_number' => 'RFQ-2026-0002',
            'supplier_quote_reference' => 'QUOTE-VEND-B',
            'quote_date' => Carbon::today()->toDateString(),
            'valid_until' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => '3000.0000',
            'tax_amount' => '0.0000',
            'grand_total' => '3000.0000',
            'status' => 'draft',
            'created_by' => $this->procurementMgr->id,
        ]);

        // Officer in Branch A can view and update quote in Branch A
        $this->assertTrue(Gate::forUser($this->procurementOfficerBranchA)->allows('view', $quoteA));
        $this->assertTrue(Gate::forUser($this->procurementOfficerBranchA)->allows('update', $quoteA));

        // Officer in Branch A CANNOT view or update quote in Branch B
        $this->assertFalse(Gate::forUser($this->procurementOfficerBranchA)->allows('view', $quoteB));
        $this->assertFalse(Gate::forUser($this->procurementOfficerBranchA)->allows('update', $quoteB));

        // Manager has company-wide access
        $this->assertTrue(Gate::forUser($this->procurementMgr)->allows('view', $quoteA));
        $this->assertTrue(Gate::forUser($this->procurementMgr)->allows('view', $quoteB));
        $this->assertTrue(Gate::forUser($this->procurementMgr)->allows('update', $quoteA));
        $this->assertTrue(Gate::forUser($this->procurementMgr)->allows('update', $quoteB));
    }

    public function test_awarded_or_converted_supplier_quotation_is_immutable(): void
    {
        TenantContext::set($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        $convertedQuote = SupplierQuotation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branchA->id,
            'supplier_id' => $this->supplier->id,
            'rfq_number' => 'RFQ-2026-0003',
            'supplier_quote_reference' => 'QUOTE-VEND-C',
            'quote_date' => Carbon::today()->toDateString(),
            'valid_until' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => '5000.0000',
            'tax_amount' => '0.0000',
            'grand_total' => '5000.0000',
            'status' => 'converted_to_po',
            'created_by' => $this->procurementMgr->id,
        ]);

        // Converted quote cannot be updated or deleted
        $this->assertFalse(Gate::forUser($this->procurementMgr)->allows('update', $convertedQuote));
        $this->assertFalse(Gate::forUser($this->procurementMgr)->allows('delete', $convertedQuote));
        $this->assertFalse(Gate::forUser($this->procurementMgr)->allows('deleteAny', SupplierQuotation::class));
    }
}
