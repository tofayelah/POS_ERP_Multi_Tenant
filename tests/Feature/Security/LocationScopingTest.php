<?php

namespace Tests\Feature\Security;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\UserLocationScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LocationScopingTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $companyRetail;

    protected Company $companyWholesale;

    protected Branch $branchDhanmondi;

    protected Branch $branchGulshan;

    protected Branch $branchUttara;

    protected User $cashier;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Uuid::uuid7(),
            'name' => 'Location Scope Tenant',
            'subdomain' => 'loc-'.uniqid(),
            'subscription_plan' => 'enterprise',
            'status' => 'active',
            'data_residency_region' => 'dhaka-1',
        ]);

        TenantContext::set($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        Role::firstOrCreate(['name' => 'Cashier', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        Role::firstOrCreate(['name' => 'Tenant Owner', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);

        $this->companyRetail = Company::create([
            'id' => (string) Uuid::uuid7(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Acme Retail Ltd',
            'base_currency' => 'BDT',
        ]);

        $this->companyWholesale = Company::create([
            'id' => (string) Uuid::uuid7(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Acme Wholesale Ltd',
            'base_currency' => 'BDT',
        ]);

        $this->branchDhanmondi = Branch::create([
            'id' => (string) Uuid::uuid7(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyRetail->id,
            'name' => 'Dhanmondi Branch',
            'code' => 'DHN-01',
            'is_active' => true,
        ]);

        $this->branchGulshan = Branch::create([
            'id' => (string) Uuid::uuid7(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyRetail->id,
            'name' => 'Gulshan Branch',
            'code' => 'GUL-01',
            'is_active' => true,
        ]);

        $this->branchUttara = Branch::create([
            'id' => (string) Uuid::uuid7(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyWholesale->id,
            'name' => 'Uttara Branch',
            'code' => 'UTT-01',
            'is_active' => true,
        ]);

        // Cashier user scoped strictly to Dhanmondi Branch
        $this->cashier = User::create([
            'id' => (string) Uuid::uuid7(),
            'name' => 'Cashier User',
            'email' => 'cashier.'.uniqid().'@acme.test',
            'password' => Hash::make('secret'),
        ]);
        $this->cashier->assignRole('Cashier');

        $membership = TenantMembership::create([
            'id' => (string) Uuid::uuid7(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->cashier->id,
            'is_active' => true,
        ]);

        UserLocationScope::create([
            'id' => (string) Uuid::uuid7(),
            'tenant_id' => $this->tenant->id,
            'membership_id' => $membership->id,
            'company_id' => $this->companyRetail->id,
            'branch_id' => $this->branchDhanmondi->id,
        ]);

        // Owner user with tenant-wide access
        $this->owner = User::create([
            'id' => (string) Uuid::uuid7(),
            'name' => 'Owner User',
            'email' => 'owner.'.uniqid().'@acme.test',
            'password' => Hash::make('secret'),
        ]);
        $this->owner->assignRole('Tenant Owner');

        TenantMembership::create([
            'id' => (string) Uuid::uuid7(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Auth::logout();
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * Surface 1: Tables (Filament & Eloquent queries).
     */
    public function test_surface_1_table_query_filters_records_outside_assigned_branches(): void
    {
        // 1. Authenticate as Cashier (scoped to Dhanmondi)
        Auth::login($this->cashier);

        $branches = Branch::all();
        $this->assertCount(1, $branches);
        $this->assertEquals($this->branchDhanmondi->id, $branches->first()->id);
        $this->assertNull(Branch::find($this->branchGulshan->id));
        $this->assertNull(Branch::find($this->branchUttara->id));

        // 2. Authenticate as Owner (unscoped)
        Auth::login($this->owner);
        $this->assertCount(3, Branch::all());
    }

    /**
     * Surface 2: Global Search queries.
     */
    public function test_surface_2_global_search_excludes_records_outside_assigned_branches(): void
    {
        Auth::login($this->cashier);

        // Global search query for 'Branch'
        $searchResults = Branch::where('name', 'like', '%Branch%')->get();

        $this->assertCount(1, $searchResults);
        $this->assertEquals('Dhanmondi Branch', $searchResults->first()->name);
    }

    /**
     * Surface 3: Relation Managers (Parent -> Children queries).
     */
    public function test_surface_3_relation_manager_excludes_unauthorized_branch_children(): void
    {
        Auth::login($this->cashier);

        // Retail company owns Dhanmondi & Gulshan branches
        $scopedCompanyBranches = $this->companyRetail->branches()->get();

        // Under cashier, Gulshan is filtered out by LocationScope
        $this->assertCount(1, $scopedCompanyBranches);
        $this->assertEquals($this->branchDhanmondi->id, $scopedCompanyBranches->first()->id);
    }

    /**
     * Surface 4: Streaming Report Exports.
     */
    public function test_surface_4_export_stream_excludes_unauthorized_branches(): void
    {
        Auth::login($this->cashier);

        $exportedBranchCodes = [];
        foreach (Branch::cursor() as $branch) {
            $exportedBranchCodes[] = $branch->code;
        }

        $this->assertEquals(['DHN-01'], $exportedBranchCodes);
    }

    /**
     * Surface 5: API Resources / Policy Authorization Endpoints.
     */
    public function test_surface_5_api_resource_endpoint_denies_cross_branch_access(): void
    {
        // Cashier can access assigned location
        $this->assertTrue($this->cashier->canAccessLocation($this->companyRetail->id, $this->branchDhanmondi->id));

        // Cashier cannot access unauthorized branch in same company
        $this->assertFalse($this->cashier->canAccessLocation($this->companyRetail->id, $this->branchGulshan->id));

        // Cashier cannot access branch in other company
        $this->assertFalse($this->cashier->canAccessLocation($this->companyWholesale->id, $this->branchUttara->id));

        // Owner can access all locations
        $this->assertTrue($this->owner->canAccessLocation($this->companyRetail->id, $this->branchGulshan->id));
        $this->assertTrue($this->owner->canAccessLocation($this->companyWholesale->id, $this->branchUttara->id));
    }
}
