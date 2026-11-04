<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Tests\TestCase;

class FiscalPeriodDatabaseTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $companyA;

    protected Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Fiscal Test Tenant',
            'subdomain' => 'fiscal-'.Str::random(6),
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

    public function test_can_create_july_to_june_fiscal_year_and_monthly_periods_acc_03(): void
    {
        $fy = FiscalYear::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'title' => 'FY 2026-2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_closed' => false,
        ]);

        $this->assertNotNull($fy->id);
        $this->assertSame('FY 2026-2027', $fy->title);

        $period1 = AccountingPeriod::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'fiscal_year_id' => $fy->id,
            'period_number' => 1,
            'period_name' => 'July 2026',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'open',
        ]);

        $this->assertSame(1, $period1->period_number);
        $this->assertSame('open', $period1->status);
    }

    public function test_duplicate_fiscal_year_dates_for_same_company_rejected(): void
    {
        FiscalYear::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'title' => 'FY 2026-2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(unique|duplicate|uq_fiscal_year_dates)/i');

        FiscalYear::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'title' => 'Duplicate FY',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);
    }

    public function test_accounting_period_invalid_status_rejected_by_check_constraint(): void
    {
        $fy = FiscalYear::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'title' => 'FY 2026-2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(check constraint|chk_accounting_period_status)/i');

        AccountingPeriod::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'fiscal_year_id' => $fy->id,
            'period_number' => 1,
            'period_name' => 'July 2026',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'invalid_status',
        ]);
    }

    public function test_period_cannot_reference_another_companys_fiscal_year_new_22(): void
    {
        // FY belongs to Company A
        $fy = FiscalYear::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'title' => 'FY 2026-2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);

        // Attempting to create period under Company B referencing Company A's FY
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(foreign key|fk_periods_year|violates foreign key)/i');

        AccountingPeriod::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id, // Mismatched company
            'fiscal_year_id' => $fy->id,
            'period_number' => 1,
            'period_name' => 'July 2026',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'open',
        ]);
    }
}
