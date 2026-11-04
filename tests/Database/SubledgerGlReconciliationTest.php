<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Core\ValueObjects\Money;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\SalesInvoice;
use App\Models\SubledgerOpenItem;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Accounting\Contracts\AccountingPostingServiceContract;
use App\Modules\Accounting\Contracts\NumberSeriesContract;
use App\Modules\Accounting\DTOs\JournalEntryDTO;
use App\Modules\Accounting\DTOs\JournalLineDTO;
use App\Modules\Accounting\Subledger\Contracts\PaymentServiceContract;
use App\Modules\Accounting\Subledger\Contracts\SubledgerReconciliationContract;
use App\Modules\Accounting\Subledger\Contracts\SupplierPaymentVoucherContract;
use App\Modules\Accounting\Subledger\DTOs\AllocationItemDTO;
use App\Modules\Accounting\Subledger\DTOs\CreateSupplierVoucherDTO;
use App\Modules\Accounting\Subledger\DTOs\RecordPaymentDTO;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubledgerGlReconciliationTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected User $creatorUser;

    protected User $approverUser;

    protected FiscalYear $fiscalYear;

    protected AccountingPeriod $period;

    protected PaymentServiceContract $paymentService;

    protected SupplierPaymentVoucherContract $voucherService;

    protected AccountingPostingServiceContract $postingService;

    protected NumberSeriesContract $numberSeries;

    protected SubledgerReconciliationContract $reconService;

    /** @var array<int, Customer> */
    protected array $customers = [];

    /** @var array<int, Supplier> */
    protected array $suppliers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Reconciliation Tenant',
            'subdomain' => 'recon-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Reconciliation MegaMart Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Main Hub',
            'code' => 'HUB-01',
        ]);

        $this->creatorUser = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ops Clerk',
            'email' => 'ops-'.Str::random(6).'@example.com',
            'password' => 'password',
        ]);

        $this->approverUser = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Finance Director',
            'email' => 'fd-'.Str::random(6).'@example.com',
            'password' => 'password',
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
            'period_number' => Carbon::now()->month,
            'period_name' => Carbon::now()->format('F Y'),
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->endOfMonth()->toDateString(),
            'status' => 'open',
        ]);

        // COA Setup
        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '1010',
            'account_name' => 'Cash in Hand',
            'account_type' => 'asset',
        ]);

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '1020',
            'account_name' => 'Bank Current Account',
            'account_type' => 'asset',
        ]);

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '1030',
            'account_name' => 'Accounts Receivable Control',
            'account_type' => 'asset',
        ]);

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '2010',
            'account_name' => 'Accounts Payable Control',
            'account_type' => 'liability',
        ]);

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '4010',
            'account_name' => 'Sales Revenue',
            'account_type' => 'revenue',
        ]);

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '4020',
            'account_name' => 'Sales Returns & Allowances',
            'account_type' => 'revenue',
        ]);

        ChartOfAccount::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'account_code' => '5010',
            'account_name' => 'Purchase Expense',
            'account_type' => 'expense',
        ]);

        // Seed 10 Customers and 10 Suppliers
        for ($i = 1; $i <= 10; $i++) {
            $this->customers[] = Customer::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'name' => "Customer {$i}",
                'phone' => '+88017'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'credit_limit' => '10000000.0000',
                'is_active' => true,
            ]);

            $this->suppliers[] = Supplier::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'name' => "Supplier {$i}",
                'code' => 'SUP-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'credit_period_days' => 30,
                'created_by' => $this->creatorUser->id,
                'is_active' => true,
            ]);
        }

        $this->paymentService = app(PaymentServiceContract::class);
        $this->voucherService = app(SupplierPaymentVoucherContract::class);
        $this->postingService = app(AccountingPostingServiceContract::class);
        $this->numberSeries = app(NumberSeriesContract::class);
        $this->reconService = app(SubledgerReconciliationContract::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_subledger_open_items_strictly_equal_gl_control_accounts_after_500_random_transactions(): void
    {
        // Fixed seed for deterministic property-based testing
        mt_srand(424242);

        $arAccount = ChartOfAccount::where('company_id', $this->company->id)->where('account_code', '1030')->firstOrFail();
        $apAccount = ChartOfAccount::where('company_id', $this->company->id)->where('account_code', '2010')->firstOrFail();
        $revenueAccount = ChartOfAccount::where('company_id', $this->company->id)->where('account_code', '4010')->firstOrFail();
        $expenseAccount = ChartOfAccount::where('company_id', $this->company->id)->where('account_code', '5010')->firstOrFail();
        $today = Carbon::today()->toDateString();

        $operationCounts = [
            'ar_invoices' => 0,
            'ar_payments' => 0,
            'ar_credit_notes' => 0,
            'ap_bills' => 0,
            'ap_payments' => 0,
        ];

        for ($step = 1; $step <= 500; $step++) {
            $action = mt_rand(1, 5);

            switch ($action) {
                case 1:
                    // 1. Customer Sales Invoice (AR Debit 1030 / Revenue Credit 4010)
                    DB::transaction(function () use ($step, $arAccount, $revenueAccount, $today, &$operationCounts) {
                        $customer = $this->customers[mt_rand(0, 9)];
                        $amountFloat = round(mt_rand(10000, 500000) / 100, 2);
                        $amountStr = number_format($amountFloat, 4, '.', '');
                        $amountMoney = Money::of($amountStr);

                        $invId = (string) Str::uuid();
                        $invNumber = "INV-SIM-{$step}";

                        // Create Sales Invoice record
                        SalesInvoice::create([
                            'id' => $invId,
                            'tenant_id' => $this->tenant->id,
                            'company_id' => $this->company->id,
                            'branch_id' => $this->branch->id,
                            'customer_id' => $customer->id,
                            'invoice_number' => $invNumber,
                            'invoice_date' => $today,
                            'business_date' => $today,
                            'subtotal' => $amountStr,
                            'discount_amount' => '0.0000',
                            'supplementary_duty_amount' => '0.0000',
                            'tax_amount' => '0.0000',
                            'total_payable' => $amountStr,
                            'payment_status' => 'unpaid',
                            'status' => 'issued',
                        ]);

                        // Create open item
                        SubledgerOpenItem::create([
                            'id' => $invId,
                            'tenant_id' => $this->tenant->id,
                            'company_id' => $this->company->id,
                            'party_type' => 'customer',
                            'party_id' => $customer->id,
                            'source_type' => 'sales_invoice',
                            'source_id' => $invId,
                            'document_number' => $invNumber,
                            'document_date' => $today,
                            'due_date' => $today,
                            'direction' => 'debit',
                            'gl_control_account_code' => '1030',
                            'original_amount' => $amountStr,
                            'allocated_amount' => '0.0000',
                            'remaining_amount' => $amountStr,
                            'status' => 'open',
                        ]);

                        // Post balanced GL entry
                        $this->postingService->post(new JournalEntryDTO(
                            companyId: $this->company->id,
                            periodId: $this->period->id,
                            documentType: 'INV',
                            postingDate: $today,
                            businessDate: $today,
                            referenceType: 'sales_invoice',
                            referenceId: $invId,
                            postedBy: $this->creatorUser->id,
                            branchId: $this->branch->id,
                            lines: [
                                new JournalLineDTO(
                                    accountId: $arAccount->id,
                                    debitAmount: $amountMoney,
                                    creditAmount: Money::zero('BDT'),
                                    branchId: $this->branch->id,
                                ),
                                new JournalLineDTO(
                                    accountId: $revenueAccount->id,
                                    debitAmount: Money::zero('BDT'),
                                    creditAmount: $amountMoney,
                                    branchId: $this->branch->id,
                                ),
                            ]
                        ));

                        $operationCounts['ar_invoices']++;
                    });
                    break;

                case 2:
                    // 2. Customer Payment & Multi-Invoice Allocation (Bank/Cash Debit 1020/1010 / AR Credit 1030)
                    $customer = $this->customers[mt_rand(0, 9)];
                    $openItems = SubledgerOpenItem::where('tenant_id', $this->tenant->id)
                        ->where('party_type', 'customer')
                        ->where('party_id', $customer->id)
                        ->whereIn('status', ['open', 'partially_allocated'])
                        ->take(3)
                        ->get();

                    if ($openItems->isNotEmpty()) {
                        $allocations = [];
                        $totalPayment = Money::zero('BDT');

                        foreach ($openItems as $item) {
                            $remFloat = (float) $item->remaining_amount;
                            if ($remFloat <= 0) {
                                continue;
                            }
                            // Pay 30% to 100% of remaining
                            $payPortion = round($remFloat * (mt_rand(30, 100) / 100), 2);
                            if ($payPortion <= 0) {
                                $payPortion = $remFloat;
                            }
                            $allocAmount = Money::of(number_format($payPortion, 4, '.', ''));
                            $allocations[] = new AllocationItemDTO(openItemId: $item->id, amount: (string) $allocAmount->getBigDecimal());
                            $totalPayment = $totalPayment->add($allocAmount);
                        }

                        if ($totalPayment->getBigDecimal()->isPositive() && count($allocations) > 0) {
                            $methods = ['cash', 'bank', 'bkash', 'nagad'];
                            $method = $methods[mt_rand(0, 3)];

                            $dto = new RecordPaymentDTO(
                                companyId: $this->company->id,
                                branchId: $this->branch->id,
                                paymentType: 'inbound',
                                paymentMethod: $method,
                                partyType: 'customer',
                                partyId: $customer->id,
                                amount: (string) $totalPayment->getBigDecimal(),
                                paymentDate: $today,
                                reference: "SIM-PAY-{$step}",
                                postedBy: $this->creatorUser->id,
                                allocations: $allocations
                            );

                            $this->paymentService->recordPayment($dto);
                            $operationCounts['ar_payments']++;
                        }
                    }
                    break;

                case 3:
                    // 3. Supplier Bill (Purchase Expense Debit 5010 / AP Credit 2010)
                    DB::transaction(function () use ($step, $expenseAccount, $apAccount, $today, &$operationCounts) {
                        $supplier = $this->suppliers[mt_rand(0, 9)];
                        $amountFloat = round(mt_rand(15000, 600000) / 100, 2);
                        $amountStr = number_format($amountFloat, 4, '.', '');
                        $amountMoney = Money::of($amountStr);

                        $billId = (string) Str::uuid();
                        $billNumber = "BILL-SIM-{$step}";

                        $bill = SupplierBill::create([
                            'id' => $billId,
                            'tenant_id' => $this->tenant->id,
                            'company_id' => $this->company->id,
                            'branch_id' => $this->branch->id,
                            'supplier_id' => $supplier->id,
                            'bill_number' => $billNumber,
                            'bill_date' => $today,
                            'due_date' => $today,
                            'subtotal' => $amountStr,
                            'tax_amount' => '0.0000',
                            'total_amount' => $amountStr,
                            'status' => 'approved',
                            'created_by' => $this->creatorUser->id,
                        ]);

                        SubledgerOpenItem::create([
                            'id' => (string) Str::uuid(),
                            'tenant_id' => $this->tenant->id,
                            'company_id' => $this->company->id,
                            'party_type' => 'supplier',
                            'party_id' => $supplier->id,
                            'source_type' => 'supplier_bill',
                            'source_id' => $bill->id,
                            'document_number' => $billNumber,
                            'document_date' => $today,
                            'due_date' => $today,
                            'direction' => 'credit',
                            'gl_control_account_code' => '2010',
                            'original_amount' => $amountStr,
                            'allocated_amount' => '0.0000',
                            'remaining_amount' => $amountStr,
                            'status' => 'open',
                        ]);

                        $this->postingService->post(new JournalEntryDTO(
                            companyId: $this->company->id,
                            periodId: $this->period->id,
                            documentType: 'BILL',
                            postingDate: $today,
                            businessDate: $today,
                            referenceType: 'supplier_bill',
                            referenceId: $billId,
                            postedBy: $this->creatorUser->id,
                            branchId: $this->branch->id,
                            lines: [
                                new JournalLineDTO(
                                    accountId: $expenseAccount->id,
                                    debitAmount: $amountMoney,
                                    creditAmount: Money::zero('BDT'),
                                    branchId: $this->branch->id,
                                ),
                                new JournalLineDTO(
                                    accountId: $apAccount->id,
                                    debitAmount: Money::zero('BDT'),
                                    creditAmount: $amountMoney,
                                    branchId: $this->branch->id,
                                ),
                            ]
                        ));

                        $operationCounts['ap_bills']++;
                    });
                    break;

                case 4:
                    // 4. Supplier Payment Voucher with SoD (AP Debit 2010 / Bank Credit 1020)
                    $supplier = $this->suppliers[mt_rand(0, 9)];
                    $openBill = SubledgerOpenItem::where('tenant_id', $this->tenant->id)
                        ->where('party_type', 'supplier')
                        ->where('party_id', $supplier->id)
                        ->whereIn('status', ['open', 'partially_allocated'])
                        ->first();

                    if ($openBill) {
                        $remFloat = (float) $openBill->remaining_amount;
                        if ($remFloat > 0) {
                            $payPortion = round($remFloat * (mt_rand(50, 100) / 100), 2);
                            if ($payPortion <= 0) {
                                $payPortion = $remFloat;
                            }
                            $allocAmount = Money::of(number_format($payPortion, 4, '.', ''));

                            $vDto = new CreateSupplierVoucherDTO(
                                companyId: $this->company->id,
                                branchId: $this->branch->id,
                                supplierId: $supplier->id,
                                paymentMethod: 'bank',
                                amount: (string) $allocAmount->getBigDecimal(),
                                paymentDate: $today,
                                reference: "VOUCHER-SIM-{$step}",
                                createdBy: $this->creatorUser->id,
                                allocations: [
                                    new AllocationItemDTO(openItemId: $openBill->id, amount: (string) $allocAmount->getBigDecimal()),
                                ]
                            );

                            $voucher = $this->voucherService->createVoucher($vDto);
                            // Approved by independent approver (satisfies SoD)
                            $this->voucherService->approveVoucher($voucher->id, $this->approverUser->id);

                            $operationCounts['ap_payments']++;
                        }
                    }
                    break;

                case 5:
                    // 5. Mushak 6.7 Credit Note / Sales Allowance (Sales Return Debit 4020 / AR Credit 1030)
                    DB::transaction(function () use ($step, $arAccount, $today, &$operationCounts) {
                        $customer = $this->customers[mt_rand(0, 9)];
                        $openItem = SubledgerOpenItem::where('tenant_id', $this->tenant->id)
                            ->where('party_type', 'customer')
                            ->where('party_id', $customer->id)
                            ->whereIn('status', ['open', 'partially_allocated'])
                            ->first();

                        if ($openItem) {
                            $remFloat = (float) $openItem->remaining_amount;
                            if ($remFloat > 10.00) {
                                $cnAmountFloat = round(mt_rand(500, min(5000, (int) ($remFloat * 50))) / 100, 2);
                                if ($cnAmountFloat > 0 && $cnAmountFloat <= $remFloat) {
                                    $cnAmountMoney = Money::of(number_format($cnAmountFloat, 4, '.', ''));
                                    $cnId = (string) Str::uuid();

                                    // Record Credit Note table entry
                                    DB::table('credit_notes')->insert([
                                        'id' => $cnId,
                                        'tenant_id' => $this->tenant->id,
                                        'company_id' => $this->company->id,
                                        'branch_id' => $this->branch->id,
                                        'sales_invoice_id' => $openItem->source_id,
                                        'customer_id' => $customer->id,
                                        'credit_note_number' => "CN-SIM-{$step}",
                                        'credit_note_date' => $today,
                                        'reason' => 'Damaged goods returned',
                                        'subtotal' => (string) $cnAmountMoney->getBigDecimal(),
                                        'tax_amount' => '0.0000',
                                        'total_amount' => (string) $cnAmountMoney->getBigDecimal(),
                                        'status' => 'issued',
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ]);

                                    // Allocate credit note to open item (trigger updates remaining balance)
                                    DB::table('payment_allocations')->insert([
                                        'id' => (string) Str::uuid(),
                                        'tenant_id' => $this->tenant->id,
                                        'company_id' => $this->company->id,
                                        'payment_id' => null,
                                        'credit_note_id' => $cnId,
                                        'open_item_id' => $openItem->id,
                                        'allocated_amount' => (string) $cnAmountMoney->getBigDecimal(),
                                        'allocation_date' => $today,
                                        'notes' => 'Credit Note Return',
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ]);

                                    // Post GL entry: Debit Sales Return 4020, Credit AR 1030
                                    $returnAccount = ChartOfAccount::where('company_id', $this->company->id)->where('account_code', '4020')->firstOrFail();
                                    $this->postingService->post(new JournalEntryDTO(
                                        companyId: $this->company->id,
                                        periodId: $this->period->id,
                                        documentType: 'CN',
                                        postingDate: $today,
                                        businessDate: $today,
                                        referenceType: 'credit_note',
                                        referenceId: $cnId,
                                        postedBy: $this->creatorUser->id,
                                        branchId: $this->branch->id,
                                        lines: [
                                            new JournalLineDTO(
                                                accountId: $returnAccount->id,
                                                debitAmount: $cnAmountMoney,
                                                creditAmount: Money::zero('BDT'),
                                                branchId: $this->branch->id,
                                            ),
                                            new JournalLineDTO(
                                                accountId: $arAccount->id,
                                                debitAmount: Money::zero('BDT'),
                                                creditAmount: $cnAmountMoney,
                                                branchId: $this->branch->id,
                                            ),
                                        ]
                                    ));

                                    $operationCounts['ar_credit_notes']++;
                                }
                            }
                        }
                    });
                    break;
            }
        }

        // Verify that operations took place across all 5 transaction types
        $this->assertGreaterThan(50, $operationCounts['ar_invoices']);
        $this->assertGreaterThan(30, $operationCounts['ar_payments']);
        $this->assertGreaterThan(10, $operationCounts['ar_credit_notes']);
        $this->assertGreaterThan(50, $operationCounts['ap_bills']);
        $this->assertGreaterThan(30, $operationCounts['ap_payments']);

        // 1. RECONCILE RECEIVABLES SUBLEDGER TO GL ACCOUNT 1030
        $arRecon = $this->reconService->reconcileReceivables($this->company->id);
        $this->assertTrue($arRecon->isReconciled, "AR Subledger does not reconcile with GL Account 1030! Subledger: {$arRecon->subledgerTotal->getFormattedAmount()}, GL: {$arRecon->glControlTotal->getFormattedAmount()}, Variance: {$arRecon->variance->getFormattedAmount()}");
        $this->assertTrue($arRecon->variance->isZero());
        $this->assertEquals((string) $arRecon->subledgerTotal->getBigDecimal(), (string) $arRecon->glControlTotal->getBigDecimal());

        // 2. RECONCILE PAYABLES SUBLEDGER TO GL ACCOUNT 2010
        $apRecon = $this->reconService->reconcilePayables($this->company->id);
        $this->assertTrue($apRecon->isReconciled, "AP Subledger does not reconcile with GL Account 2010! Subledger: {$apRecon->subledgerTotal->getFormattedAmount()}, GL: {$apRecon->glControlTotal->getFormattedAmount()}, Variance: {$apRecon->variance->getFormattedAmount()}");
        $this->assertTrue($apRecon->variance->isZero());
        $this->assertEquals((string) $apRecon->subledgerTotal->getBigDecimal(), (string) $apRecon->glControlTotal->getBigDecimal());

        // 3. Ensure every open item has mathematically consistent status
        $inconsistentItems = SubledgerOpenItem::where('tenant_id', $this->tenant->id)
            ->whereRaw("(remaining_amount = 0 AND status <> 'fully_allocated') OR (remaining_amount > 0 AND allocated_amount > 0 AND status <> 'partially_allocated') OR (allocated_amount = 0 AND status <> 'open')")
            ->count();
        $this->assertSame(0, $inconsistentItems, 'Found open items with inconsistent allocation status!');
    }
}
