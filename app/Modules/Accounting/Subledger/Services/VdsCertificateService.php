<?php

namespace App\Modules\Accounting\Subledger\Services;

use App\Core\Tenancy\TenantContext;
use App\Core\ValueObjects\Money;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\SubledgerOpenItem;
use App\Models\VdsCertificate;
use App\Modules\Accounting\Contracts\AccountingPostingServiceContract;
use App\Modules\Accounting\Contracts\NumberSeriesContract;
use App\Modules\Accounting\DTOs\JournalEntryDTO;
use App\Modules\Accounting\DTOs\JournalLineDTO;
use App\Modules\Accounting\Subledger\Contracts\VdsCertificateContract;
use App\Modules\Accounting\Subledger\DTOs\CustomerVdsDeductionDTO;
use App\Modules\Accounting\Subledger\DTOs\SupplierVdsWithholdingDTO;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class VdsCertificateService implements VdsCertificateContract
{
    public function __construct(
        protected NumberSeriesContract $numberSeries,
        protected AccountingPostingServiceContract $postingService,
    ) {}

    /**
     * Record a Mushak 6.6 VDS certificate received from a corporate buyer.
     */
    public function recordCustomerVdsDeduction(CustomerVdsDeductionDTO $dto): VdsCertificate
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new InvalidArgumentException('Cannot record VDS certificate without active TenantContext.');
            }

            $companyId = $dto->companyId;
            $branchId = $dto->branchId;

            $netPayment = Money::of($dto->netPaymentAmount);
            $vdsAmount = Money::of($dto->vdsAmount);
            $totalSettlement = $netPayment->add($vdsAmount);

            if ($netPayment->getBigDecimal()->isNegative()) {
                throw new InvalidArgumentException('Net payment amount cannot be negative.');
            }
            if ($vdsAmount->getBigDecimal()->isNegativeOrZero()) {
                throw new InvalidArgumentException('VDS amount must be strictly greater than zero.');
            }

            $issueDate = $dto->issueDate instanceof CarbonInterface
                ? $dto->issueDate->toDateString()
                : Carbon::parse($dto->issueDate)->toDateString();

            /** @var AccountingPeriod|null $period */
            $period = AccountingPeriod::query()
                ->where('tenant_id', $tenantId)
                ->where('company_id', $companyId)
                ->where('status', 'open')
                ->where('start_date', '<=', $issueDate)
                ->where('end_date', '>=', $issueDate)
                ->with('fiscalYear')
                ->first();

            if (! $period) {
                throw new InvalidArgumentException("No open accounting period found for date [{$issueDate}].");
            }

            /** @var SubledgerOpenItem|null $openItem */
            $openItem = SubledgerOpenItem::query()
                ->where('tenant_id', $tenantId)
                ->where('company_id', $companyId)
                ->where('party_type', 'customer')
                ->where('party_id', $dto->customerId)
                ->where('source_type', 'sales_invoice')
                ->where('source_id', $dto->salesInvoiceId)
                ->lockForUpdate()
                ->first();

            if (! $openItem) {
                throw new InvalidArgumentException("Subledger open item for sales invoice [{$dto->salesInvoiceId}] not found.");
            }

            $remaining = Money::of((string) $openItem->remaining_amount);
            if ($totalSettlement->greaterThan($remaining)) {
                throw new InvalidArgumentException("Settlement amount ({$totalSettlement->getBigDecimal()}) exceeds remaining open balance ({$remaining->getBigDecimal()}) of open item [{$openItem->id}].");
            }

            // Draw sequence for Customer Receipt
            $paymentNumber = $this->numberSeries->getNextNumber(
                companyId: $companyId,
                branchId: $branchId,
                documentType: 'CR',
                fiscalYear: $period->fiscalYear->title
            );

            $paymentId = (string) Str::uuid();

            // Insert Payment
            DB::table('payments')->insert([
                'id' => $paymentId,
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'payment_number' => $paymentNumber,
                'payment_type' => 'inbound',
                'payment_method' => $dto->paymentMethod,
                'party_type' => 'customer',
                'party_id' => $dto->customerId,
                'payment_date' => $issueDate,
                'amount' => (string) $netPayment->getBigDecimal(),
                'reference' => $dto->reference,
                'status' => 'posted',
                'created_by' => $dto->createdBy,
                'approved_by' => $dto->createdBy,
                'approved_at' => now(),
                'gl_journal_entry_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert Allocation (Trigger will deduct totalSettlement from openItem remaining balance)
            DB::table('payment_allocations')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'payment_id' => $paymentId,
                'credit_note_id' => null,
                'open_item_id' => $openItem->id,
                'allocated_amount' => (string) $totalSettlement->getBigDecimal(),
                'allocation_date' => $issueDate,
                'notes' => "Settled via {$dto->paymentMethod} ({$dto->netPaymentAmount}) + Mushak 6.6 VDS ({$dto->vdsAmount})",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $certificateId = (string) Str::uuid();

            // Insert VdsCertificate
            DB::table('vds_certificates')->insert([
                'id' => $certificateId,
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'certificate_number' => $dto->certificateNumber,
                'direction' => 'received_from_customer',
                'customer_id' => $dto->customerId,
                'supplier_id' => null,
                'withholding_entity_name' => $dto->withholdingEntityName,
                'withholding_entity_bin' => $dto->withholdingEntityBin,
                'issue_date' => $issueDate,
                'tax_period' => $dto->taxPeriod,
                'total_invoice_amount' => (string) Money::of($dto->totalInvoiceAmount)->getBigDecimal(),
                'total_vat_amount' => (string) Money::of($dto->totalVatAmount)->getBigDecimal(),
                'vds_amount' => (string) $vdsAmount->getBigDecimal(),
                'sales_invoice_id' => $dto->salesInvoiceId,
                'supplier_bill_id' => null,
                'payment_id' => $paymentId,
                'status' => 'valid',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Post double entry GL journal:
            // Debit Bank/Cash (1020/1010) $netPayment
            // Debit VDS Receivable (1060) $vdsAmount
            // Credit Accounts Receivable (1030) $totalSettlement
            $tenderCode = in_array($dto->paymentMethod, ['bank', 'cheque', 'bkash', 'nagad'], true) ? '1020' : '1010';
            $tenderAccountId = $this->getRequiredAccountId($companyId, $tenderCode);
            $vdsAccountId = $this->getRequiredAccountId($companyId, '1060');
            $arAccountId = $this->getRequiredAccountId($companyId, '1030');

            $journalLines = [
                new JournalLineDTO(
                    accountId: $tenderAccountId,
                    debitAmount: $netPayment,
                    creditAmount: Money::zero('BDT'),
                    branchId: $branchId,
                    lineDescription: "Receipt {$paymentNumber} - Net Bank Tender",
                ),
                new JournalLineDTO(
                    accountId: $vdsAccountId,
                    debitAmount: $vdsAmount,
                    creditAmount: Money::zero('BDT'),
                    branchId: $branchId,
                    lineDescription: "Mushak 6.6 VDS Certificate {$dto->certificateNumber}",
                ),
                new JournalLineDTO(
                    accountId: $arAccountId,
                    debitAmount: Money::zero('BDT'),
                    creditAmount: $totalSettlement,
                    branchId: $branchId,
                    lineDescription: "Receipt {$paymentNumber} - Invoice {$openItem->document_number} Settlement",
                ),
            ];

            $journalEntryDTO = new JournalEntryDTO(
                companyId: $companyId,
                periodId: $period->id,
                documentType: 'CR',
                postingDate: $issueDate,
                businessDate: $issueDate,
                referenceType: 'vds_certificate',
                referenceId: $certificateId,
                postedBy: $dto->createdBy,
                branchId: $branchId,
                narration: "Mushak 6.6 VDS deduction by customer {$dto->withholdingEntityName} cert #{$dto->certificateNumber}",
                lines: $journalLines
            );

            $journal = $this->postingService->post($journalEntryDTO);

            // Link journal to payment
            DB::table('payments')->where('id', $paymentId)->update([
                'gl_journal_entry_id' => $journal->id,
                'updated_at' => now(),
            ]);

            return VdsCertificate::findOrFail($certificateId);
        });
    }

    /**
     * Issue a Mushak 6.6 VDS certificate to a supplier when paying a bill with VAT withheld.
     */
    public function issueSupplierVdsCertificate(SupplierVdsWithholdingDTO $dto): VdsCertificate
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new InvalidArgumentException('Cannot issue VDS certificate without active TenantContext.');
            }

            $companyId = $dto->companyId;
            $branchId = $dto->branchId;

            $netPayment = Money::of($dto->netPaymentAmount);
            $vdsAmount = Money::of($dto->vdsAmount);
            $totalSettlement = $netPayment->add($vdsAmount);

            if ($netPayment->getBigDecimal()->isNegative()) {
                throw new InvalidArgumentException('Net payment amount cannot be negative.');
            }
            if ($vdsAmount->getBigDecimal()->isNegativeOrZero()) {
                throw new InvalidArgumentException('VDS amount must be strictly greater than zero.');
            }

            $issueDate = $dto->issueDate instanceof CarbonInterface
                ? $dto->issueDate->toDateString()
                : Carbon::parse($dto->issueDate)->toDateString();

            /** @var AccountingPeriod|null $period */
            $period = AccountingPeriod::query()
                ->where('tenant_id', $tenantId)
                ->where('company_id', $companyId)
                ->where('status', 'open')
                ->where('start_date', '<=', $issueDate)
                ->where('end_date', '>=', $issueDate)
                ->with('fiscalYear')
                ->first();

            if (! $period) {
                throw new InvalidArgumentException("No open accounting period found for date [{$issueDate}].");
            }

            /** @var SubledgerOpenItem|null $openItem */
            $openItem = SubledgerOpenItem::query()
                ->where('tenant_id', $tenantId)
                ->where('company_id', $companyId)
                ->where('party_type', 'supplier')
                ->where('party_id', $dto->supplierId)
                ->where('source_type', 'supplier_bill')
                ->where('source_id', $dto->supplierBillId)
                ->lockForUpdate()
                ->first();

            if (! $openItem) {
                throw new InvalidArgumentException("Subledger open item for supplier bill [{$dto->supplierBillId}] not found.");
            }

            $remaining = Money::of((string) $openItem->remaining_amount);
            if ($totalSettlement->greaterThan($remaining)) {
                throw new InvalidArgumentException("Settlement amount ({$totalSettlement->getBigDecimal()}) exceeds remaining open balance ({$remaining->getBigDecimal()}) of open item [{$openItem->id}].");
            }

            /** @var Company $company */
            $company = Company::findOrFail($companyId);

            // Draw sequence for Outbound Payment
            $paymentNumber = $this->numberSeries->getNextNumber(
                companyId: $companyId,
                branchId: $branchId,
                documentType: 'PV',
                fiscalYear: $period->fiscalYear->title
            );

            $paymentId = (string) Str::uuid();

            // Insert Payment
            DB::table('payments')->insert([
                'id' => $paymentId,
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'payment_number' => $paymentNumber,
                'payment_type' => 'outbound',
                'payment_method' => $dto->paymentMethod,
                'party_type' => 'supplier',
                'party_id' => $dto->supplierId,
                'payment_date' => $issueDate,
                'amount' => (string) $netPayment->getBigDecimal(),
                'reference' => $dto->reference,
                'status' => 'posted',
                'created_by' => $dto->createdBy,
                'approved_by' => $dto->createdBy,
                'approved_at' => now(),
                'gl_journal_entry_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert Allocation
            DB::table('payment_allocations')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'payment_id' => $paymentId,
                'credit_note_id' => null,
                'open_item_id' => $openItem->id,
                'allocated_amount' => (string) $totalSettlement->getBigDecimal(),
                'allocation_date' => $issueDate,
                'notes' => "Disbursed via {$dto->paymentMethod} ({$dto->netPaymentAmount}) + Withheld VDS Mushak 6.6 ({$dto->vdsAmount})",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $certificateId = (string) Str::uuid();

            // Insert VdsCertificate
            DB::table('vds_certificates')->insert([
                'id' => $certificateId,
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'certificate_number' => $dto->certificateNumber,
                'direction' => 'issued_to_supplier',
                'customer_id' => null,
                'supplier_id' => $dto->supplierId,
                'withholding_entity_name' => $company->legal_name,
                'withholding_entity_bin' => $company->bin_number ?? '0000000000000',
                'issue_date' => $issueDate,
                'tax_period' => $dto->taxPeriod,
                'total_invoice_amount' => (string) Money::of($dto->totalBillAmount)->getBigDecimal(),
                'total_vat_amount' => (string) Money::of($dto->totalVatAmount)->getBigDecimal(),
                'vds_amount' => (string) $vdsAmount->getBigDecimal(),
                'sales_invoice_id' => null,
                'supplier_bill_id' => $dto->supplierBillId,
                'payment_id' => $paymentId,
                'status' => 'valid',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Post double entry GL journal:
            // Debit Accounts Payable (2010) $totalSettlement
            // Credit Bank/Cash (1020/1010) $netPayment
            // Credit VDS Payable (2040) $vdsAmount
            $tenderCode = in_array($dto->paymentMethod, ['bank', 'cheque', 'bkash', 'nagad'], true) ? '1020' : '1010';
            $tenderAccountId = $this->getRequiredAccountId($companyId, $tenderCode);
            $apAccountId = $this->getRequiredAccountId($companyId, '2010');
            $vdsPayableAccountId = $this->getRequiredAccountId($companyId, '2040');

            $journalLines = [
                new JournalLineDTO(
                    accountId: $apAccountId,
                    debitAmount: $totalSettlement,
                    creditAmount: Money::zero('BDT'),
                    branchId: $branchId,
                    lineDescription: "Payment {$paymentNumber} - Supplier Bill {$openItem->document_number} Settlement",
                ),
                new JournalLineDTO(
                    accountId: $tenderAccountId,
                    debitAmount: Money::zero('BDT'),
                    creditAmount: $netPayment,
                    branchId: $branchId,
                    lineDescription: "Payment {$paymentNumber} - Net Bank Tender",
                ),
                new JournalLineDTO(
                    accountId: $vdsPayableAccountId,
                    debitAmount: Money::zero('BDT'),
                    creditAmount: $vdsAmount,
                    branchId: $branchId,
                    lineDescription: "Mushak 6.6 Withheld VDS Certificate {$dto->certificateNumber}",
                ),
            ];

            $journalEntryDTO = new JournalEntryDTO(
                companyId: $companyId,
                periodId: $period->id,
                documentType: 'PV',
                postingDate: $issueDate,
                businessDate: $issueDate,
                referenceType: 'vds_certificate',
                referenceId: $certificateId,
                postedBy: $dto->createdBy,
                branchId: $branchId,
                narration: "Mushak 6.6 VDS withholding on supplier bill {$openItem->document_number} cert #{$dto->certificateNumber}",
                lines: $journalLines
            );

            $journal = $this->postingService->post($journalEntryDTO);

            // Link journal to payment
            DB::table('payments')->where('id', $paymentId)->update([
                'gl_journal_entry_id' => $journal->id,
                'updated_at' => now(),
            ]);

            return VdsCertificate::findOrFail($certificateId);
        });
    }

    protected function getRequiredAccountId(string $companyId, string $accountCode): string
    {
        $tenantId = TenantContext::id();

        /** @var ChartOfAccount|null $account */
        $account = ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('account_code', $accountCode)
            ->first();

        if (! $account) {
            throw new InvalidArgumentException("Required GL account code [{$accountCode}] not found in chart of accounts.");
        }

        return $account->id;
    }
}
