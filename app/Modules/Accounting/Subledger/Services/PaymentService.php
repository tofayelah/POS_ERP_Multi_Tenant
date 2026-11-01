<?php

namespace App\Modules\Accounting\Subledger\Services;

use App\Core\Tenancy\TenantContext;
use App\Core\ValueObjects\Money;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SubledgerOpenItem;
use App\Modules\Accounting\Contracts\AccountingPostingServiceContract;
use App\Modules\Accounting\Contracts\NumberSeriesContract;
use App\Modules\Accounting\DTOs\JournalEntryDTO;
use App\Modules\Accounting\DTOs\JournalLineDTO;
use App\Modules\Accounting\Subledger\Contracts\PaymentServiceContract;
use App\Modules\Accounting\Subledger\DTOs\AllocationItemDTO;
use App\Modules\Accounting\Subledger\DTOs\RecordPaymentDTO;
use App\Modules\Accounting\Subledger\Exceptions\AllocationExceedsOpenItemException;
use App\Modules\Accounting\Subledger\Exceptions\AllocationExceedsPaymentException;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentService implements PaymentServiceContract
{
    public function __construct(
        protected NumberSeriesContract $numberSeries,
        protected AccountingPostingServiceContract $postingService,
    ) {}

    /**
     * Record a payment and allocate across open items atomically in the same transaction.
     */
    public function recordPayment(RecordPaymentDTO $dto): Payment
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new InvalidArgumentException('Cannot record payment without active TenantContext.');
            }

            $companyId = $dto->companyId;
            $branchId = $dto->branchId;
            $paymentAmount = Money::of($dto->amount);

            if ($paymentAmount->getBigDecimal()->isNegativeOrZero()) {
                throw new InvalidArgumentException('Payment amount must be greater than zero.');
            }

            $paymentDate = $dto->paymentDate instanceof CarbonInterface
                ? $dto->paymentDate->toDateString()
                : Carbon::parse($dto->paymentDate)->toDateString();

            // 1. Resolve open accounting period
            /** @var AccountingPeriod|null $period */
            $period = AccountingPeriod::query()
                ->where('tenant_id', $tenantId)
                ->where('company_id', $companyId)
                ->where('status', 'open')
                ->where('start_date', '<=', $paymentDate)
                ->where('end_date', '>=', $paymentDate)
                ->with('fiscalYear')
                ->first();

            if (! $period) {
                throw new InvalidArgumentException("No open accounting period found for payment date [{$paymentDate}].");
            }

            // 2. Validate allocations against payment amount
            $totalAllocated = Money::zero('BDT');
            foreach ($dto->allocations as $alloc) {
                $allocAmount = Money::of($alloc->amount);
                $totalAllocated = $totalAllocated->add($allocAmount);
            }

            if ($totalAllocated->greaterThan($paymentAmount)) {
                throw new AllocationExceedsPaymentException($paymentAmount, $totalAllocated);
            }

            // 3. Draw gapless sequence number
            $docType = $dto->paymentType === 'inbound' ? 'RCP' : 'PV';
            $paymentNumber = $this->numberSeries->getNextNumber(
                companyId: $companyId,
                branchId: $branchId,
                documentType: $docType,
                fiscalYear: $period->fiscalYear->title
            );

            $paymentId = (string) Str::uuid();

            // 4. Insert Payment record
            DB::table('payments')->insert([
                'id' => $paymentId,
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'payment_number' => $paymentNumber,
                'payment_type' => $dto->paymentType,
                'payment_method' => $dto->paymentMethod,
                'party_type' => $dto->partyType,
                'party_id' => $dto->partyId,
                'payment_date' => $paymentDate,
                'amount' => (string) $paymentAmount->getBigDecimal(),
                'reference' => $dto->reference,
                'status' => 'posted',
                'created_by' => $dto->postedBy ?? (string) Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 5. Create allocations
            foreach ($dto->allocations as $alloc) {
                $allocAmount = Money::of($alloc->amount);

                /** @var SubledgerOpenItem|null $openItem */
                $openItem = SubledgerOpenItem::query()
                    ->where('tenant_id', $tenantId)
                    ->where('company_id', $companyId)
                    ->where('id', $alloc->openItemId)
                    ->lockForUpdate()
                    ->first();

                if (! $openItem) {
                    throw new InvalidArgumentException("Subledger open item [{$alloc->openItemId}] not found.");
                }

                $remaining = Money::of((string) $openItem->remaining_amount);
                if ($allocAmount->greaterThan($remaining)) {
                    throw new AllocationExceedsOpenItemException($openItem->id, $remaining, $allocAmount);
                }

                DB::table('payment_allocations')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'company_id' => $companyId,
                    'payment_id' => $paymentId,
                    'credit_note_id' => null,
                    'open_item_id' => $openItem->id,
                    'allocated_amount' => (string) $allocAmount->getBigDecimal(),
                    'allocation_date' => $paymentDate,
                    'notes' => $alloc->notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 6. Post balanced double-entry GL journal atomically
            $accountMap = $dto->accountMap;
            $journalLines = [];

            if ($dto->paymentType === 'inbound') {
                // Inbound Customer Receipt: Debit Tender (1010/1020), Credit Accounts Receivable (1030)
                $tenderCode = in_array($dto->paymentMethod, ['bank', 'cheque', 'bkash', 'nagad'], true) ? '1020' : '1010';
                $tenderAccountId = $accountMap['tender'] ?? $this->getRequiredAccountId($companyId, $tenderCode);
                $arAccountId = $accountMap['ar'] ?? $this->getRequiredAccountId($companyId, '1030');

                // Debit: Cash/Bank/MFS
                $journalLines[] = new JournalLineDTO(
                    accountId: $tenderAccountId,
                    debitAmount: $paymentAmount,
                    creditAmount: Money::zero('BDT'),
                    branchId: $branchId,
                    lineDescription: "Receipt {$paymentNumber} - {$dto->paymentMethod}",
                );

                // Credit: Accounts Receivable
                $journalLines[] = new JournalLineDTO(
                    accountId: $arAccountId,
                    debitAmount: Money::zero('BDT'),
                    creditAmount: $paymentAmount,
                    branchId: $branchId,
                    lineDescription: "Receipt {$paymentNumber} - Customer Settlement",
                );
            } else {
                // Outbound Supplier Disbursement: Debit Accounts Payable (2010), Credit Tender (1010/1020)
                $tenderCode = in_array($dto->paymentMethod, ['bank', 'cheque', 'bkash', 'nagad'], true) ? '1020' : '1010';
                $tenderAccountId = $accountMap['tender'] ?? $this->getRequiredAccountId($companyId, $tenderCode);
                $apAccountId = $accountMap['ap'] ?? $this->getRequiredAccountId($companyId, '2010');

                // Debit: Accounts Payable
                $journalLines[] = new JournalLineDTO(
                    accountId: $apAccountId,
                    debitAmount: $paymentAmount,
                    creditAmount: Money::zero('BDT'),
                    branchId: $branchId,
                    lineDescription: "Payment Voucher {$paymentNumber} - Supplier Settlement",
                );

                // Credit: Cash/Bank
                $journalLines[] = new JournalLineDTO(
                    accountId: $tenderAccountId,
                    debitAmount: Money::zero('BDT'),
                    creditAmount: $paymentAmount,
                    branchId: $branchId,
                    lineDescription: "Payment Voucher {$paymentNumber} - {$dto->paymentMethod}",
                );
            }

            $journalEntryDTO = new JournalEntryDTO(
                companyId: $companyId,
                periodId: $period->id,
                documentType: $docType,
                postingDate: $paymentDate,
                businessDate: $paymentDate,
                referenceType: 'payment',
                referenceId: $paymentId,
                postedBy: $dto->postedBy ?? (string) Str::uuid(),
                branchId: $branchId,
                narration: "Payment {$paymentNumber} via {$dto->paymentMethod}",
                lines: $journalLines
            );

            $journal = $this->postingService->post($journalEntryDTO);

            // Update payment with posted GL journal entry ID
            DB::table('payments')->where('id', $paymentId)->update([
                'gl_journal_entry_id' => $journal->id,
                'updated_at' => now(),
            ]);

            return Payment::with('allocations')->findOrFail($paymentId);
        });
    }

    /**
     * Allocate an existing unallocated payment across open subledger items.
     *
     * @param  array<int, AllocationItemDTO>  $allocations
     * @return array<int, PaymentAllocation>
     */
    public function allocatePayment(string $paymentId, array $allocations): array
    {
        return DB::transaction(function () use ($paymentId, $allocations) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new InvalidArgumentException('Cannot allocate payment without active TenantContext.');
            }

            /** @var Payment|null $payment */
            $payment = Payment::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $paymentId)
                ->lockForUpdate()
                ->firstOrFail();

            // Calculate currently allocated sum on this payment
            $alreadyAllocatedSum = DB::table('payment_allocations')
                ->where('tenant_id', $tenantId)
                ->where('payment_id', $paymentId)
                ->sum('allocated_amount');

            $availablePayment = Money::of((string) $payment->amount)
                ->subtract(Money::of((string) ($alreadyAllocatedSum ?: '0.0000')));

            $newAllocSum = Money::zero('BDT');
            foreach ($allocations as $item) {
                $newAllocSum = $newAllocSum->add(Money::of($item->amount));
            }

            if ($newAllocSum->greaterThan($availablePayment)) {
                throw new AllocationExceedsPaymentException($availablePayment, $newAllocSum);
            }

            $createdAllocations = [];
            foreach ($allocations as $item) {
                $allocAmount = Money::of($item->amount);

                /** @var SubledgerOpenItem|null $openItem */
                $openItem = SubledgerOpenItem::query()
                    ->where('tenant_id', $tenantId)
                    ->where('company_id', $payment->company_id)
                    ->where('id', $item->openItemId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $remaining = Money::of((string) $openItem->remaining_amount);
                if ($allocAmount->greaterThan($remaining)) {
                    throw new AllocationExceedsOpenItemException($openItem->id, $remaining, $allocAmount);
                }

                $allocId = (string) Str::uuid();
                DB::table('payment_allocations')->insert([
                    'id' => $allocId,
                    'tenant_id' => $tenantId,
                    'company_id' => $payment->company_id,
                    'payment_id' => $payment->id,
                    'credit_note_id' => null,
                    'open_item_id' => $openItem->id,
                    'allocated_amount' => (string) $allocAmount->getBigDecimal(),
                    'allocation_date' => $payment->payment_date->toDateString(),
                    'notes' => $item->notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $createdAllocations[] = PaymentAllocation::findOrFail($allocId);
            }

            return $createdAllocations;
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
            throw new InvalidArgumentException("Required GL account code [{$accountCode}] not found in company chart of accounts.");
        }

        return $account->id;
    }
}
