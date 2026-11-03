<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Tests\TestCase;

class BranchCodeAutoGenerationTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

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

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Auto Code Company Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);
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

    public function test_branch_code_is_immutable_on_update(): void
    {
        $branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Original Outlet',
            'code' => 'BR-01',
        ]);

        $this->assertSame('BR-01', $branch->code);

        // Attempt to update the code
        $branch->update([
            'name' => 'Updated Outlet Name',
            'code' => 'BR-999-TAMPERED',
        ]);

        $branch->refresh();
        $this->assertSame('BR-01', $branch->code);
        $this->assertSame('Updated Outlet Name', $branch->name);
    }

    public function test_sequence_skips_existing_non_prefixed_or_padded_numbers(): void
    {
        // Suppose a branch was manually seeded with code '02' or 'BR-02'
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
}
