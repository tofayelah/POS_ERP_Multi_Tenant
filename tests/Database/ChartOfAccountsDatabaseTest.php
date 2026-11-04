<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Tenant;
use App\Modules\Accounting\Database\Seeders\BangladeshRetailCoaTemplate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChartOfAccountsDatabaseTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $companyA;

    protected Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'COA Test Tenant',
            'subdomain' => 'coa-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->companyA = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Company A',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->companyB = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Company B',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_can_create_account_and_enforce_unique_code_per_company(): void
    {
        $account = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'account_code' => '1010',
            'account_name' => 'Cash in Hand',
            'account_type' => 'asset',
            'is_reconcilable' => true,
            'is_active' => true,
        ]);

        $this->assertNotNull($account->id);
        $this->assertSame('1010', $account->account_code);

        // Same code in Company B is allowed
        $accountB = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
            'account_code' => '1010',
            'account_name' => 'Cash in Hand (Company B)',
            'account_type' => 'asset',
            'is_reconcilable' => true,
            'is_active' => true,
        ]);
        $this->assertNotNull($accountB->id);

        // Duplicate code in Company A is rejected
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(unique|duplicate|uq_coa_code_per_company)/i');

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'account_code' => '1010',
            'account_name' => 'Duplicate Cash',
            'account_type' => 'asset',
        ]);
    }

    public function test_account_cannot_reference_parent_from_another_company_new_22(): void
    {
        $parentInCoA = ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'account_code' => '1000',
            'account_name' => 'Current Assets',
            'account_type' => 'asset',
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(foreign key|fk_coa_parent|violates foreign key)/i');

        // Child in Company B cannot have parent in Company A
        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
            'parent_id' => $parentInCoA->id,
            'account_code' => '1010',
            'account_name' => 'Cash in Hand B',
            'account_type' => 'asset',
        ]);
    }

    public function test_bangladesh_retail_coa_template_seeds_statutory_accounts(): void
    {
        $seeder = new BangladeshRetailCoaTemplate;
        $accounts = $seeder->seedForCompany($this->tenant->id, $this->companyA->id);

        $this->assertNotEmpty($accounts);

        // Verify Statutory Tax and Withholding Accounts exist
        $vatReceivable = ChartOfAccount::where('company_id', $this->companyA->id)
            ->where('account_code', '1050')
            ->first();
        $this->assertNotNull($vatReceivable);
        $this->assertSame('asset', $vatReceivable->account_type);

        $vdsReceivable = ChartOfAccount::where('company_id', $this->companyA->id)
            ->where('account_code', '1060')
            ->first();
        $this->assertNotNull($vdsReceivable);

        $ait = ChartOfAccount::where('company_id', $this->companyA->id)
            ->where('account_code', '1070')
            ->first();
        $this->assertNotNull($ait);

        $vatPayable = ChartOfAccount::where('company_id', $this->companyA->id)
            ->where('account_code', '2020')
            ->first();
        $this->assertNotNull($vatPayable);
        $this->assertSame('liability', $vatPayable->account_type);

        $sdPayable = ChartOfAccount::where('company_id', $this->companyA->id)
            ->where('account_code', '2030')
            ->first();
        $this->assertNotNull($sdPayable);

        $vdsPayable = ChartOfAccount::where('company_id', $this->companyA->id)
            ->where('account_code', '2040')
            ->first();
        $this->assertNotNull($vdsPayable);

        $tdsPayable = ChartOfAccount::where('company_id', $this->companyA->id)
            ->where('account_code', '2050')
            ->first();
        $this->assertNotNull($tdsPayable);
    }
}
