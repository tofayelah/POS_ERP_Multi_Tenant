<?php

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

beforeEach(function () {
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
});

afterEach(function () {
    TenantContext::clear();
});

test('true parallel pos checkouts prevent overselling', function () {
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

    $exitA = $processA->getExitCode();
    $exitB = $processB->getExitCode();

    expect(($exitA === 0 && $exitB === 1) || ($exitB === 0 && $exitA === 1))
        ->toBeTrue("Expected one process to exit 0 and one to exit 1. Got Process A: {$exitA}, Process B: {$exitB}. Output A: {$processA->getErrorOutput()}, Output B: {$processB->getErrorOutput()}");

    $balance = StockBalance::where('warehouse_id', $this->warehouse->id)
        ->where('product_variant_id', $this->variant->id)
        ->value('quantity_on_hand');

    $saleMovements = StockMovement::where('warehouse_id', $this->warehouse->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('movement_type', 'SALE')
        ->count();

    expect($balance)->toBe('0.0000');
    expect($saleMovements)->toBe(1);
});
