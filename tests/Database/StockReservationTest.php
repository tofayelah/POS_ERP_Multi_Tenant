<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Modules\Inventory\Contracts\StockMovementServiceContract;
use App\Modules\Inventory\Contracts\StockReservationServiceContract;
use App\Modules\Inventory\DTOs\StockMovementDTO;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockReservationTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Warehouse $warehouse;

    protected ProductVariant $variant;

    protected StockReservationServiceContract $reservationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Reservation Tenant',
            'subdomain' => 'res-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Reservation Company',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'WH-RES-01',
            'name' => 'Reservation Warehouse',
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
            'code' => 'PROD-RES-01',
            'name_en' => 'Reservation Product',
            'base_uom' => 'Pcs',
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'tax_code_id' => $taxCode->id,
            'sku' => 'SKU-RES-001',
            'name_en' => 'Reservation Variant',
            'base_uom' => 'Pcs',
            'cost_price' => '100.0000',
            'mrp' => '150.0000',
            'is_active' => true,
        ]);

        // Seed 10 units initial stock
        /** @var StockMovementServiceContract $movementService */
        $movementService = app(StockMovementServiceContract::class);
        DB::transaction(fn () => $movementService->recordMovement(new StockMovementDTO(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            stockLocationId: null,
            productVariantId: $this->variant->id,
            movementType: 'PURCHASE_RECEIPT',
            quantity: '10.0000',
            unitCost: '100.0000',
            referenceType: 'initial_stock',
            referenceId: (string) Str::uuid()
        )));

        $this->reservationService = app(StockReservationServiceContract::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_reserving_stock_increments_reserved_quantity_and_reduces_available(): void
    {
        $docId = (string) Str::uuid();

        $reservation = $this->reservationService->reserveStock(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            stockLocationId: null,
            productVariantId: $this->variant->id,
            quantity: '4.0000',
            documentType: 'cart',
            documentId: $docId,
            ttlMinutes: 15
        );

        $this->assertEquals('active', $reservation->status);
        $this->assertEquals('4.0000', $reservation->quantity);

        $balance = StockBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->firstOrFail();

        $this->assertEquals('10.0000', $balance->quantity_on_hand);
        $this->assertEquals('4.0000', $balance->quantity_reserved);
        $this->assertEquals('6.0000', $balance->quantity_available);
    }

    public function test_reserving_more_than_available_stock_throws_exception(): void
    {
        $this->expectException(InsufficientStockException::class);

        // Already have 10 on hand, attempting to reserve 15
        $this->reservationService->reserveStock(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            stockLocationId: null,
            productVariantId: $this->variant->id,
            quantity: '15.0000',
            documentType: 'cart',
            documentId: (string) Str::uuid()
        );
    }

    public function test_expired_reservations_are_automatically_released(): void
    {
        // Reserve 5 units with past expiry
        $reservation = $this->reservationService->reserveStock(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            stockLocationId: null,
            productVariantId: $this->variant->id,
            quantity: '5.0000',
            documentType: 'cart',
            documentId: (string) Str::uuid(),
            ttlMinutes: -5 // already expired
        );

        $balanceBefore = StockBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->firstOrFail();
        $this->assertEquals('5.0000', $balanceBefore->quantity_available);

        // Act: release expired
        $releasedCount = $this->reservationService->releaseExpiredReservations();

        $this->assertGreaterThanOrEqual(1, $releasedCount);

        $reservation->refresh();
        $this->assertEquals('expired', $reservation->status);

        $balanceAfter = StockBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->firstOrFail();
        $this->assertEquals('0.0000', $balanceAfter->quantity_reserved);
        $this->assertEquals('10.0000', $balanceAfter->quantity_available);
    }
}
