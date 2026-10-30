<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaxRateOverlapTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Tax Test Tenant',
            'subdomain' => 'taxtest-'.Str::random(6),
            'status' => 'active',
        ]);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Tax Test Co',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        TenantContext::set($this->tenant);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_overlapping_tax_rate_periods_are_rejected_by_btree_gist_exclusion(): void
    {
        $taxCodeId = (string) Str::uuid();

        // 1. Create Tax Code under app_rw
        DB::table('tax_codes')->insert([
            'id' => $taxCodeId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'VAT-STD-15',
            'name_en' => 'Standard VAT 15%',
            'name_bn' => 'আদর্শ মূসক ১৫%',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Insert First Rate Period: 2026-01-01 to 2026-06-30
        DB::table('tax_code_rates')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'tax_code_id' => $taxCodeId,
            'rate_percent' => 15.00,
            'supplementary_duty_percent' => 0.00,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-06-30',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Attempt to insert Overlapping Period: 2026-06-15 to 2026-12-31
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(uq_no_overlapping_tax_rates|exclusion_violation|23P01)/i');

        DB::table('tax_code_rates')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'tax_code_id' => $taxCodeId,
            'rate_percent' => 10.00,
            'supplementary_duty_percent' => 0.00,
            'effective_from' => '2026-06-15',
            'effective_to' => '2026-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_non_overlapping_contiguous_tax_rate_periods_succeed(): void
    {
        $taxCodeId = (string) Str::uuid();

        DB::table('tax_codes')->insert([
            'id' => $taxCodeId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'VAT-STD-MULTI',
            'name_en' => 'Multi Period VAT',
            'name_bn' => 'বহু-মেয়াদী মূসক',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Period 1: 2026-01-01 to 2026-06-30
        DB::table('tax_code_rates')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'tax_code_id' => $taxCodeId,
            'rate_percent' => 15.00,
            'supplementary_duty_percent' => 0.00,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-06-30',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Period 2: 2026-07-01 onwards (effective_to is null)
        DB::table('tax_code_rates')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'tax_code_id' => $taxCodeId,
            'rate_percent' => 10.00,
            'supplementary_duty_percent' => 5.00,
            'effective_from' => '2026-07-01',
            'effective_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $count = DB::table('tax_code_rates')
            ->where('tax_code_id', $taxCodeId)
            ->count();

        $this->assertSame(2, $count);
    }
}
