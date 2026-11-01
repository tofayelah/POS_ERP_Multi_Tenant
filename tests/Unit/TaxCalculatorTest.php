<?php

namespace Tests\Unit;

use App\Core\ValueObjects\Money;
use App\Models\Company;
use App\Models\TaxCode;
use App\Models\TaxCodeRate;
use App\Models\Tenant;
use App\Modules\Tax\Services\TaxRateResolver;
use App\Modules\Tax\TaxCalculator;
use Carbon\Carbon;
use Illuminate\Support\Str;

$vectorsPath = __DIR__.'/../golden/vat-vectors.json';
$vectors = json_decode((string) file_get_contents($vectorsPath), true);

$lineVectorsById = [];
foreach ($vectors as $v) {
    if (! isset($v['lines']) && ! isset($v['type'])) {
        $lineVectorsById[$v['id']] = $v;
    }
}

foreach ($vectors as $vector) {
    if ($vector['id'] === 'basket-total') {
        test("matches golden vector [{$vector['id']}] (basket total)", function () use ($vector, $lineVectorsById) {
            $calculator = new TaxCalculator;
            $basketLines = array_map(function ($lineId) use ($lineVectorsById) {
                $line = $lineVectorsById[$lineId];

                return [
                    'gross' => $line['gross'],
                    'vat_rate' => $line['vat_rate'],
                    'basis' => 'tax_inclusive',
                ];
            }, $vector['lines']);

            $result = $calculator->calculateBasket($basketLines);

            expect((string) $result->gross)->toBe($vector['expect']['gross'])
                ->and((string) $result->net)->toBe($vector['expect']['net'])
                ->and((string) $result->vat)->toBe($vector['expect']['vat']);
        });
    } elseif (($vector['type'] ?? '') === 'invoice_discount') {
        test("matches golden vector [{$vector['id']}] (invoice discount)", function () use ($vector) {
            $calculator = new TaxCalculator;
            $lines = array_map(function ($l) {
                return [
                    'gross' => $l['gross'],
                    'vat_rate' => $l['vat_rate'],
                    'basis' => 'tax_inclusive',
                ];
            }, $vector['lines']);

            $result = $calculator->calculateBasket($lines, $vector['invoice_discount']);

            expect((string) $result->gross)->toBe($vector['expect']['gross'])
                ->and((string) $result->discount)->toBe($vector['expect']['discount'])
                ->and((string) $result->totalPayable)->toBe($vector['expect']['discounted_gross'])
                ->and((string) $result->net)->toBe($vector['expect']['net'])
                ->and((string) $result->vat)->toBe($vector['expect']['vat']);
        });
    } elseif (($vector['type'] ?? '') === 'cash_rounding') {
        test("matches golden vector [{$vector['id']}] (cash rounding to ৳1)", function () use ($vector) {
            $calculator = new TaxCalculator;
            foreach ($vector['cases'] as $case) {
                $result = $calculator->roundCash($case['payable']);

                expect((string) $result->roundedCash)->toBe($case['rounded_cash'])
                    ->and((string) $result->adjustment)->toBe($case['adjustment']);
            }
        });
    } elseif ($vector['basis'] === 'tax_exclusive') {
        test("matches golden vector [{$vector['id']}] (tax exclusive wholesale)", function () use ($vector) {
            $calculator = new TaxCalculator;
            $result = $calculator->calculateTaxExclusiveLine(
                net: $vector['net'],
                vatRate: $vector['vat_rate'],
                sdRate: $vector['sd_rate'] ?? '0',
            );

            expect((string) $result->net)->toBe($vector['expect']['net'])
                ->and((string) $result->supplementaryDuty)->toBe($vector['expect']['sd'])
                ->and((string) $result->vat)->toBe($vector['expect']['vat'])
                ->and((string) $result->total)->toBe($vector['expect']['total']);
        });
    } else {
        test("matches golden vector [{$vector['id']}] (gross: {$vector['gross']}, vat: {$vector['vat_rate']}%)", function () use ($vector) {
            $calculator = new TaxCalculator;
            $gross = $vector['gross'];
            $discount = $vector['discount'] ?? '0';

            $result = $calculator->calculateTaxInclusiveLine(
                gross: $gross,
                vatRate: $vector['vat_rate'],
                sdRate: $vector['sd_rate'] ?? '0',
                discount: $discount,
            );

            $expectedGross = $vector['expect']['gross'] ?? $vector['gross'];
            expect((string) $result->gross)->toBe($expectedGross)
                ->and((string) $result->net)->toBe($vector['expect']['net'])
                ->and((string) $result->vat)->toBe($vector['expect']['vat']);

            if (isset($vector['expect']['sd'])) {
                expect((string) $result->supplementaryDuty)->toBe($vector['expect']['sd']);
            }
        });
    }
}

test('resolves rates via TaxRateResolver when TaxCode is supplied', function () {
    $tenant = Tenant::create([
        'id' => (string) Str::uuid(),
        'name' => 'Tax Calc Tenant',
        'subdomain' => 'calc-'.Str::random(6),
        'status' => 'active',
    ]);

    $company = Company::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'legal_name' => 'Tax Co',
        'base_currency' => 'BDT',
        'fiscal_year_start_month' => 7,
        'tax_regime' => 'standard_vat',
    ]);

    $taxCode = TaxCode::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'code' => 'VAT-TEST-15',
        'name_en' => 'Standard VAT Test',
        'is_active' => true,
    ]);

    TaxCodeRate::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'tax_code_id' => $taxCode->id,
        'rate_percent' => 15.00,
        'supplementary_duty_percent' => 0.00,
        'effective_from' => '2026-01-01',
    ]);

    $resolver = new TaxRateResolver;
    $calculator = new TaxCalculator($resolver);

    $result = $calculator->calculateLine(
        price: '130.00',
        quantity: 1,
        pricingBasis: 'tax_inclusive',
        taxCode: $taxCode,
        date: Carbon::parse('2026-06-01'),
    );

    expect((string) $result->net)->toBe('113.04')
        ->and((string) $result->vat)->toBe('16.96')
        ->and((string) $result->gross)->toBe('130.00');
});
