<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Modules\Inventory\Contracts\StockMovementServiceContract;
use App\Modules\Inventory\DTOs\StockMovementDTO;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockLedgerPropertyTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    /** @var array<Warehouse> */
    protected array $warehouses = [];

    /** @var array<ProductVariant> */
    protected array $variants = [];

    protected StockMovementServiceContract $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ledger Property Tenant',
            'subdomain' => 'prop-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Property Test Company',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $taxCode = TaxCode::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'VAT-15',
            'name_en' => 'Standard VAT 15%',
            'tax_type' => 'standard_vat',
            'is_active' => true,
        ]);

        for ($w = 1; $w <= 2; $w++) {
            $this->warehouses[] = Warehouse::create([
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'code' => "WH-PROP-0{$w}",
                'name' => "Property Warehouse {$w}",
                'is_active' => true,
            ]);
        }

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'PROD-PROP',
            'name_en' => 'Property Product',
            'base_uom' => 'Pcs',
            'is_active' => true,
        ]);

        for ($v = 1; $v <= 5; $v++) {
            $this->variants[] = ProductVariant::create([
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'product_id' => $product->id,
                'tax_code_id' => $taxCode->id,
                'sku' => "SKU-PROP-0{$v}",
                'name_en' => "Property Variant {$v}",
                'base_uom' => 'Pcs',
                'cost_price' => '100.0000',
                'mrp' => '150.0000',
                'is_active' => true,
            ]);
        }

        $this->service = app(StockMovementServiceContract::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_1000_random_movements_leave_balances_exactly_equal_to_sum_of_movements(): void
    {
        $seed = 777777;
        mt_srand($seed);
        fwrite(STDOUT, "\n[StockLedgerPropertyTest] Random seed: {$seed}\n");

        // Maintain in-memory tracking of expected balances to prevent illegal online negative stock during generation
        $expectedBalances = [];
        foreach ($this->warehouses as $wh) {
            foreach ($this->variants as $var) {
                $expectedBalances[$wh->id][$var->id] = BigDecimal::zero();
            }
        }

        $types = ['PURCHASE_RECEIPT', 'SALE', 'STOCK_COUNT_ADJUSTMENT'];

        for ($i = 0; $i < 1000; $i++) {
            $wh = $this->warehouses[array_rand($this->warehouses)];
            $var = $this->variants[array_rand($this->variants)];
            $currentBal = $expectedBalances[$wh->id][$var->id];

            // Decide whether to add or deduct
            if ($currentBal->isGreaterThan(BigDecimal::of('10.0000')) && mt_rand(1, 100) > 40) {
                // Sale or downward adjustment
                $maxDeduct = (int) $currentBal->toScale(0, RoundingMode::Down)->toInt();
                $qtyInt = mt_rand(1, min(10, $maxDeduct));
                $qty = (string) BigDecimal::of(-$qtyInt)->toScale(4, RoundingMode::HalfUp);
                $type = 'SALE';
            } else {
                // Purchase receipt or positive adjustment
                $qtyInt = mt_rand(5, 50);
                $qty = (string) BigDecimal::of($qtyInt)->toScale(4, RoundingMode::HalfUp);
                $type = 'PURCHASE_RECEIPT';
            }

            $unitCost = (string) BigDecimal::of(mt_rand(80, 120))->toScale(4, RoundingMode::HalfUp);

            $dto = new StockMovementDTO(
                companyId: $this->company->id,
                warehouseId: $wh->id,
                stockLocationId: null,
                productVariantId: $var->id,
                movementType: $type,
                quantity: $qty,
                unitCost: $unitCost,
                referenceType: 'property_test',
                referenceId: (string) Str::uuid()
            );

            DB::transaction(fn () => $this->service->recordMovement($dto));

            $expectedBalances[$wh->id][$var->id] = $expectedBalances[$wh->id][$var->id]->plus($qty);
        }

        // Assert that for every warehouse and variant, stock_balances.quantity_on_hand == sum(stock_movements.quantity)
        foreach ($this->warehouses as $wh) {
            foreach ($this->variants as $var) {
                $actualBalance = StockBalance::where('warehouse_id', $wh->id)
                    ->where('product_variant_id', $var->id)
                    ->whereNull('stock_location_id')
                    ->whereNull('batch_number')
                    ->value('quantity_on_hand') ?? '0.0000';

                $movementSum = StockMovement::where('warehouse_id', $wh->id)
                    ->where('product_variant_id', $var->id)
                    ->whereNull('stock_location_id')
                    ->whereNull('batch_number')
                    ->sum('quantity') ?? '0.0000';

                $expected = (string) $expectedBalances[$wh->id][$var->id]->toScale(4, RoundingMode::HalfUp);
                $actual = (string) BigDecimal::of($actualBalance)->toScale(4, RoundingMode::HalfUp);
                $sum = (string) BigDecimal::of($movementSum)->toScale(4, RoundingMode::HalfUp);

                $this->assertEquals($expected, $actual, "Balance mismatch for WH {$wh->code} SKU {$var->sku}");
                $this->assertEquals($sum, $actual, "Movement sum mismatch for WH {$wh->code} SKU {$var->sku}");
            }
        }
    }
}
