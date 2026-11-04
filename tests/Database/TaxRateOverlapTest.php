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

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Tax Test Co',
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

    public function test_insert_overlapping_tax_rate_periods_rejected_by_btree_gist_exclusion_new_20(): void
    {
        $taxCodeId = (string) Str::uuid();

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

        // Insert Base Period: 2026-01-01 to 2026-06-30
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

        // Attempt overlapping insert: 2026-06-15 to 2026-12-31
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

    public function test_update_extending_effective_to_into_existing_period_rejected_new_20(): void
    {
        $taxCodeId = (string) Str::uuid();

        DB::table('tax_codes')->insert([
            'id' => $taxCodeId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'VAT-UPDATE-TEST',
            'name_en' => 'Update Test VAT',
            'name_bn' => 'আপডেট টেস্ট মূসক',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rate1Id = (string) Str::uuid();
        DB::table('tax_code_rates')->insert([
            'id' => $rate1Id,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'tax_code_id' => $taxCodeId,
            'rate_percent' => 15.00,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-03-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tax_code_rates')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'tax_code_id' => $taxCodeId,
            'rate_percent' => 10.00,
            'effective_from' => '2026-04-01',
            'effective_to' => '2026-06-30',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempt update extending Period 1 past 2026-03-31 into 2026-04-15
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(uq_no_overlapping_tax_rates|exclusion_violation|23P01)/i');

        DB::table('tax_code_rates')
            ->where('id', $rate1Id)
            ->update(['effective_to' => '2026-04-15']);
    }

    public function test_update_moving_rate_to_another_tax_code_causing_overlap_rejected_new_20(): void
    {
        $taxCode1Id = (string) Str::uuid();
        $taxCode2Id = (string) Str::uuid();

        DB::table('tax_codes')->insert([
            ['id' => $taxCode1Id, 'tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'code' => 'TC-1', 'name_en' => 'TC 1', 'name_bn' => 'টিসি ১', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $taxCode2Id, 'tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'code' => 'TC-2', 'name_en' => 'TC 2', 'name_bn' => 'টিসি ২', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Rate in TC-1: 2026-01-01 to 2026-06-30
        $rate1Id = (string) Str::uuid();
        DB::table('tax_code_rates')->insert([
            'id' => $rate1Id,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'tax_code_id' => $taxCode1Id,
            'rate_percent' => 15.00,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-06-30',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Rate in TC-2: 2026-03-01 to 2026-08-31
        DB::table('tax_code_rates')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'tax_code_id' => $taxCode2Id,
            'rate_percent' => 10.00,
            'effective_from' => '2026-03-01',
            'effective_to' => '2026-08-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempt moving Rate 1 from TC-1 to TC-2 where it overlaps 2026-03-01 to 2026-06-30
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(uq_no_overlapping_tax_rates|exclusion_violation|23P01)/i');

        DB::table('tax_code_rates')
            ->where('id', $rate1Id)
            ->update(['tax_code_id' => $taxCode2Id]);
    }

    public function test_insert_open_ended_rate_overlapping_existing_period_rejected_new_20(): void
    {
        $taxCodeId = (string) Str::uuid();

        DB::table('tax_codes')->insert([
            'id' => $taxCodeId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'VAT-OPEN-TEST',
            'name_en' => 'Open Ended VAT',
            'name_bn' => 'উন্মুক্ত মূসক',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tax_code_rates')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'tax_code_id' => $taxCodeId,
            'rate_percent' => 15.00,
            'effective_from' => '2026-07-01',
            'effective_to' => null, // Open-ended
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempt inserting a new period starting 2026-09-01
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(uq_no_overlapping_tax_rates|exclusion_violation|23P01)/i');

        DB::table('tax_code_rates')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'tax_code_id' => $taxCodeId,
            'rate_percent' => 5.00,
            'effective_from' => '2026-09-01',
            'effective_to' => '2026-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_contiguous_non_overlapping_tax_rate_periods_allowed_new_20(): void
    {
        $taxCodeId = (string) Str::uuid();

        DB::table('tax_codes')->insert([
            'id' => $taxCodeId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'VAT-CONTIGUOUS',
            'name_en' => 'Contiguous VAT',
            'name_bn' => 'ধারাবাহিক মূসক',
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
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-06-30',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Period 2: 2026-07-01 to 2026-12-31
        DB::table('tax_code_rates')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'tax_code_id' => $taxCodeId,
            'rate_percent' => 10.00,
            'effective_from' => '2026-07-01',
            'effective_to' => '2026-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $count = DB::table('tax_code_rates')->where('tax_code_id', $taxCodeId)->count();
        $this->assertSame(2, $count);
    }

    public function test_same_date_range_for_different_tax_codes_in_same_company_allowed_new_20(): void
    {
        $taxCode1Id = (string) Str::uuid();
        $taxCode2Id = (string) Str::uuid();

        DB::table('tax_codes')->insert([
            ['id' => $taxCode1Id, 'tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'code' => 'TC-DIFF-1', 'name_en' => 'TC Diff 1', 'name_bn' => 'টিসি ১', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $taxCode2Id, 'tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'code' => 'TC-DIFF-2', 'name_en' => 'TC Diff 2', 'name_bn' => 'টিসি ২', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Identical date range on different codes
        DB::table('tax_code_rates')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'tax_code_id' => $taxCode1Id,
            'rate_percent' => 15.00,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tax_code_rates')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'tax_code_id' => $taxCode2Id,
            'rate_percent' => 5.00,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1, DB::table('tax_code_rates')->where('tax_code_id', $taxCode1Id)->count());
        $this->assertSame(1, DB::table('tax_code_rates')->where('tax_code_id', $taxCode2Id)->count());
    }
}
