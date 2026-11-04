<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockOversellException;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Modules\Inventory\Contracts\OfflineOversellServiceContract;
use App\Modules\Inventory\Contracts\StockMovementServiceContract;
use App\Modules\Inventory\DTOs\StockMovementDTO;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineOversellSyncTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Warehouse $warehouse;

    protected ProductVariant $variant;

    protected OfflineOversellServiceContract $oversellService;

    protected StockMovementServiceContract $movementService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Oversell Tenant',
            'subdomain' => 'oversell-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Oversell Test Company',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'WH-OVERSELL-01',
            'name' => 'Oversell Warehouse',
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
            'code' => 'PROD-OV-01',
            'name_en' => 'Oversell Product',
            'base_uom' => 'Pcs',
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'tax_code_id' => $taxCode->id,
            'sku' => 'SKU-OV-001',
            'name_en' => 'Oversell Variant',
            'base_uom' => 'Pcs',
            'cost_price' => '100.0000',
            'mrp' => '150.0000',
            'is_active' => true,
        ]);

        $this->movementService = app(StockMovementServiceContract::class);
        $this->oversellService = app(OfflineOversellServiceContract::class);

        // Put 2 units in warehouse initially
        DB::transaction(fn () => $this->movementService->recordMovement(new StockMovementDTO(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            stockLocationId: null,
            productVariantId: $this->variant->id,
            movementType: 'PURCHASE_RECEIPT',
            quantity: '2.0000',
            unitCost: '100.0000',
            referenceType: 'initial_stock',
            referenceId: (string) Str::uuid()
        )));
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_offline_oversell_syncs_unconditionally_and_opens_supervisor_exception(): void
    {
        $salesInvoiceId = (string) Str::uuid();

        // 1. Offline POS sold 5 units when only 2 were on hand
        $result = $this->oversellService->recordOfflineSale(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            productVariantId: $this->variant->id,
            quantity: '5.0000',
            unitCost: '100.0000',
            salesInvoiceId: $salesInvoiceId
        );

        // 2. Verify stock balance goes negative (-3.0000)
        $balance = StockBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->value('quantity_on_hand');
        $this->assertEquals('-3.0000', $balance);

        // 3. Verify stock movement was recorded as OFFLINE_OVERSELL
        $movement = StockMovement::where('reference_type', 'sales_invoice')
            ->where('reference_id', $salesInvoiceId)
            ->firstOrFail();
        $this->assertEquals('OFFLINE_OVERSELL', $movement->movement_type);
        $this->assertEquals('-5.0000', $movement->quantity);

        // 4. Verify supervisor exception was opened
        $exception = StockOversellException::where('sales_invoice_id', $salesInvoiceId)->firstOrFail();
        $this->assertEquals('OPEN', $exception->status);
        $this->assertEquals('3.0000', $exception->quantity_oversold);
        $this->assertEquals('100.0000', $exception->cost_basis_used);
    }

    public function test_exception_resolution_by_grn_trues_up_balance(): void
    {
        $salesInvoiceId = (string) Str::uuid();

        // Oversell 5 units (with 2 on hand -> deficit 3 units)
        $this->oversellService->recordOfflineSale(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            productVariantId: $this->variant->id,
            quantity: '5.0000',
            unitCost: '100.0000',
            salesInvoiceId: $salesInvoiceId
        );

        $exception = StockOversellException::where('sales_invoice_id', $salesInvoiceId)->firstOrFail();
        $this->assertEquals('OPEN', $exception->status);

        // Later GRN of 10 units arrives
        $grnId = (string) Str::uuid();
        DB::transaction(fn () => $this->movementService->recordMovement(new StockMovementDTO(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            stockLocationId: null,
            productVariantId: $this->variant->id,
            movementType: 'PURCHASE_RECEIPT',
            quantity: '10.0000',
            unitCost: '105.0000',
            referenceType: 'purchase_receipt',
            referenceId: $grnId
        )));

        // Balance is now -3 + 10 = +7.0000
        $balance = StockBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->value('quantity_on_hand');
        $this->assertEquals('7.0000', $balance);

        // Resolve exception by GRN
        $resolved = $this->oversellService->resolveException(
            exceptionId: $exception->id,
            resolutionType: 'RESOLVED_BY_GRN',
            resolvedByDocumentId: $grnId,
            notes: 'Goods physically arrived via supplier GRN'
        );

        $this->assertEquals('RESOLVED_BY_GRN', $resolved->status);
        $this->assertNotNull($resolved->resolved_at);
        $this->assertEquals($grnId, $resolved->resolved_by_document_id);
    }

    public function test_online_checkout_rejects_insufficient_stock(): void
    {
        // Try to sell 5 units online when only 2 available
        $this->expectException(InsufficientStockException::class);

        DB::transaction(fn () => $this->movementService->recordMovement(new StockMovementDTO(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            stockLocationId: null,
            productVariantId: $this->variant->id,
            movementType: 'SALE',
            quantity: '-5.0000',
            unitCost: '100.0000',
            referenceType: 'sales_invoice',
            referenceId: (string) Str::uuid()
        )));
    }
}
