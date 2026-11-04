<?php

namespace Tests\Unit;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\TaxCode;
use App\Models\TaxCodeRate;
use App\Models\Tenant;
use App\Modules\Tax\Contracts\TaxRateResolverContract;
use App\Modules\Tax\Exceptions\NoEffectiveTaxRateException;
use App\Modules\Tax\Services\TaxRateResolver;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaxRateResolverTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected TaxCode $taxCode;

    protected TaxRateResolverContract $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Resolver Test Tenant',
            'subdomain' => 'resolver-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Resolver Test Co',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->taxCode = TaxCode::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'VAT-STD',
            'name_en' => 'Standard VAT',
            'name_bn' => 'আদর্শ মূসক',
            'is_active' => true,
        ]);

        // Rate 1: 2026-01-01 to 2026-06-30 (15% VAT, 0% SD)
        TaxCodeRate::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'tax_code_id' => $this->taxCode->id,
            'rate_percent' => '15.00',
            'supplementary_duty_percent' => '0.00',
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-06-30',
        ]);

        // Rate 2: 2026-07-01 onwards (10% VAT, 5% SD)
        TaxCodeRate::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'tax_code_id' => $this->taxCode->id,
            'rate_percent' => '10.00',
            'supplementary_duty_percent' => '5.00',
            'effective_from' => '2026-07-01',
            'effective_to' => null,
        ]);

        $this->resolver = new TaxRateResolver;
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_resolves_period_one_rate_on_last_day_of_period(): void
    {
        $result = $this->resolver->resolve($this->taxCode, Carbon::parse('2026-06-30'));

        $this->assertSame('15.00', $result->vatRatePercent);
        $this->assertSame('0.00', $result->supplementaryDutyPercent);
        $this->assertSame('2026-01-01', $result->effectiveFrom);
        $this->assertSame('2026-06-30', $result->effectiveTo);
    }

    public function test_resolves_period_two_rate_on_start_day_of_period(): void
    {
        $result = $this->resolver->resolve($this->taxCode->id, '2026-07-01');

        $this->assertSame('10.00', $result->vatRatePercent);
        $this->assertSame('5.00', $result->supplementaryDutyPercent);
        $this->assertSame('2026-07-01', $result->effectiveFrom);
        $this->assertNull($result->effectiveTo);
    }

    public function test_resolves_period_two_rate_in_distant_future(): void
    {
        $result = $this->resolver->resolve($this->taxCode, '2028-12-31');

        $this->assertSame('10.00', $result->vatRatePercent);
        $this->assertSame('5.00', $result->supplementaryDutyPercent);
    }

    public function test_throws_exception_when_no_rate_covers_date(): void
    {
        $this->expectException(NoEffectiveTaxRateException::class);

        $this->resolver->resolve($this->taxCode, '2025-12-31');
    }
}
