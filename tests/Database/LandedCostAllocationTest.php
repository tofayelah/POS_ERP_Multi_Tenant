<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Modules\Inventory\Contracts\LandedCostServiceContract;
use App\Modules\Inventory\Contracts\StockMovementServiceContract;
use App\Modules\Inventory\DTOs\LandedCostAllocationDTO;
use App\Modules\Inventory\DTOs\StockMovementDTO;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LandedCostAllocationTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Warehouse $warehouse;

    protected ProductVariant $variant;

    protected LandedCostServiceContract $landedCostService;

    protected StockMovementServiceContract $movementService;

    protected ChartOfAccount $cogsAccount;

    protected ChartOfAccount $apAccrualAccount;

    protected AccountingPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Landed Cost Tenant',
            'subdomain' => 'landed-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Landed Cost Test Company',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'WH-LC-01',
            'name' => 'Landed Cost Warehouse',
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
            'code' => 'PROD-LC-01',
            'name_en' => 'Landed Cost Product',
            'base_uom' => 'Pcs',
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'tax_code_id' => $taxCode->id,
            'sku' => 'SKU-LC-001',
            'name_en' => 'Landed Cost Variant',
            'base_uom' => 'Pcs',
            'cost_price' => '100.0000',
            'mrp' => '150.0000',
            'is_active' => true,
        ]);

        $fy = FiscalYear::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'title' => 'FY 2026-2027',
            'start_date' => Carbon::parse('2026-07-01'),
            'end_date' => Carbon::parse('2027-06-30'),
            'is_closed' => false,
        ]);

        $this->period = AccountingPeriod::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'fiscal_year_id' => $fy->id,
            'period_number' => 4,
            'period_name' => 'October 2026',
            'start_date' => Carbon::parse('2026-10-01'),
            'end_date' => Carbon::parse('2026-10-31'),
            'status' => 'open',
        ]);

        $this->cogsAccount = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '5010',
            'account_name' => 'Cost of Goods Sold',
            'account_type' => 'expense',
            'is_active' => true,
        ]);

        $this->apAccrualAccount = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '2010',
            'account_name' => 'Accounts Payable Accrual',
            'account_type' => 'liability',
            'is_active' => true,
        ]);

        $this->movementService = app(StockMovementServiceContract::class);
        $this->landedCostService = app(LandedCostServiceContract::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_landed_cost_allocated_to_in_stock_inventory_increases_wac(): void
    {
        // 1. Initial receipt: 10 units @ 100 BDT
        DB::transaction(fn () => $this->movementService->recordMovement(new StockMovementDTO(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            stockLocationId: null,
            productVariantId: $this->variant->id,
            movementType: 'PURCHASE_RECEIPT',
            quantity: '10.0000',
            unitCost: '100.0000',
            referenceType: 'purchase_receipt',
            referenceId: (string) Str::uuid()
        )));

        // 2. Freight invoice arrives: 200 BDT freight allocated to this 10 units
        // Result: 200 BDT / 10 units = 20 BDT per unit additional cost. WAC should become 120.0000
        $landedCost = $this->landedCostService->createLandedCost(
            companyId: $this->company->id,
            costType: 'freight',
            amount: '200.0000',
            allocationMethod: 'by_quantity',
            allocations: [
                new LandedCostAllocationDTO(
                    productVariantId: $this->variant->id,
                    warehouseId: $this->warehouse->id,
                    allocatedAmount: '200.0000'
                ),
            ]
        );

        $posted = $this->landedCostService->postLandedCost($landedCost->id);

        $this->assertEquals('posted', $posted->status);

        $allocation = $posted->allocations->first();
        $this->assertEquals('200.0000', $allocation->adjustment_to_inventory);
        $this->assertEquals('0.0000', $allocation->adjustment_to_cogs);

        // Assert StockBalance WAC updated to 120.0000
        $balance = StockBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->firstOrFail();

        $this->assertEquals('120.0000', $balance->weighted_avg_cost);
    }

    public function test_landed_cost_late_arrival_when_goods_already_sold_expenses_directly_to_cogs(): void
    {
        // 1. Initial receipt: 5 units @ 100 BDT
        DB::transaction(fn () => $this->movementService->recordMovement(new StockMovementDTO(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            stockLocationId: null,
            productVariantId: $this->variant->id,
            movementType: 'PURCHASE_RECEIPT',
            quantity: '5.0000',
            unitCost: '100.0000',
            referenceType: 'purchase_receipt',
            referenceId: (string) Str::uuid()
        )));

        // 2. Customer buys all 5 units -> Stock goes to 0 on hand
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

        // 3. Late freight bill arrives: 150 BDT
        $landedCost = $this->landedCostService->createLandedCost(
            companyId: $this->company->id,
            costType: 'customs',
            amount: '150.0000',
            allocationMethod: 'by_quantity',
            allocations: [
                new LandedCostAllocationDTO(
                    productVariantId: $this->variant->id,
                    warehouseId: $this->warehouse->id,
                    allocatedAmount: '150.0000'
                ),
            ]
        );

        // 4. Post landed cost with period and accounts
        $posted = $this->landedCostService->postLandedCost(
            landedCostId: $landedCost->id,
            periodId: $this->period->id,
            cogsAccountId: $this->cogsAccount->id,
            apAccrualAccountId: $this->apAccrualAccount->id,
            postedBy: (string) Str::uuid()
        );

        $this->assertEquals('posted', $posted->status);

        $allocation = $posted->allocations->first();
        // Since on_hand was 0, zero goes to inventory and 100% goes to COGS
        $this->assertEquals('0.0000', $allocation->adjustment_to_inventory);
        $this->assertEquals('150.0000', $allocation->adjustment_to_cogs);

        // Verify direct COGS adjustment journal was posted
        $journal = JournalEntry::where('reference_type', 'landed_cost')
            ->where('reference_id', $landedCost->id)
            ->firstOrFail();

        $this->assertTrue($journal->is_posted);
        $this->assertCount(2, $journal->lines);

        $debitLine = $journal->lines->where('account_id', $this->cogsAccount->id)->firstOrFail();
        $this->assertEquals('150.00', $debitLine->debit_amount);

        $creditLine = $journal->lines->where('account_id', $this->apAccrualAccount->id)->firstOrFail();
        $this->assertEquals('150.00', $creditLine->credit_amount);
    }
}
