<?php

namespace App\Modules\Tax\Services;

use App\Core\Tenancy\TenantContext;
use App\Core\ValueObjects\Money;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\PosTerminal;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\TaxCode;
use App\Modules\Accounting\Contracts\AccountingPostingServiceContract;
use App\Modules\Accounting\Contracts\NumberSeriesContract;
use App\Modules\Accounting\DTOs\JournalEntryDTO;
use App\Modules\Accounting\DTOs\JournalLineDTO;
use App\Modules\Accounting\Subledger\Contracts\CustomerCreditCheckerContract;
use App\Modules\Tax\DTOs\IssueInvoiceRequest;
use App\Modules\Tax\DTOs\ReturnInvoiceRequest;
use App\Modules\Tax\TaxCalculator;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InvoiceIssuer
{
    protected CustomerCreditCheckerContract $creditChecker;

    public function __construct(
        protected TaxCalculator $taxCalculator,
        protected NumberSeriesContract $numberSeries,
        protected AccountingPostingServiceContract $postingService,
        ?CustomerCreditCheckerContract $creditChecker = null,
    ) {
        $this->creditChecker = $creditChecker ?? app(CustomerCreditCheckerContract::class);
    }

    /**
     * Issue a Sales Invoice: calculate tax, draw gapless number series, insert draft, issue atomically,
     * and post balanced double-entry GL journal in the same transaction (Design §5.4, §8.2(8), §9.1, NEW-24, NEW-27).
     */
    public function issue(IssueInvoiceRequest $request): SalesInvoice
    {
        return DB::transaction(function () use ($request) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new InvalidArgumentException('Cannot issue invoice without active TenantContext.');
            }

            $companyId = $request->company instanceof Company ? $request->company->id : $request->company;
            $branchId = $request->branch instanceof Branch ? $request->branch->id : $request->branch;
            $customerId = $request->customer instanceof Customer ? $request->customer->id : $request->customer;
            $terminalId = $request->terminal instanceof PosTerminal ? $request->terminal->id : $request->terminal;

            $invoiceDate = $request->invoiceDate
                ? ($request->invoiceDate instanceof CarbonInterface ? $request->invoiceDate->toDateString() : Carbon::parse($request->invoiceDate)->toDateString())
                : Carbon::today()->toDateString();

            $businessDate = $request->businessDate
                ? ($request->businessDate instanceof CarbonInterface ? $request->businessDate->toDateString() : Carbon::parse($request->businessDate)->toDateString())
                : $invoiceDate;

            if (empty($request->lines)) {
                throw new InvalidArgumentException('Cannot issue sales invoice with 0 line items (NEW-27).');
            }

            // 1. Resolve open accounting period
            /** @var AccountingPeriod|null $period */
            $period = AccountingPeriod::query()
                ->where('tenant_id', $tenantId)
                ->where('company_id', $companyId)
                ->where('status', 'open')
                ->where('start_date', '<=', $invoiceDate)
                ->where('end_date', '>=', $invoiceDate)
                ->with('fiscalYear')
                ->first();

            if (! $period) {
                throw new InvalidArgumentException("No open accounting period found for company [{$companyId}] on date [{$invoiceDate}].");
            }

            $fiscalYearTitle = $period->fiscalYear->title ?? Carbon::parse($invoiceDate)->format('Y');

            // 2. Draw next gapless sequence invoice number
            $invoiceNumber = $this->numberSeries->getNextNumber(
                companyId: $companyId,
                branchId: $branchId,
                documentType: 'sales_invoice',
                fiscalYear: $fiscalYearTitle,
            );

            // 3. Compute Line Taxes & Totals
            $lineCalculations = [];
            $totalSubtotal = Money::zero('BDT');
            $totalDiscount = Money::of($request->invoiceDiscount);
            $totalTax = Money::zero('BDT');
            $totalSd = Money::zero('BDT');
            $totalCogs = Money::zero('BDT');

            foreach ($request->lines as $lineDto) {
                $variantId = $lineDto->variant instanceof ProductVariant ? $lineDto->variant->id : $lineDto->variant;
                $taxCodeId = $lineDto->taxCode instanceof TaxCode ? $lineDto->taxCode->id : $lineDto->taxCode;

                $taxRes = $this->taxCalculator->calculateLine(
                    price: $lineDto->unitPrice,
                    quantity: $lineDto->quantity,
                    discount: $lineDto->discount,
                    pricingBasis: $request->pricingBasis,
                    taxCode: $taxCodeId,
                    date: $invoiceDate,
                    vatRatePercent: $lineDto->vatRatePercent,
                    supplementaryDutyPercent: $lineDto->supplementaryDutyPercent,
                );

                $lineSubtotal = $taxRes->net;
                $lineTax = $taxRes->vat;
                $lineSd = $taxRes->supplementaryDuty;
                $lineDiscount = Money::of($lineDto->discount);

                $totalSubtotal = $totalSubtotal->add($lineSubtotal);
                $totalTax = $totalTax->add($lineTax);
                $totalSd = $totalSd->add($lineSd);
                $totalDiscount = $totalDiscount->add($lineDiscount);

                if ($lineDto->cogsAmount !== null) {
                    $totalCogs = $totalCogs->add(Money::of($lineDto->cogsAmount));
                }

                $lineCalculations[] = [
                    'id' => (string) Str::uuid(),
                    'variant_id' => $variantId,
                    'item_description' => $lineDto->itemDescription,
                    'uom' => $lineDto->uom ?? 'Pcs',
                    'batch_number' => $lineDto->batchNumber,
                    'quantity' => $lineDto->quantity,
                    'unit_price' => (string) Money::of($lineDto->unitPrice)->getBigDecimal(),
                    'pricing_basis' => $request->pricingBasis,
                    'line_discount_amount' => (string) $lineDiscount->getBigDecimal(),
                    'tax_code_id' => $taxCodeId ?? $this->resolveFallbackTaxCodeId($companyId),
                    'vat_rate' => $taxRes->vatRatePercent,
                    'supplementary_duty_rate' => $taxRes->supplementaryDutyPercent,
                    'supplementary_duty_amount' => (string) $lineSd->getBigDecimal(),
                    'tax_amount' => (string) $lineTax->getBigDecimal(),
                    'line_total' => (string) $lineSubtotal->getBigDecimal(),
                ];
            }

            $totalPayable = $totalSubtotal->subtract(Money::of($request->invoiceDiscount))
                ->add($totalSd)
                ->add($totalTax);

            $paymentStatus = $request->paymentStatus ?? 'paid';

            // Credit Limit Check (ACC-06, NEW-16, Design §8.2 #4)
            if ($paymentStatus === 'credit') {
                if (! $customerId) {
                    throw new InvalidArgumentException('Cannot issue credit sale without a registered customer.');
                }
                $this->creditChecker->checkCreditLimit((string) $companyId, (string) $customerId, $totalPayable);
            }

            $invoiceId = (string) Str::uuid();

            // 4. Insert draft invoice
            DB::table('sales_invoices')->insert([
                'id' => $invoiceId,
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'terminal_id' => $terminalId,
                'client_txn_id' => $request->clientTxnId,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $invoiceDate,
                'business_date' => $businessDate,
                'customer_id' => $customerId,
                'status' => 'draft',
                'subtotal' => (string) $totalSubtotal->getBigDecimal(),
                'discount_amount' => (string) $totalDiscount->getBigDecimal(),
                'supplementary_duty_amount' => (string) $totalSd->getBigDecimal(),
                'tax_amount' => (string) $totalTax->getBigDecimal(),
                'total_payable' => (string) $totalPayable->getBigDecimal(),
                'payment_status' => $paymentStatus,
                'is_synced_from_offline' => $request->isSyncedFromOffline,
                'device_time' => $request->deviceTime,
                'server_received_time' => $request->serverReceivedTime,
                'clock_skew_seconds' => $request->clockSkewSeconds,
                'is_clock_skew_flagged' => $request->isClockSkewFlagged,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create subledger open item for credit sale (single source of truth for AR)
            if ($paymentStatus === 'credit') {
                $dueDate = $request->dueDate
                    ? ($request->dueDate instanceof CarbonInterface ? $request->dueDate->toDateString() : Carbon::parse($request->dueDate)->toDateString())
                    : Carbon::parse($invoiceDate)->addDays(30)->toDateString();

                DB::table('subledger_open_items')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'company_id' => $companyId,
                    'party_type' => 'customer',
                    'party_id' => $customerId,
                    'source_type' => 'sales_invoice',
                    'source_id' => $invoiceId,
                    'document_number' => $invoiceNumber,
                    'document_date' => $invoiceDate,
                    'due_date' => $dueDate,
                    'direction' => 'debit',
                    'gl_control_account_code' => '1030',
                    'original_amount' => (string) $totalPayable->getBigDecimal(),
                    'allocated_amount' => '0.0000',
                    'remaining_amount' => (string) $totalPayable->getBigDecimal(),
                    'status' => 'open',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 5. Insert invoice lines
            foreach ($lineCalculations as $line) {
                DB::table('sales_invoice_lines')->insert([
                    'id' => $line['id'],
                    'tenant_id' => $tenantId,
                    'company_id' => $companyId,
                    'sales_invoice_id' => $invoiceId,
                    'product_variant_id' => $line['variant_id'],
                    'item_description' => $line['item_description'],
                    'uom' => $line['uom'],
                    'batch_number' => $line['batch_number'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'pricing_basis' => $line['pricing_basis'],
                    'line_discount_amount' => $line['line_discount_amount'],
                    'tax_code_id' => $line['tax_code_id'],
                    'vat_rate' => $line['vat_rate'],
                    'supplementary_duty_rate' => $line['supplementary_duty_rate'],
                    'supplementary_duty_amount' => $line['supplementary_duty_amount'],
                    'tax_amount' => $line['tax_amount'],
                    'line_total' => $line['line_total'],
                    'created_at' => now(),
                ]);
            }

            // 6. Issue the invoice (PostgreSQL trigger verifies lines exist and header totals balance!)
            DB::table('sales_invoices')->where('id', $invoiceId)->update([
                'status' => 'issued',
                'updated_at' => now(),
            ]);

            // 7. Post synchronous double-entry GL journal via AccountingPostingService
            $accountMap = $request->accountMap;
            $cashOrArDefault = ($paymentStatus === 'credit') ? '1030' : '1010';
            $cashAccountId = $accountMap['cash_or_ar'] ?? $this->getRequiredAccountId($companyId, $cashOrArDefault);
            $salesAccountId = $accountMap['sales'] ?? $this->getRequiredAccountId($companyId, '4010');
            $vatPayableAccountId = $accountMap['vat_payable'] ?? $this->getRequiredAccountId($companyId, '2020');
            $sdPayableAccountId = $accountMap['sd_payable'] ?? $this->findOptionalAccountId($companyId, '2030');

            $cogsAccountId = $accountMap['cogs'] ?? $this->findOptionalAccountId($companyId, '5010');
            $inventoryAccountId = $accountMap['inventory'] ?? $this->findOptionalAccountId($companyId, '1040');

            $journalLines = [];

            // Debit: Cash / AR for Total Payable
            $journalLines[] = new JournalLineDTO(
                accountId: $cashAccountId,
                debitAmount: $totalPayable,
                creditAmount: Money::zero('BDT'),
                branchId: $branchId,
                lineDescription: "Sales Invoice {$invoiceNumber} - Receivable/Cash",
            );

            // Credit: Sales Revenue for Net Subtotal (net of invoice discount)
            $netRevenue = $totalSubtotal->subtract(Money::of($request->invoiceDiscount));
            $journalLines[] = new JournalLineDTO(
                accountId: $salesAccountId,
                debitAmount: Money::zero('BDT'),
                creditAmount: $netRevenue,
                branchId: $branchId,
                lineDescription: "Sales Invoice {$invoiceNumber} - Revenue",
            );

            // Credit: VAT Payable
            if (! $totalTax->getBigDecimal()->isZero()) {
                $journalLines[] = new JournalLineDTO(
                    accountId: $vatPayableAccountId,
                    debitAmount: Money::zero('BDT'),
                    creditAmount: $totalTax,
                    branchId: $branchId,
                    lineDescription: "Sales Invoice {$invoiceNumber} - VAT Payable",
                );
            }

            // Credit: Supplementary Duty Payable (if applicable)
            if (! $totalSd->getBigDecimal()->isZero() && $sdPayableAccountId) {
                $journalLines[] = new JournalLineDTO(
                    accountId: $sdPayableAccountId,
                    debitAmount: Money::zero('BDT'),
                    creditAmount: $totalSd,
                    branchId: $branchId,
                    lineDescription: "Sales Invoice {$invoiceNumber} - Supplementary Duty Payable",
                );
            }

            // Optional: Inventory and COGS posting if cost is tracked
            if (! $totalCogs->getBigDecimal()->isZero() && $cogsAccountId && $inventoryAccountId) {
                $journalLines[] = new JournalLineDTO(
                    accountId: $cogsAccountId,
                    debitAmount: $totalCogs,
                    creditAmount: Money::zero('BDT'),
                    branchId: $branchId,
                    lineDescription: "Sales Invoice {$invoiceNumber} - COGS",
                );

                $journalLines[] = new JournalLineDTO(
                    accountId: $inventoryAccountId,
                    debitAmount: Money::zero('BDT'),
                    creditAmount: $totalCogs,
                    branchId: $branchId,
                    lineDescription: "Sales Invoice {$invoiceNumber} - Inventory Asset",
                );
            }

            $journalEntryDTO = new JournalEntryDTO(
                companyId: $companyId,
                periodId: $period->id,
                documentType: 'sales_invoice',
                postingDate: $invoiceDate,
                businessDate: $businessDate,
                referenceType: 'sales_invoice',
                referenceId: $invoiceId,
                postedBy: $request->postedBy,
                branchId: $branchId,
                narration: $request->narration ?? "Statutory Mushak-6.3 Tax Invoice {$invoiceNumber}",
                lines: $journalLines,
            );

            $this->postingService->post($journalEntryDTO);

            return SalesInvoice::with('lines')->findOrFail($invoiceId);
        });
    }

    /**
     * Create a Mushak 6.7 Credit Note for returns, reversing the original tax and posting
     * a reversing journal entry without ever modifying the original issued sales invoice (NEW-27, TAX-03).
     */
    public function createReturn(ReturnInvoiceRequest $request): CreditNote
    {
        return DB::transaction(function () use ($request) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new InvalidArgumentException('Cannot process return without active TenantContext.');
            }

            /** @var SalesInvoice $invoice */
            $invoice = SalesInvoice::with('lines')->findOrFail($request->salesInvoiceId);

            if ($invoice->status !== 'issued') {
                throw new InvalidArgumentException("Cannot create credit note for invoice in [{$invoice->status}] status. Only issued invoices may be refunded.");
            }

            $returnDate = $request->returnDate
                ? ($request->returnDate instanceof CarbonInterface ? $request->returnDate->toDateString() : Carbon::parse($request->returnDate)->toDateString())
                : Carbon::today()->toDateString();

            /** @var AccountingPeriod|null $period */
            $period = AccountingPeriod::query()
                ->where('tenant_id', $tenantId)
                ->where('company_id', $invoice->company_id)
                ->where('status', 'open')
                ->where('start_date', '<=', $returnDate)
                ->where('end_date', '>=', $returnDate)
                ->with('fiscalYear')
                ->first();

            if (! $period) {
                throw new InvalidArgumentException("No open accounting period found for return on date [{$returnDate}].");
            }

            $fiscalYearTitle = $period->fiscalYear->title ?? Carbon::parse($returnDate)->format('Y');

            // Draw gapless Credit Note number
            $creditNoteNumber = $this->numberSeries->getNextNumber(
                companyId: $invoice->company_id,
                branchId: $invoice->branch_id,
                documentType: 'credit_note',
                fiscalYear: $fiscalYearTitle,
            );

            $returnedSubtotal = Money::zero('BDT');
            $returnedTax = Money::zero('BDT');
            $returnedSd = Money::zero('BDT');

            $invoiceLinesById = $invoice->lines->keyBy('id');
            $cnLinesData = [];

            foreach ($request->lines as $returnLine) {
                $origLine = $invoiceLinesById->get($returnLine->salesInvoiceLineId);
                if (! $origLine) {
                    throw new InvalidArgumentException("Original sales invoice line [{$returnLine->salesInvoiceLineId}] not found on invoice [{$invoice->id}].");
                }

                $retQtyBd = BigDecimal::of($returnLine->quantity);
                $origQtyBd = BigDecimal::of((string) $origLine->quantity);

                if ($retQtyBd->isGreaterThan($origQtyBd) || $retQtyBd->isZero()) {
                    throw new InvalidArgumentException("Return quantity [{$retQtyBd}] exceeds original line quantity [{$origQtyBd}].");
                }

                // Compute proportional return amounts
                $ratio = $retQtyBd->dividedBy($origQtyBd, 8, RoundingMode::HalfUp);

                $origLineTotal = Money::of((string) $origLine->line_total);
                $origTax = Money::of((string) $origLine->tax_amount);
                $origSd = Money::of((string) $origLine->supplementary_duty_amount);

                $retLineSubtotal = $origLineTotal->multiply($ratio)->round(2);
                $retLineTax = $origTax->multiply($ratio)->round(2);
                $retLineSd = $origSd->multiply($ratio)->round(2);

                $returnedSubtotal = $returnedSubtotal->add($retLineSubtotal);
                $returnedTax = $returnedTax->add($retLineTax);
                $returnedSd = $returnedSd->add($retLineSd);

                $cnLinesData[] = [
                    'id' => (string) Str::uuid(),
                    'sales_invoice_line_id' => $origLine->id,
                    'product_variant_id' => $origLine->product_variant_id,
                    'item_description' => $origLine->item_description,
                    'uom' => $origLine->uom,
                    'quantity' => (string) $retQtyBd,
                    'unit_price' => (string) $origLine->unit_price,
                    'subtotal' => (string) $retLineSubtotal->getBigDecimal(),
                    'vat_rate' => (string) $origLine->vat_rate,
                    'tax_amount' => (string) $retLineTax->getBigDecimal(),
                    'supplementary_duty_rate' => (string) $origLine->supplementary_duty_rate,
                    'supplementary_duty_amount' => (string) $retLineSd->getBigDecimal(),
                    'line_total' => (string) $retLineSubtotal->add($retLineTax)->add($retLineSd)->getBigDecimal(),
                ];
            }

            $totalRefund = $returnedSubtotal->add($returnedSd)->add($returnedTax);
            $creditNoteId = (string) Str::uuid();

            // Insert Credit Note (Mushak 6.7)
            DB::table('credit_notes')->insert([
                'id' => $creditNoteId,
                'tenant_id' => $tenantId,
                'company_id' => $invoice->company_id,
                'branch_id' => $invoice->branch_id,
                'sales_invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'credit_note_number' => $creditNoteNumber,
                'credit_note_date' => $returnDate,
                'reason' => $request->reason ?? 'Customer Return',
                'status' => 'issued',
                'subtotal' => (string) $returnedSubtotal->getBigDecimal(),
                'discount_amount' => '0.0000',
                'supplementary_duty_amount' => (string) $returnedSd->getBigDecimal(),
                'tax_amount' => (string) $returnedTax->getBigDecimal(),
                'total_amount' => (string) $totalRefund->getBigDecimal(),
                'mushak_6_7_number' => $creditNoteNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert Credit Note Lines
            foreach ($cnLinesData as $line) {
                DB::table('credit_note_lines')->insert([
                    'id' => $line['id'],
                    'tenant_id' => $tenantId,
                    'company_id' => $invoice->company_id,
                    'credit_note_id' => $creditNoteId,
                    'sales_invoice_line_id' => $line['sales_invoice_line_id'],
                    'product_variant_id' => $line['product_variant_id'],
                    'item_description' => $line['item_description'],
                    'uom' => $line['uom'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'subtotal' => $line['subtotal'],
                    'vat_rate' => $line['vat_rate'],
                    'tax_amount' => $line['tax_amount'],
                    'supplementary_duty_rate' => $line['supplementary_duty_rate'],
                    'supplementary_duty_amount' => $line['supplementary_duty_amount'],
                    'line_total' => $line['line_total'],
                    'created_at' => now(),
                ]);
            }

            // Post Reversing Journal Entry in the same transaction
            $accountMap = $request->accountMap;
            $cashAccountId = $accountMap['cash_or_ar'] ?? $this->getRequiredAccountId($invoice->company_id, '1010');
            $salesAccountId = $accountMap['sales'] ?? $this->getRequiredAccountId($invoice->company_id, '4010');
            $vatPayableAccountId = $accountMap['vat_payable'] ?? $this->getRequiredAccountId($invoice->company_id, '2020');
            $sdPayableAccountId = $accountMap['sd_payable'] ?? $this->findOptionalAccountId($invoice->company_id, '2030');

            $journalLines = [];

            // Debit: Sales / Returns for returned subtotal
            $journalLines[] = new JournalLineDTO(
                accountId: $salesAccountId,
                debitAmount: $returnedSubtotal,
                creditAmount: Money::zero('BDT'),
                branchId: $invoice->branch_id,
                lineDescription: "Mushak 6.7 Credit Note {$creditNoteNumber} - Sales Reversal",
            );

            // Debit: VAT Payable for returned VAT
            if (! $returnedTax->getBigDecimal()->isZero()) {
                $journalLines[] = new JournalLineDTO(
                    accountId: $vatPayableAccountId,
                    debitAmount: $returnedTax,
                    creditAmount: Money::zero('BDT'),
                    branchId: $invoice->branch_id,
                    lineDescription: "Mushak 6.7 Credit Note {$creditNoteNumber} - VAT Reversal",
                );
            }

            // Debit: SD Payable for returned SD
            if (! $returnedSd->getBigDecimal()->isZero() && $sdPayableAccountId) {
                $journalLines[] = new JournalLineDTO(
                    accountId: $sdPayableAccountId,
                    debitAmount: $returnedSd,
                    creditAmount: Money::zero('BDT'),
                    branchId: $invoice->branch_id,
                    lineDescription: "Mushak 6.7 Credit Note {$creditNoteNumber} - SD Reversal",
                );
            }

            // Credit: Cash / AR for Total Refund
            $journalLines[] = new JournalLineDTO(
                accountId: $cashAccountId,
                debitAmount: Money::zero('BDT'),
                creditAmount: $totalRefund,
                branchId: $invoice->branch_id,
                lineDescription: "Mushak 6.7 Credit Note {$creditNoteNumber} - Cash/Payable Refund",
            );

            $journalEntryDTO = new JournalEntryDTO(
                companyId: $invoice->company_id,
                periodId: $period->id,
                documentType: 'credit_note',
                postingDate: $returnDate,
                businessDate: $returnDate,
                referenceType: 'credit_note',
                referenceId: $creditNoteId,
                postedBy: $request->postedBy,
                branchId: $invoice->branch_id,
                narration: "Mushak 6.7 Credit Note {$creditNoteNumber} Reversing Invoice {$invoice->invoice_number}",
                isReversal: false,
                lines: $journalLines,
            );

            $this->postingService->post($journalEntryDTO);

            return CreditNote::with('lines')->findOrFail($creditNoteId);
        });
    }

    protected function getRequiredAccountId(string $companyId, string $accountCode): string
    {
        /** @var ChartOfAccount|null $account */
        $account = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('account_code', $accountCode)
            ->first();

        if (! $account) {
            throw new InvalidArgumentException("Required Chart of Account code [{$accountCode}] not found for company [{$companyId}].");
        }

        return $account->id;
    }

    protected function findOptionalAccountId(string $companyId, string $accountCode): ?string
    {
        /** @var ChartOfAccount|null $account */
        $account = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('account_code', $accountCode)
            ->first();

        return $account?->id;
    }

    protected function resolveFallbackTaxCodeId(string $companyId): string
    {
        /** @var TaxCode|null $taxCode */
        $taxCode = TaxCode::query()
            ->where('company_id', $companyId)
            ->first();

        if (! $taxCode) {
            throw new InvalidArgumentException("No tax code found for company [{$companyId}].");
        }

        return $taxCode->id;
    }
}
