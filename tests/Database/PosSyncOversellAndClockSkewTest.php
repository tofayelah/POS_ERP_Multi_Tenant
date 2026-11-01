<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockOversellException;
use App\Models\TaxCode;
use App\Models\TaxCodeRate;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Pos\Contracts\PosSyncServiceContract;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PosSyncOversellAndClockSkewTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected Warehouse $warehouse;

    protected PosTerminal $terminal;

    protected User $cashier;

    protected FiscalYear $fiscalYear;

    protected AccountingPeriod $period;

    protected TaxCode $taxCodeFifteen;

    protected ProductVariant $variant;

    protected PosSyncServiceContract $syncService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Oversell Skew Tenant',
            'subdomain' => 'skew-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        Role::firstOrCreate([
            'name' => 'Tenant Admin',
            'guard_name' => 'web',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Retail Oversell Mart Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Dhanmondi Branch',
            'code' => 'DHA-01',
        ]);

        $this->warehouse = Warehouse::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'WH-DHA-01',
            'name' => 'Dhanmondi Central Depot',
            'is_active' => true,
        ]);

        $this->terminal = PosTerminal::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'terminal_code' => 'T-DHA-01',
            'name' => 'Checkout Counter 1',
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Oversell Cashier',
            'email' => 'cashier-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);
        $this->cashier->assignRole('Tenant Admin');

        TenantMembership::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->cashier->id,
            'is_active' => true,
        ]);

        $currentYear = Carbon::now()->year;
        $this->fiscalYear = FiscalYear::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'title' => "FY {$currentYear}-".($currentYear + 1),
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->addYear()->endOfMonth()->toDateString(),
        ]);

        $this->period = AccountingPeriod::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'period_number' => 1,
            'period_name' => Carbon::now()->format('F Y'),
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->endOfMonth()->toDateString(),
            'status' => 'open',
        ]);

        // General Ledger Accounts
        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '1010',
            'account_name' => 'Cash on Hand',
            'account_type' => 'asset',
            'is_reconcilable' => true,
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '1020',
            'account_name' => 'Bank Current Account',
            'account_type' => 'asset',
            'is_reconcilable' => true,
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '1030',
            'account_name' => 'Trade Debtors',
            'account_type' => 'asset',
            'is_reconcilable' => true,
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '2010',
            'account_name' => 'Trade Creditors',
            'account_type' => 'liability',
            'is_reconcilable' => true,
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '2020',
            'account_name' => 'VAT Output Payable',
            'account_type' => 'liability',
            'is_reconcilable' => true,
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '4010',
            'account_name' => 'Retail Sales Revenue',
            'account_type' => 'revenue',
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '1040',
            'account_name' => 'Merchandise Inventory Asset',
            'account_type' => 'asset',
            'is_reconcilable' => true,
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '5010',
            'account_name' => 'Cost of Goods Sold',
            'account_type' => 'expense',
            'is_active' => true,
        ]);

        $this->taxCodeFifteen = TaxCode::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'VAT-15',
            'name_en' => 'Standard VAT 15%',
            'tax_type' => 'standard_vat',
            'is_active' => true,
        ]);

        TaxCodeRate::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'tax_code_id' => $this->taxCodeFifteen->id,
            'rate_percent' => '15.00',
            'supplementary_duty_percent' => '0.00',
            'effective_from' => '2020-01-01',
            'effective_to' => null,
        ]);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'PRD-OS-01',
            'name_en' => 'Oversell Test Product',
            'base_uom' => 'Pcs',
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'tax_code_id' => $this->taxCodeFifteen->id,
            'sku' => 'SKU-OS-001',
            'name_en' => 'Oversell Variant 1',
            'base_uom' => 'Pcs',
            'cost_price' => '100.0000',
            'mrp' => '200.0000',
            'is_active' => true,
        ]);

        // Establish initial stock balance = 3.0000 units on hand
        StockBalance::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity_on_hand' => '3.0000',
            'quantity_reserved' => '0.0000',
            'weighted_avg_cost' => '100.0000',
        ]);

        $this->syncService = app(PosSyncServiceContract::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_offline_oversell_syncs_successfully_and_opens_exception_adr016(): void
    {
        $this->actingAs($this->cashier);

        $clientTxnId = 'txn-oversell-'.Str::random(8);

        // Client sells 10 units offline when on-hand is only 3 units
        $payload = [
            'batch_id' => (string) Str::uuid(),
            'terminal_id' => $this->terminal->id,
            'sales' => [
                [
                    'client_txn_id' => $clientTxnId,
                    'device_time' => Carbon::now()->toIso8601String(),
                    'pricing_basis' => 'tax_inclusive',
                    'payment_status' => 'paid',
                    'items' => [
                        [
                            'variant_id' => $this->variant->id,
                            'quantity' => '10.0000',
                            'unit_price' => '230.0000',
                            'tax_code_id' => $this->taxCodeFifteen->id,
                            'flat_tax_rate' => '15.0000',
                            'discount_amount' => '0.0000',
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->syncService->syncBatch($payload, '1.2.0');

        $this->assertSame('success', $response['status']);
        $this->assertCount(1, $response['results']);
        $this->assertSame('committed', $response['results'][0]['status']);
        $this->assertNotNull($response['results'][0]['invoice_id']);

        $invoiceId = $response['results'][0]['invoice_id'];

        // 1. Verify SalesInvoice was created and issued
        $invoice = SalesInvoice::findOrFail($invoiceId);
        $this->assertSame('issued', $invoice->status);
        $this->assertTrue($invoice->is_synced_from_offline);

        // 2. Verify Stock Balance transitioned to negative balance -7.0000 (3 on hand - 10 sold = -7)
        $stockBalance = StockBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->firstOrFail();

        $this->assertSame('-7.0000', (string) $stockBalance->quantity_on_hand);

        // 3. Verify StockMovement was recorded with OFFLINE_OVERSELL and -10.0000
        $movement = StockMovement::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->where('reference_type', 'sales_invoice')
            ->where('reference_id', $invoiceId)
            ->firstOrFail();

        $this->assertSame('OFFLINE_OVERSELL', $movement->movement_type);
        $this->assertSame('-10.0000', (string) $movement->quantity);

        // 4. Verify StockOversellException was logged with status OPEN and oversold = 7.0000
        $exception = StockOversellException::where('sales_invoice_id', $invoiceId)
            ->where('product_variant_id', $this->variant->id)
            ->firstOrFail();

        $this->assertSame('OPEN', $exception->status);
        $this->assertSame('7.0000', (string) $exception->quantity_oversold);
        $this->assertSame($this->warehouse->id, $exception->warehouse_id);
        $this->assertSame($this->branch->id, $exception->branch_id);

        // 5. Verify General Ledger trial balance for this transaction balances (debit == credit)
        $journalEntry = JournalEntry::where('reference_id', $invoiceId)->firstOrFail();
        $this->assertTrue((bool) $journalEntry->is_posted);

        $totals = JournalLine::where('journal_entry_id', $journalEntry->id)
            ->selectRaw('SUM(debit_amount) as total_debits, SUM(credit_amount) as total_credits')
            ->first();

        $this->assertNotNull($totals);
        $this->assertGreaterThan(0, (float) $totals->total_debits);
        $this->assertSame((string) $totals->total_debits, (string) $totals->total_credits);
    }

    public function test_clock_skew_flagging_when_device_time_exceeds_threshold_pos10(): void
    {
        $this->actingAs($this->cashier);

        $clientTxnId = 'txn-skew-flagged-'.Str::random(8);

        // Device time is 15 minutes (900 seconds) in the past (> 300s threshold)
        $pastDeviceTime = Carbon::now()->subMinutes(15);

        $payload = [
            'batch_id' => (string) Str::uuid(),
            'terminal_id' => $this->terminal->id,
            'sales' => [
                [
                    'client_txn_id' => $clientTxnId,
                    'device_time' => $pastDeviceTime->toIso8601String(),
                    'pricing_basis' => 'tax_inclusive',
                    'payment_status' => 'paid',
                    'items' => [
                        [
                            'variant_id' => $this->variant->id,
                            'quantity' => '1.0000',
                            'unit_price' => '230.0000',
                            'tax_code_id' => $this->taxCodeFifteen->id,
                            'flat_tax_rate' => '15.0000',
                            'discount_amount' => '0.0000',
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->syncService->syncBatch($payload, '1.2.0');

        $this->assertSame('success', $response['status']);
        $this->assertTrue($response['results'][0]['clock_skew_flagged']);

        $invoiceId = $response['results'][0]['invoice_id'];
        $invoice = SalesInvoice::findOrFail($invoiceId);

        $this->assertTrue($invoice->is_clock_skew_flagged);
        $this->assertNotNull($invoice->clock_skew_seconds);
        $this->assertGreaterThanOrEqual(890, $invoice->clock_skew_seconds);
        $this->assertNotNull($invoice->device_time);
        $this->assertNotNull($invoice->server_received_time);
    }

    public function test_clock_skew_not_flagged_when_within_threshold(): void
    {
        $this->actingAs($this->cashier);

        $clientTxnId = 'txn-skew-normal-'.Str::random(8);

        // Device time is within 10 seconds of current server time
        $normalDeviceTime = Carbon::now()->subSeconds(10);

        $payload = [
            'batch_id' => (string) Str::uuid(),
            'terminal_id' => $this->terminal->id,
            'sales' => [
                [
                    'client_txn_id' => $clientTxnId,
                    'device_time' => $normalDeviceTime->toIso8601String(),
                    'pricing_basis' => 'tax_inclusive',
                    'payment_status' => 'paid',
                    'items' => [
                        [
                            'variant_id' => $this->variant->id,
                            'quantity' => '1.0000',
                            'unit_price' => '230.0000',
                            'tax_code_id' => $this->taxCodeFifteen->id,
                            'flat_tax_rate' => '15.0000',
                            'discount_amount' => '0.0000',
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->syncService->syncBatch($payload, '1.2.0');

        $this->assertSame('success', $response['status']);
        $this->assertFalse($response['results'][0]['clock_skew_flagged']);

        $invoiceId = $response['results'][0]['invoice_id'];
        $invoice = SalesInvoice::findOrFail($invoiceId);

        $this->assertFalse($invoice->is_clock_skew_flagged);
        $this->assertNotNull($invoice->clock_skew_seconds);
        $this->assertLessThanOrEqual(60, $invoice->clock_skew_seconds);
    }

    public function test_statutory_fiscal_enrichment_whitelist_path_and_trigger4_compliance(): void
    {
        $this->actingAs($this->cashier);

        $clientTxnId = 'txn-fiscal-'.Str::random(8);

        // Step 1: Initial offline sync without fiscal details (e.g. SDC dongle was disconnected)
        $payload1 = [
            'batch_id' => (string) Str::uuid(),
            'terminal_id' => $this->terminal->id,
            'sales' => [
                [
                    'client_txn_id' => $clientTxnId,
                    'device_time' => Carbon::now()->toIso8601String(),
                    'pricing_basis' => 'tax_inclusive',
                    'payment_status' => 'paid',
                    'items' => [
                        [
                            'variant_id' => $this->variant->id,
                            'quantity' => '1.0000',
                            'unit_price' => '230.0000',
                            'tax_code_id' => $this->taxCodeFifteen->id,
                            'flat_tax_rate' => '15.0000',
                            'discount_amount' => '0.0000',
                        ],
                    ],
                ],
            ],
        ];

        $res1 = $this->syncService->syncBatch($payload1, '1.2.0');
        $this->assertSame('committed', $res1['results'][0]['status']);
        $invoiceId = $res1['results'][0]['invoice_id'];

        $invoiceInitial = SalesInvoice::findOrFail($invoiceId);
        $this->assertNull($invoiceInitial->fiscal_signature);
        $this->assertNull($invoiceInitial->sdc_invoice_number);

        // Step 2: Later sync delivers statutory fiscal enrichment (fiscal signature & SDC invoice number)
        $payload2 = [
            'batch_id' => (string) Str::uuid(),
            'terminal_id' => $this->terminal->id,
            'sales' => [
                [
                    'client_txn_id' => $clientTxnId,
                    'device_time' => Carbon::now()->toIso8601String(),
                    'pricing_basis' => 'tax_inclusive',
                    'payment_status' => 'paid',
                    'fiscal_signature' => 'SIG-NBR-SDC-9876543210',
                    'sdc_invoice_number' => 'SDC-INV-2026-00042',
                    'items' => [
                        [
                            'variant_id' => $this->variant->id,
                            'quantity' => '1.0000',
                            'unit_price' => '230.0000',
                            'tax_code_id' => $this->taxCodeFifteen->id,
                            'flat_tax_rate' => '15.0000',
                            'discount_amount' => '0.0000',
                        ],
                    ],
                ],
            ],
        ];

        $res2 = $this->syncService->syncBatch($payload2, '1.2.0');
        $this->assertSame('already_synced', $res2['results'][0]['status']);

        // Check that fiscal columns were enriched on the issued invoice via Trigger 4 whitelist
        $invoiceEnriched = SalesInvoice::findOrFail($invoiceId);
        $this->assertSame('SIG-NBR-SDC-9876543210', $invoiceEnriched->fiscal_signature);
        $this->assertSame('SDC-INV-2026-00042', $invoiceEnriched->sdc_invoice_number);

        // Step 3: Trigger 4 still rejects modifying non-whitelisted columns on the issued invoice
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Immutability Violation|cannot be modified/i');

        DB::table('sales_invoices')
            ->where('id', $invoiceId)
            ->update(['total_payable' => '999999.0000']);
    }
}
