<?php

namespace Tests\Concurrency;

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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ParallelPosCheckoutTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Warehouse $warehouse;

    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Concurrency Tenant',
            'subdomain' => 'conc-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Concurrency Test Company',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'WH-CONC-01',
            'name' => 'Concurrency Warehouse',
            'is_active' => true,
        ]);

        $taxCode = TaxCode::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'VAT-15',
            'name_en' => 'Standard VAT 15%',
            'tax_type' => 'standard_vat',
            'is_active' => true,
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'PROD-CONC-01',
            'name_en' => 'Concurrency Product',
            'base_uom' => 'Pcs',
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'tax_code_id' => $taxCode->id,
            'sku' => 'SKU-CONC-001',
            'name_en' => 'Concurrency Variant 1',
            'base_uom' => 'Pcs',
            'cost_price' => '100.0000',
            'mrp' => '150.0000',
            'is_active' => true,
        ]);

        // Stock exactly 1 unit
        /** @var StockMovementServiceContract $service */
        $service = app(StockMovementServiceContract::class);
        $dto = new StockMovementDTO(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            stockLocationId: null,
            productVariantId: $this->variant->id,
            movementType: 'PURCHASE_RECEIPT',
            quantity: '1.0000',
            unitCost: '100.0000',
            referenceType: 'initial_stock',
            referenceId: (string) Str::uuid()
        );

        DB::transaction(fn () => $service->recordMovement($dto));
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_true_parallel_pos_checkouts_prevent_overselling(): void
    {
        // Act: Dispatch 2 asynchronous background processes via Symfony Process
        $script = base_path('tests/Fixtures/simulate_checkout.php');

        $processA = new Process([
            'php',
            $script,
            $this->tenant->id,
            $this->company->id,
            $this->warehouse->id,
            $this->variant->id,
            '1.0000',
        ]);

        $processB = new Process([
            'php',
            $script,
            $this->tenant->id,
            $this->company->id,
            $this->warehouse->id,
            $this->variant->id,
            '1.0000',
        ]);

        $processA->start();
        $processB->start();

        while ($processA->isRunning() || $processB->isRunning()) {
            usleep(10000); // 10ms wait
        }

        // Assert: Exactly one process committed (exit code 0), the other failed with Insufficient Stock (exit code 1)
        $exitA = $processA->getExitCode();
        $exitB = $processB->getExitCode();

        $this->assertTrue(
            ($exitA === 0 && $exitB === 1) || ($exitB === 0 && $exitA === 1),
            "Expected one process to exit 0 and one to exit 1. Got Process A: {$exitA}, Process B: {$exitB}. Output A: {$processA->getErrorOutput()}, Output B: {$processB->getErrorOutput()}"
        );

        // Assert: Final balance is exactly 0.0000; exactly 2 movements (1 initial + 1 sale)
        $balance = StockBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->value('quantity_on_hand');

        $saleMovements = StockMovement::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->where('movement_type', 'SALE')
            ->count();

        $this->assertEquals('0.0000', $balance);
        $this->assertEquals(1, $saleMovements);
    }
}
