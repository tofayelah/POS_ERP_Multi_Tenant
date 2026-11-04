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
use App\Modules\Inventory\Contracts\StockTransferServiceContract;
use App\Modules\Inventory\DTOs\StockMovementDTO;
use App\Modules\Inventory\DTOs\StockTransferItemDTO;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Warehouse $sourceWarehouse;

    protected Warehouse $destWarehouse;

    protected ProductVariant $variant;

    protected StockTransferServiceContract $transferService;

    protected StockMovementServiceContract $movementService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Transfer Test Tenant',
            'subdomain' => 'transfer-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Transfer Test Company',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->sourceWarehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'WH-SRC-01',
            'name' => 'Source Warehouse',
            'is_active' => true,
        ]);

        $this->destWarehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'WH-DEST-01',
            'name' => 'Destination Warehouse',
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
            'code' => 'PROD-TR-01',
            'name_en' => 'Transfer Product',
            'base_uom' => 'Pcs',
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'tax_code_id' => $taxCode->id,
            'sku' => 'SKU-TR-001',
            'name_en' => 'Transfer Variant',
            'base_uom' => 'Pcs',
            'cost_price' => '100.0000',
            'mrp' => '150.0000',
            'is_active' => true,
        ]);

        $this->movementService = app(StockMovementServiceContract::class);

        // Put 50 units in source warehouse
        DB::transaction(fn () => $this->movementService->recordMovement(new StockMovementDTO(
            companyId: $this->company->id,
            warehouseId: $this->sourceWarehouse->id,
            stockLocationId: null,
            productVariantId: $this->variant->id,
            movementType: 'PURCHASE_RECEIPT',
            quantity: '50.0000',
            unitCost: '100.0000',
            referenceType: 'initial_stock',
            referenceId: (string) Str::uuid()
        )));

        $this->transferService = app(StockTransferServiceContract::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_complete_stock_transfer_lifecycle(): void
    {
        // 1. Create draft transfer
        $transfer = $this->transferService->createTransfer(
            companyId: $this->company->id,
            sourceWarehouseId: $this->sourceWarehouse->id,
            destWarehouseId: $this->destWarehouse->id,
            items: [
                new StockTransferItemDTO(
                    productVariantId: $this->variant->id,
                    quantity: '20.0000',
                    unitCost: '100.0000'
                ),
            ],
            notes: 'Inter-branch stock replenishment'
        );

        $this->assertEquals('draft', $transfer->status);
        $this->assertCount(1, $transfer->lines);

        // Source balance unchanged in draft
        $srcBal = StockBalance::where('warehouse_id', $this->sourceWarehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->value('quantity_on_hand');
        $this->assertEquals('50.0000', $srcBal);

        // 2. Ship transfer
        $shipped = $this->transferService->shipTransfer($transfer->id);
        $this->assertEquals('in_transit', $shipped->status);
        $this->assertNotNull($shipped->shipped_at);

        // Source balance decremented to 30.0000
        $srcBalAfterShip = StockBalance::where('warehouse_id', $this->sourceWarehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->value('quantity_on_hand');
        $this->assertEquals('30.0000', $srcBalAfterShip);

        // Outbound movement recorded
        $outMovement = StockMovement::where('warehouse_id', $this->sourceWarehouse->id)
            ->where('reference_type', 'stock_transfer')
            ->where('reference_id', $transfer->id)
            ->firstOrFail();
        $this->assertEquals('TRANSFER_OUT', $outMovement->movement_type);
        $this->assertEquals('-20.0000', $outMovement->quantity);

        // Dest balance still 0
        $destBalBeforeReceive = StockBalance::where('warehouse_id', $this->destWarehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->value('quantity_on_hand');
        $this->assertNull($destBalBeforeReceive);

        // 3. Receive transfer (all 20 units received)
        $lineId = $transfer->lines->first()->id;
        $received = $this->transferService->receiveTransfer(
            transferId: $transfer->id,
            receipts: [$lineId => '20.0000']
        );

        $this->assertEquals('received', $received->status);
        $this->assertNotNull($received->received_at);

        // Dest balance incremented to 20.0000
        $destBalAfterReceive = StockBalance::where('warehouse_id', $this->destWarehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->value('quantity_on_hand');
        $this->assertEquals('20.0000', $destBalAfterReceive);

        // Inbound movement recorded
        $inMovement = StockMovement::where('warehouse_id', $this->destWarehouse->id)
            ->where('reference_type', 'stock_transfer')
            ->where('reference_id', $transfer->id)
            ->firstOrFail();
        $this->assertEquals('TRANSFER_IN', $inMovement->movement_type);
        $this->assertEquals('20.0000', $inMovement->quantity);
    }

    public function test_stock_transfer_with_transit_discrepancy(): void
    {
        // Transfer 10 units, but destination only receives 8 (2 damaged in transit)
        $transfer = $this->transferService->createTransfer(
            companyId: $this->company->id,
            sourceWarehouseId: $this->sourceWarehouse->id,
            destWarehouseId: $this->destWarehouse->id,
            items: [
                new StockTransferItemDTO(
                    productVariantId: $this->variant->id,
                    quantity: '10.0000',
                    unitCost: '100.0000'
                ),
            ]
        );

        $this->transferService->shipTransfer($transfer->id);

        $lineId = $transfer->lines->first()->id;
        $received = $this->transferService->receiveTransfer(
            transferId: $transfer->id,
            receipts: [$lineId => '8.0000']
        );

        $this->assertEquals('received', $received->status);

        $receivedLine = $received->lines()->where('id', $lineId)->firstOrFail();
        $this->assertEquals('10.0000', $receivedLine->quantity_shipped);
        $this->assertEquals('8.0000', $receivedLine->quantity_received);
        $this->assertEquals('2.0000', $receivedLine->discrepancy_quantity);

        // Dest balance has exactly 8.0000
        $destBal = StockBalance::where('warehouse_id', $this->destWarehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->value('quantity_on_hand');
        $this->assertEquals('8.0000', $destBal);
    }

    public function test_shipping_transfer_fails_when_stock_insufficient(): void
    {
        // Try to transfer 999 units when only 50 available
        $transfer = $this->transferService->createTransfer(
            companyId: $this->company->id,
            sourceWarehouseId: $this->sourceWarehouse->id,
            destWarehouseId: $this->destWarehouse->id,
            items: [
                new StockTransferItemDTO(
                    productVariantId: $this->variant->id,
                    quantity: '999.0000',
                    unitCost: '100.0000'
                ),
            ]
        );

        $this->expectException(InsufficientStockException::class);
        $this->transferService->shipTransfer($transfer->id);
    }
}
