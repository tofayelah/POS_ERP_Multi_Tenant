<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\TaxCode;
use App\Models\TaxCodeRate;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosBatchSyncTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected Warehouse $warehouse;

    protected PosTerminal $terminal;

    protected User $cashier;

    protected FiscalYear $fiscalYear;

    protected AccountingPeriod $period;

    protected TaxCode $taxCodeZero;

    protected TaxCode $taxCodeFifteen;

    protected ProductVariant $variantA;

    protected ProductVariant $variantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Batch Sync Tenant',
            'subdomain' => 'sync-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'Tenant Admin',
            'guard_name' => 'web',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Batch Sync Mart Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Gulshan Outlet',
            'code' => 'GUL-01',
        ]);

        $this->warehouse = Warehouse::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'WH-GUL-01',
            'name' => 'Gulshan Outlet Stockroom',
            'is_active' => true,
        ]);

        $this->terminal = PosTerminal::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'terminal_code' => 'T-GUL-01',
            'name' => 'Register 1',
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'POS Cashier 1',
            'email' => 'cashier-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);
        $this->cashier->assignRole('Tenant Admin');

        \App\Models\TenantMembership::create([
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

        // General Ledger Accounts (Asset, Revenue, Liability, Expense)
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

        // Tax Codes
        $this->taxCodeZero = TaxCode::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'VAT-0',
            'name_en' => 'Zero Rated VAT',
            'tax_type' => 'standard_vat',
            'is_active' => true,
        ]);
        TaxCodeRate::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'tax_code_id' => $this->taxCodeZero->id,
            'rate_percent' => '0.00',
            'supplementary_duty_percent' => '0.00',
            'effective_from' => '2020-01-01',
            'effective_to' => null,
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
            'is_active' => true,
        ]);

        // Product Catalog
        $productA = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'PROD-A',
            'name_en' => 'Miniket Rice 5kg',
            'base_uom' => 'Bag',
            'is_active' => true,
        ]);
        $this->variantA = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $productA->id,
            'tax_code_id' => $this->taxCodeZero->id,
            'sku' => 'SKU-RICE-5KG',
            'name_en' => 'Miniket Rice 5kg',
            'base_uom' => 'Bag',
            'mrp' => '450.0000',
            'cost_price' => '400.0000',
            'is_active' => true,
        ]);

        $productB = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'PROD-B',
            'name_en' => 'Arogya Neem Soap 125g',
            'base_uom' => 'Pcs',
            'is_active' => true,
        ]);
        $this->variantB = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $productB->id,
            'tax_code_id' => $this->taxCodeFifteen->id,
            'sku' => 'SKU-SOAP-125G',
            'name_en' => 'Arogya Neem Soap 125g',
            'base_uom' => 'Pcs',
            'mrp' => '65.0000',
            'cost_price' => '50.0000',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_same_batch_sent_twice_records_each_sale_once_idempotency(): void
    {
        $batchId = (string) Str::uuid();
        $shiftId = (string) Str::uuid();

        $payload = [
            'batch_id' => $batchId,
            'terminal_id' => $this->terminal->id,
            'shift_id' => $shiftId,
            'sales' => [
                [
                    'client_txn_id' => 'TXN-IDEMP-001',
                    'device_time' => Carbon::now()->subMinutes(5)->toIso8601String(),
                    'payment_method' => 'cash',
                    'items' => [
                        [
                            'variant_id' => $this->variantA->id,
                            'quantity' => '2.0000',
                            'unit_price' => '450.0000',
                            'tax_code_id' => $this->taxCodeZero->id,
                        ],
                    ],
                ],
                [
                    'client_txn_id' => 'TXN-IDEMP-002',
                    'device_time' => Carbon::now()->subMinutes(4)->toIso8601String(),
                    'payment_method' => 'cash',
                    'items' => [
                        [
                            'variant_id' => $this->variantB->id,
                            'quantity' => '1.0000',
                            'unit_price' => '65.0000',
                            'tax_code_id' => $this->taxCodeFifteen->id,
                        ],
                    ],
                ],
            ],
        ];

        // First submission: should commit both sales
        $response1 = $this->actingAs($this->cashier)
            ->withHeader('X-POS-Client-Version', '1.2.0')
            ->postJson('/api/v1/pos/sync-batch', $payload);

        $response1->assertOk();
        $data1 = $response1->json();
        $this->assertSame('success', $data1['status']);
        $this->assertCount(2, $data1['results']);
        $this->assertSame('committed', $data1['results'][0]['status'], $data1['results'][0]['reason'] ?? 'no reason');
        $this->assertSame('committed', $data1['results'][1]['status']);
        $invNum1 = $data1['results'][0]['invoice_number'];
        $invNum2 = $data1['results'][1]['invoice_number'];

        // Second submission of the exact same payload: should return already_synced for both
        $response2 = $this->actingAs($this->cashier)
            ->withHeader('X-POS-Client-Version', '1.2.0')
            ->postJson('/api/v1/pos/sync-batch', $payload);

        $response2->assertOk();
        $data2 = $response2->json();
        $this->assertSame('success', $data2['status']);
        $this->assertCount(2, $data2['results']);
        $this->assertSame('already_synced', $data2['results'][0]['status']);
        $this->assertSame('already_synced', $data2['results'][1]['status']);
        $this->assertSame($invNum1, $data2['results'][0]['invoice_number']);
        $this->assertSame($invNum2, $data2['results'][1]['invoice_number']);

        // Assert strictly 2 invoices exist in database
        $this->assertSame(2, SalesInvoice::where('tenant_id', $this->tenant->id)->count());

        // Assert strictly 2 GL journal entries exist
        $this->assertSame(2, JournalEntry::where('tenant_id', $this->tenant->id)->where('reference_type', 'sales_invoice')->count());
    }

    public function test_batch_where_one_sale_fails_still_commits_the_others_isolated_savepoints(): void
    {
        $batchId = (string) Str::uuid();
        $shiftId = (string) Str::uuid();
        $nonExistentVariantId = (string) Str::uuid();

        $payload = [
            'batch_id' => $batchId,
            'terminal_id' => $this->terminal->id,
            'shift_id' => $shiftId,
            'sales' => [
                [
                    'client_txn_id' => 'TXN-PARTIAL-001',
                    'device_time' => Carbon::now()->subMinutes(5)->toIso8601String(),
                    'payment_method' => 'cash',
                    'items' => [
                        [
                            'variant_id' => $this->variantA->id,
                            'quantity' => '1.0000',
                            'unit_price' => '450.0000',
                            'tax_code_id' => $this->taxCodeZero->id,
                        ],
                    ],
                ],
                [
                    // This sale will fail because variant_id does not exist
                    'client_txn_id' => 'TXN-PARTIAL-002',
                    'device_time' => Carbon::now()->subMinutes(4)->toIso8601String(),
                    'payment_method' => 'cash',
                    'items' => [
                        [
                            'variant_id' => $nonExistentVariantId,
                            'quantity' => '1.0000',
                            'unit_price' => '500.0000',
                        ],
                    ],
                ],
                [
                    'client_txn_id' => 'TXN-PARTIAL-003',
                    'device_time' => Carbon::now()->subMinutes(3)->toIso8601String(),
                    'payment_method' => 'cash',
                    'items' => [
                        [
                            'variant_id' => $this->variantB->id,
                            'quantity' => '2.0000',
                            'unit_price' => '65.0000',
                            'tax_code_id' => $this->taxCodeFifteen->id,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)
            ->withHeader('X-POS-Client-Version', '1.2.0')
            ->postJson('/api/v1/pos/sync-batch', $payload);

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(3, $data['results']);
        $this->assertSame('committed', $data['results'][0]['status']);
        $this->assertSame('failed', $data['results'][1]['status']);
        $this->assertNotEmpty($data['results'][1]['reason']);
        $this->assertSame('committed', $data['results'][2]['status']);

        // Assert that exactly 2 invoices are saved in the database
        $this->assertSame(2, SalesInvoice::where('tenant_id', $this->tenant->id)->count());
        $this->assertNotNull(SalesInvoice::where('client_txn_id', 'TXN-PARTIAL-001')->first());
        $this->assertNull(SalesInvoice::where('client_txn_id', 'TXN-PARTIAL-002')->first());
        $this->assertNotNull(SalesInvoice::where('client_txn_id', 'TXN-PARTIAL-003')->first());
    }

    public function test_new27_invariant_draft_then_issued_via_invoice_issuer(): void
    {
        $batchId = (string) Str::uuid();

        $payload = [
            'batch_id' => $batchId,
            'terminal_id' => $this->terminal->id,
            'shift_id' => (string) Str::uuid(),
            'sales' => [
                [
                    'client_txn_id' => 'TXN-NEW27-001',
                    'device_time' => Carbon::now()->subMinutes(2)->toIso8601String(),
                    'payment_method' => 'cash',
                    'items' => [
                        [
                            'variant_id' => $this->variantA->id,
                            'quantity' => '1.0000',
                            'unit_price' => '450.0000',
                            'tax_code_id' => $this->taxCodeZero->id,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier)
            ->withHeader('X-POS-Client-Version', '1.2.0')
            ->postJson('/api/v1/pos/sync-batch', $payload);

        $response->assertOk();

        $invoice = SalesInvoice::where('client_txn_id', 'TXN-NEW27-001')->firstOrFail();
        $this->assertSame('issued', $invoice->status);
        $this->assertTrue($invoice->is_synced_from_offline);
        $this->assertStringStartsWith('INV-', $invoice->invoice_number);

        // Verify balanced double-entry journal was posted
        $journal = JournalEntry::where('reference_type', 'sales_invoice')
            ->where('reference_id', $invoice->id)
            ->firstOrFail();
        $this->assertTrue($journal->is_posted);
        $this->assertEquals((string) $journal->lines->sum('debit_amount'), (string) $journal->lines->sum('credit_amount'));
    }
}
