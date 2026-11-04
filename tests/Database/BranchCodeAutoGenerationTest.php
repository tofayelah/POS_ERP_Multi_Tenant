<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Branches\Pages\EditBranch;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BranchCodeAutoGenerationTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected User $superUser;

    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Auto Code Tenant',
            'subdomain' => 'autocode-'.Str::lower(Str::random(6)),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($this->tenant->id);
        }

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Auto Code Company Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        // Create Super User (Tenant Owner)
        $this->superUser = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Super User',
            'email' => 'super-'.Str::random(6).'@kcerp.com',
            'password' => 'Password123!',
        ]);
        $ownerRole = Role::firstOrCreate(['name' => 'Tenant Owner', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $this->superUser->assignRole($ownerRole);

        // Create Regular User (Cashier)
        $this->regularUser = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Regular User',
            'email' => 'cashier-'.Str::random(6).'@kcerp.com',
            'password' => 'Password123!',
        ]);
        $cashierRole = Role::firstOrCreate(['name' => 'Cashier', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $this->regularUser->assignRole($cashierRole);
    }

    public function test_generates_br01_when_no_branches_exist(): void
    {
        $code = Branch::generateNextCode($this->tenant->id, $this->company->id);

        $this->assertSame('BR-01', $code);
    }

    public function test_branch_creation_without_code_automatically_populates_code(): void
    {
        $branch1 = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'First Outlet',
        ]);

        $this->assertSame('BR-01', $branch1->code);

        $branch2 = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Second Outlet',
        ]);

        $this->assertSame('BR-02', $branch2->code);

        $branch3 = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Third Outlet',
        ]);

        $this->assertSame('BR-03', $branch3->code);
    }

    public function test_super_user_can_update_branch_code(): void
    {
        Auth::login($this->superUser);

        $branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Editable Outlet',
            'code' => 'BR-01',
        ]);

        $this->assertTrue(BranchResource::canDelete($branch));
        $this->assertTrue(BranchResource::canDeleteAny());

        $branch->update([
            'name' => 'Renamed Outlet',
            'code' => 'BR-CUSTOM-01',
        ]);

        $branch->refresh();
        $this->assertSame('BR-CUSTOM-01', $branch->code);
        $this->assertSame('Renamed Outlet', $branch->name);
    }

    public function test_non_super_user_cannot_update_branch_code(): void
    {
        Auth::login($this->regularUser);

        $branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Protected Outlet',
            'code' => 'BR-01',
        ]);

        $this->assertFalse(BranchResource::canDelete($branch));
        $this->assertFalse(BranchResource::canDeleteAny());

        // Regular user attempts to change code
        $branch->update([
            'name' => 'Updated Outlet Name',
            'code' => 'BR-TAMPERED',
        ]);

        $branch->refresh();
        $this->assertSame('BR-01', $branch->code);
        $this->assertSame('Updated Outlet Name', $branch->name);
    }

    public function test_sequence_skips_existing_non_prefixed_or_padded_numbers(): void
    {
        Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Seeded Branch',
            'code' => '02',
        ]);

        $nextCode = Branch::generateNextCode($this->tenant->id, $this->company->id);
        $this->assertSame('BR-03', $nextCode);
    }

    public function test_branch_can_be_inactivated_and_reactivated(): void
    {
        Auth::login($this->superUser);

        $branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Outlet to Inactivate',
            'is_active' => true,
        ]);

        $this->assertTrue($branch->is_active);

        // Inactivate branch
        $branch->update(['is_active' => false]);
        $branch->refresh();
        $this->assertFalse($branch->is_active);

        // Inactive branch remains queryable in the resource query
        $found = Branch::where('id', $branch->id)->first();
        $this->assertNotNull($found);
        $this->assertFalse($found->is_active);

        // Reactivate branch
        $branch->update(['is_active' => true]);
        $branch->refresh();
        $this->assertTrue($branch->is_active);
    }

    public function test_livewire_edit_branch_inactivates_and_redirects_to_index(): void
    {
        Auth::login($this->superUser);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->tenant);

        $branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Livewire Inactivation Test Outlet',
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superUser)
            ->test(EditBranch::class, [
                'record' => $branch->id,
            ])
            ->assertSet('data.is_active', true)
            ->set('data.is_active', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(BranchResource::getUrl('index'));

        $branch->refresh();
        $this->assertFalse($branch->is_active);
    }
}
