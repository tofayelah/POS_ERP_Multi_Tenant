<?php

namespace Tests\Feature\Filament;

use App\Core\Tenancy\TenantContext;
use App\Filament\Resources\LandedCosts\LandedCostVoucherResource;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\JournalEntry;
use App\Models\LandedCostVoucher;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Inventory\Contracts\LandedCostAllocationContract;
use App\Modules\Inventory\Contracts\StockMovementServiceContract;
use App\Modules\Inventory\DTOs\CreateLandedCostVoucherDTO;
use App\Modules\Inventory\DTOs\CreateLandedCostVoucherLineDTO;
use App\Modules\Inventory\DTOs\StockMovementDTO;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LandedCostAllocationTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected Warehouse $warehouse;

    protected Supplier $supplier;

    protected ProductVariant $variantA;

    protected ProductVariant $variantB;

    protected User $user;

    protected ChartOfAccount $inventoryAccount;

    protected ChartOfAccount $clearingAccount;

    protected ChartOfAccount $cogsAccount;

    protected AccountingPeriod $period;

    protected LandedCostAllocationContract $allocationService;

    protected StockMovementServiceContract $movementService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Landed Cost Test Tenant',
            'subdomain' => 'lc-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Landed Cost Corp',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'LC-BR-01',
            'name' => 'Chittagong Port Branch',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'code' => 'LC-WH-01',
            'name' => 'Customs Bonded Warehouse',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Procurement Officer',
            'email' => 'officer-'.Str::random(6).'@kcerp.com',
            'password' => 'Secret123!',
        ]);

        $this->supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'SUP-LC-01',
            'name' => 'Overseas Commodity Supplier Ltd',
            'currency' => 'BDT',
            'is_active' => true,
        ]);

        $taxCode = TaxCode::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'VAT-STD',
            'name_en' => 'Standard VAT 15%',
            'tax_type' => 'standard_vat',
            'rate' => '15.0000',
            'is_active' => true,
        ]);

        $productA = Product::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'PROD-A',
            'name_en' => 'Bulk Commodity Alpha',
            'base_uom' => 'KG',
            'is_active' => true,
        ]);

        $this->variantA = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $productA->id,
            'tax_code_id' => $taxCode->id,
            'sku' => 'SKU-A-001',
            'name_en' => 'Commodity Alpha Grade 1',
            'base_uom' => 'KG',
            'cost_price' => '100.0000',
            'mrp' => '150.0000',
            'is_active' => true,
        ]);

        $productB = Product::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'PROD-B',
            'name_en' => 'Bulk Commodity Beta',
            'base_uom' => 'KG',
            'is_active' => true,
        ]);

        $this->variantB = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $productB->id,
            'tax_code_id' => $taxCode->id,
            'sku' => 'SKU-B-001',
            'name_en' => 'Commodity Beta Grade 1',
            'base_uom' => 'KG',
            'cost_price' => '300.0000',
            'mrp' => '450.0000',
            'is_active' => true,
        ]);

        $fy = FiscalYear::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'title' => 'FY 2026-2027',
            'start_date' => Carbon::now()->startOfYear()->toDateString(),
            'end_date' => Carbon::now()->endOfYear()->toDateString(),
            'is_closed' => false,
        ]);

        $this->period = AccountingPeriod::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'fiscal_year_id' => $fy->id,
            'period_number' => 1,
            'period_name' => Carbon::now()->format('F Y'),
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->endOfMonth()->toDateString(),
            'status' => 'open',
        ]);

        $this->inventoryAccount = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '1040',
            'account_name' => 'Merchandise Inventory Asset',
            'account_type' => 'asset',
            'is_active' => true,
        ]);

        $this->clearingAccount = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '2050',
            'account_name' => 'Landed Cost Clearing Account',
            'account_type' => 'liability',
            'is_active' => true,
        ]);

        $this->cogsAccount = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '5010',
            'account_name' => 'Cost of Goods Sold',
            'account_type' => 'expense',
            'is_active' => true,
        ]);

        $this->movementService = app(StockMovementServiceContract::class);
        $this->allocationService = app(LandedCostAllocationContract::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_landed_cost_voucher_resource_navigation_and_model(): void
    {
        $this->assertEquals(LandedCostVoucher::class, LandedCostVoucherResource::getModel());
        $this->assertEquals('Procurement & Purchasing', LandedCostVoucherResource::getNavigationGroup());
    }

    public function test_landed_cost_allocation_by_value_updates_inventory_avco_and_posts_gl(): void
    {
        // 1. Initial Goods Receipt: 100 units of A @ 100 BDT (=10,000), 100 units of B @ 300 BDT (=30,000). Total value = 40,000 BDT
        $grn = GoodsReceipt::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'grn_number' => 'GRN-2026-VAL-01',
            'receipt_date' => now()->toDateString(),
            'status' => 'confirmed',
            'created_by' => $this->user->id,
        ]);

        $grnLineA = GoodsReceiptLine::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'goods_receipt_id' => $grn->id,
            'product_id' => $this->variantA->product_id,
            'variant_id' => $this->variantA->id,
            'received_quantity' => '100.0000',
            'billed_quantity' => '0.0000',
            'unit_cost' => '100.0000',
        ]);

        $grnLineB = GoodsReceiptLine::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'goods_receipt_id' => $grn->id,
            'product_id' => $this->variantB->product_id,
            'variant_id' => $this->variantB->id,
            'received_quantity' => '100.0000',
            'billed_quantity' => '0.0000',
            'unit_cost' => '300.0000',
        ]);

        // Record inbound movements for stock balance
        DB::transaction(function () use ($grn) {
            $this->movementService->recordMovement(new StockMovementDTO(
                companyId: $this->company->id,
                warehouseId: $this->warehouse->id,
                stockLocationId: null,
                productVariantId: $this->variantA->id,
                movementType: 'PURCHASE_RECEIPT',
                quantity: '100.0000',
                unitCost: '100.0000',
                referenceType: 'goods_receipt',
                referenceId: $grn->id
            ));

            $this->movementService->recordMovement(new StockMovementDTO(
                companyId: $this->company->id,
                warehouseId: $this->warehouse->id,
                stockLocationId: null,
                productVariantId: $this->variantB->id,
                movementType: 'PURCHASE_RECEIPT',
                quantity: '100.0000',
                unitCost: '300.0000',
                referenceType: 'goods_receipt',
                referenceId: $grn->id
            ));
        });

        // 2. Issue Landed Cost Voucher of 4,000 BDT allocated pro-rata by value:
        // Item A value = 10,000 / 40,000 = 25% -> 1,000 BDT allocated. Additional unit cost = 10.0000. New WAC = 110.0000
        // Item B value = 30,000 / 40,000 = 75% -> 3,000 BDT allocated. Additional unit cost = 30.0000. New WAC = 330.0000
        $voucher = $this->allocationService->createVoucher(new CreateLandedCostVoucherDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            voucherDate: now()->toDateString(),
            allocationMethod: 'by_value',
            currency: 'BDT',
            exchangeRate: '1.000000',
            notes: 'Import Freight and Customs for Shipment Alpha/Beta',
            goodsReceiptIds: [$grn->id],
            lines: [
                new CreateLandedCostVoucherLineDTO(
                    expenseType: 'freight',
                    description: 'Ocean Freight Carrier Fee',
                    amount: '3000.0000'
                ),
                new CreateLandedCostVoucherLineDTO(
                    expenseType: 'customs_duty',
                    description: 'Customs Tariff and Port Demurrage',
                    amount: '1000.0000'
                ),
            ],
            createdBy: $this->user->id
        ));

        $this->assertEquals('draft', $voucher->status);
        $this->assertEquals('4000.0000', $voucher->total_cost);
        $this->assertCount(2, $voucher->allocations);

        // 3. Post the Landed Cost Voucher
        $posted = $this->allocationService->postVoucher(
            voucherId: $voucher->id,
            postedBy: $this->user->id,
            periodId: $this->period->id,
            inventoryValuationAccountId: $this->inventoryAccount->id,
            landedCostClearingAccountId: $this->clearingAccount->id,
            cogsAccountId: $this->cogsAccount->id
        );

        $this->assertEquals('posted', $posted->status);
        $this->assertNotNull($posted->posted_at);
        $this->assertNotNull($posted->gl_journal_entry_id);

        // 4. Assert updated AVCO in stock balances
        $balanceA = StockBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variantA->id)
            ->firstOrFail();
        $this->assertEquals('110.0000', $balanceA->weighted_avg_cost);

        $balanceB = StockBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variantB->id)
            ->firstOrFail();
        $this->assertEquals('330.0000', $balanceB->weighted_avg_cost);

        // 5. Assert posted GL journal entry
        $journal = JournalEntry::findOrFail($posted->gl_journal_entry_id);
        $this->assertTrue($journal->is_posted);
        $this->assertCount(2, $journal->lines);

        $invLine = $journal->lines->where('account_id', $this->inventoryAccount->id)->firstOrFail();
        $this->assertEquals('4000.0000', $invLine->debit_amount);
        $this->assertEquals('0.0000', $invLine->credit_amount);

        $clrLine = $journal->lines->where('account_id', $this->clearingAccount->id)->firstOrFail();
        $this->assertEquals('0.0000', $clrLine->debit_amount);
        $this->assertEquals('4000.0000', $clrLine->credit_amount);
    }

    public function test_landed_cost_allocation_by_quantity(): void
    {
        // Item A: 100 units @ 100 BDT
        // Item B: 300 units @ 100 BDT
        // Total Qty = 400 units
        // Landed Cost = 2,000 BDT allocated by quantity
        // Item A share = 100 / 400 * 2000 = 500 BDT -> additional unit cost = 5.0000 -> WAC = 105.0000
        // Item B share = 300 / 400 * 2000 = 1500 BDT -> additional unit cost = 5.0000 -> WAC = 105.0000
        $grn = GoodsReceipt::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'grn_number' => 'GRN-2026-QTY-01',
            'receipt_date' => now()->toDateString(),
            'status' => 'confirmed',
            'created_by' => $this->user->id,
        ]);

        GoodsReceiptLine::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'goods_receipt_id' => $grn->id,
            'product_id' => $this->variantA->product_id,
            'variant_id' => $this->variantA->id,
            'received_quantity' => '100.0000',
            'billed_quantity' => '0.0000',
            'unit_cost' => '100.0000',
        ]);

        GoodsReceiptLine::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'goods_receipt_id' => $grn->id,
            'product_id' => $this->variantB->product_id,
            'variant_id' => $this->variantB->id,
            'received_quantity' => '300.0000',
            'billed_quantity' => '0.0000',
            'unit_cost' => '100.0000',
        ]);

        DB::transaction(function () use ($grn) {
            $this->movementService->recordMovement(new StockMovementDTO(
                companyId: $this->company->id,
                warehouseId: $this->warehouse->id,
                stockLocationId: null,
                productVariantId: $this->variantA->id,
                movementType: 'PURCHASE_RECEIPT',
                quantity: '100.0000',
                unitCost: '100.0000',
                referenceType: 'goods_receipt',
                referenceId: $grn->id
            ));

            $this->movementService->recordMovement(new StockMovementDTO(
                companyId: $this->company->id,
                warehouseId: $this->warehouse->id,
                stockLocationId: null,
                productVariantId: $this->variantB->id,
                movementType: 'PURCHASE_RECEIPT',
                quantity: '300.0000',
                unitCost: '100.0000',
                referenceType: 'goods_receipt',
                referenceId: $grn->id
            ));
        });

        $voucher = $this->allocationService->createVoucher(new CreateLandedCostVoucherDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            voucherDate: now()->toDateString(),
            allocationMethod: 'by_quantity',
            currency: 'BDT',
            exchangeRate: '1.000000',
            notes: 'Port Handling Fee',
            goodsReceiptIds: [$grn->id],
            lines: [
                new CreateLandedCostVoucherLineDTO(
                    expenseType: 'port_handling',
                    description: 'Port Container Unloading',
                    amount: '2000.0000'
                ),
            ],
            createdBy: $this->user->id
        ));

        $posted = $this->allocationService->postVoucher(
            voucherId: $voucher->id,
            postedBy: $this->user->id,
            periodId: $this->period->id,
            inventoryValuationAccountId: $this->inventoryAccount->id,
            landedCostClearingAccountId: $this->clearingAccount->id,
            cogsAccountId: $this->cogsAccount->id
        );

        $this->assertEquals('posted', $posted->status);

        $balanceA = StockBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variantA->id)
            ->firstOrFail();
        $this->assertEquals('105.0000', $balanceA->weighted_avg_cost);

        $balanceB = StockBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variantB->id)
            ->firstOrFail();
        $this->assertEquals('105.0000', $balanceB->weighted_avg_cost);
    }

    public function test_landed_cost_late_arrival_when_goods_already_sold_expenses_directly_to_cogs(): void
    {
        // 1. Initial GRN of 50 units @ 100 BDT
        $grn = GoodsReceipt::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'grn_number' => 'GRN-2026-COGS-01',
            'receipt_date' => now()->toDateString(),
            'status' => 'confirmed',
            'created_by' => $this->user->id,
        ]);

        GoodsReceiptLine::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'goods_receipt_id' => $grn->id,
            'product_id' => $this->variantA->product_id,
            'variant_id' => $this->variantA->id,
            'received_quantity' => '50.0000',
            'billed_quantity' => '0.0000',
            'unit_cost' => '100.0000',
        ]);

        // Inbound receipt
        DB::transaction(fn () => $this->movementService->recordMovement(new StockMovementDTO(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            stockLocationId: null,
            productVariantId: $this->variantA->id,
            movementType: 'PURCHASE_RECEIPT',
            quantity: '50.0000',
            unitCost: '100.0000',
            referenceType: 'goods_receipt',
            referenceId: $grn->id
        )));

        // 2. All 50 units sold out before freight voucher arrives
        DB::transaction(fn () => $this->movementService->recordMovement(new StockMovementDTO(
            companyId: $this->company->id,
            warehouseId: $this->warehouse->id,
            stockLocationId: null,
            productVariantId: $this->variantA->id,
            movementType: 'SALE',
            quantity: '-50.0000',
            unitCost: '100.0000',
            referenceType: 'sales_invoice',
            referenceId: (string) Str::uuid()
        )));

        // On hand is now 0
        $balance = StockBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variantA->id)
            ->firstOrFail();
        $this->assertEquals('0.0000', $balance->quantity_on_hand);

        // 3. Late landed cost arrives: 500 BDT
        $voucher = $this->allocationService->createVoucher(new CreateLandedCostVoucherDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            voucherDate: now()->toDateString(),
            allocationMethod: 'by_value',
            currency: 'BDT',
            exchangeRate: '1.000000',
            notes: 'Late Customs Assessment',
            goodsReceiptIds: [$grn->id],
            lines: [
                new CreateLandedCostVoucherLineDTO(
                    expenseType: 'customs_duty',
                    description: 'Delayed Customs Audit Fee',
                    amount: '500.0000'
                ),
            ],
            createdBy: $this->user->id
        ));

        $posted = $this->allocationService->postVoucher(
            voucherId: $voucher->id,
            postedBy: $this->user->id,
            periodId: $this->period->id,
            inventoryValuationAccountId: $this->inventoryAccount->id,
            landedCostClearingAccountId: $this->clearingAccount->id,
            cogsAccountId: $this->cogsAccount->id
        );

        $this->assertEquals('posted', $posted->status);

        $alloc = $posted->allocations->first();
        $this->assertEquals('0.0000', $alloc->adjustment_to_inventory);
        $this->assertEquals('500.0000', $alloc->adjustment_to_cogs);

        // Verify journal debits COGS (5010) and credits Clearing (2050)
        $journal = JournalEntry::findOrFail($posted->gl_journal_entry_id);
        $this->assertTrue($journal->is_posted);

        $cogsLine = $journal->lines->where('account_id', $this->cogsAccount->id)->firstOrFail();
        $this->assertEquals('500.0000', $cogsLine->debit_amount);

        $clrLine = $journal->lines->where('account_id', $this->clearingAccount->id)->firstOrFail();
        $this->assertEquals('500.0000', $clrLine->credit_amount);
    }

    public function test_tenant_isolation_on_landed_cost_vouchers(): void
    {
        // Create voucher in Tenant A
        $grn = GoodsReceipt::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'grn_number' => 'GRN-2026-ISO-01',
            'receipt_date' => now()->toDateString(),
            'status' => 'confirmed',
            'created_by' => $this->user->id,
        ]);

        GoodsReceiptLine::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'goods_receipt_id' => $grn->id,
            'product_id' => $this->variantA->product_id,
            'variant_id' => $this->variantA->id,
            'received_quantity' => '10.0000',
            'billed_quantity' => '0.0000',
            'unit_cost' => '100.0000',
        ]);

        $voucher = $this->allocationService->createVoucher(new CreateLandedCostVoucherDTO(
            companyId: $this->company->id,
            branchId: $this->branch->id,
            voucherDate: now()->toDateString(),
            allocationMethod: 'by_value',
            goodsReceiptIds: [$grn->id],
            lines: [
                new CreateLandedCostVoucherLineDTO(
                    expenseType: 'freight',
                    description: 'Freight for Tenant A',
                    amount: '100.0000'
                ),
            ],
            createdBy: $this->user->id
        ));

        // Switch to Tenant B
        $tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Competitor Tenant B',
            'subdomain' => 'comp-'.Str::random(6),
            'status' => 'active',
        ]);
        TenantContext::set($tenantB);

        // Tenant B must not see Tenant A's voucher via Eloquent (Forced RLS)
        $this->assertNull(LandedCostVoucher::find($voucher->id));
        $this->assertCount(0, LandedCostVoucher::where('id', $voucher->id)->get());
    }
}
