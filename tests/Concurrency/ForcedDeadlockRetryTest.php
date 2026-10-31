<?php

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Modules\Inventory\Contracts\StockMovementServiceContract;
use App\Modules\Inventory\DTOs\StockMovementDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->tenant = Tenant::create([
        'id' => (string) Str::uuid(),
        'name' => 'Deadlock Tenant',
        'subdomain' => 'dl-'.Str::random(6),
        'status' => 'active',
    ]);

    TenantContext::set($this->tenant);

    $this->company = Company::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenant->id,
        'legal_name' => 'Deadlock Test Company',
        'base_currency' => 'BDT',
        'fiscal_year_start_month' => 7,
        'tax_regime' => 'standard_vat',
    ]);

    $this->warehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $this->company->id,
        'code' => 'WH-DL-01',
        'name' => 'Deadlock Warehouse',
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
        'code' => 'PROD-DL-01',
        'name_en' => 'Deadlock Product',
        'base_uom' => 'Pcs',
        'is_active' => true,
    ]);

    $this->variantA = ProductVariant::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'tax_code_id' => $taxCode->id,
        'sku' => 'SKU-DL-001',
        'name_en' => 'Deadlock Variant A',
        'base_uom' => 'Pcs',
        'cost_price' => '100.0000',
        'mrp' => '150.0000',
        'is_active' => true,
    ]);

    $this->variantB = ProductVariant::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'tax_code_id' => $taxCode->id,
        'sku' => 'SKU-DL-002',
        'name_en' => 'Deadlock Variant B',
        'base_uom' => 'Pcs',
        'cost_price' => '100.0000',
        'mrp' => '150.0000',
        'is_active' => true,
    ]);

    /** @var StockMovementServiceContract $service */
    $service = app(StockMovementServiceContract::class);
    DB::transaction(function () use ($service) {
        $service->recordMovement(new StockMovementDTO(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            stockLocationId: null,
            productVariantId: $this->variantA->id,
            movementType: 'PURCHASE_RECEIPT',
            quantity: '10.0000',
            unitCost: '100.0000',
            referenceType: 'initial_stock',
            referenceId: (string) Str::uuid()
        ));

        $service->recordMovement(new StockMovementDTO(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            stockLocationId: null,
            productVariantId: $this->variantB->id,
            movementType: 'PURCHASE_RECEIPT',
            quantity: '10.0000',
            unitCost: '100.0000',
            referenceType: 'initial_stock',
            referenceId: (string) Str::uuid()
        ));
    });
});

afterEach(function () {
    TenantContext::clear();
});

test('batch checkout sorts variants ascending preventing deadlocks', function () {
    /** @var StockMovementServiceContract $service */
    $service = app(StockMovementServiceContract::class);

    $dtos = [
        new StockMovementDTO(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            stockLocationId: null,
            productVariantId: $this->variantB->id,
            movementType: 'SALE',
            quantity: '-2.0000',
            unitCost: '100.0000',
            referenceType: 'sales_invoice',
            referenceId: (string) Str::uuid()
        ),
        new StockMovementDTO(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            stockLocationId: null,
            productVariantId: $this->variantA->id,
            movementType: 'SALE',
            quantity: '-3.0000',
            unitCost: '100.0000',
            referenceType: 'sales_invoice',
            referenceId: (string) Str::uuid()
        ),
    ];

    $movements = $service->recordMovements($dtos);

    expect($movements)->toHaveCount(2);

    $balA = StockBalance::where('warehouse_id', $this->warehouse->id)
        ->where('product_variant_id', $this->variantA->id)
        ->value('quantity_on_hand');

    $balB = StockBalance::where('warehouse_id', $this->warehouse->id)
        ->where('product_variant_id', $this->variantB->id)
        ->value('quantity_on_hand');

    expect($balA)->toBe('7.0000');
    expect($balB)->toBe('8.0000');
});
