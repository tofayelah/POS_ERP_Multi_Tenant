<?php

namespace App\Modules\Accounting\Subledger\Services;

use App\Core\Tenancy\TenantContext;
use App\Core\ValueObjects\Money;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Payment;
use App\Models\SubledgerOpenItem;
use App\Models\Supplier;
use App\Modules\Accounting\Contracts\AccountingPostingServiceContract;
use App\Modules\Accounting\Contracts\NumberSeriesContract;
use App\Modules\Accounting\DTOs\JournalEntryDTO;
use App\Modules\Accounting\DTOs\JournalLineDTO;
use App\Modules\Accounting\Subledger\Contracts\SupplierPaymentVoucherContract;
use App\Modules\Accounting\Subledger\DTOs\CreateSupplierVoucherDTO;
use App\Modules\Accounting\Subledger\Exceptions\AllocationExceedsOpenItemException;
use App\Modules\Accounting\Subledger\Exceptions\AllocationExceedsPaymentException;
use App\Modules\Accounting\Subledger\Exceptions\SegregationOfDutiesException;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SupplierPaymentVoucherService implements SupplierPaymentVoucherContract
{
    public function __construct(
        protected NumberSeriesContract $numberSeries,
        protected AccountingPostingServiceContract $postingService,
    ) {}

    /**
     * Draft a supplier payment voucher (pending approval).
     */
    public function createVoucher(CreateSupplierVoucherDTO $dto): Payment
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new InvalidArgumentException('Cannot create payment voucher without active TenantContext.');
            }

            $companyId = $dto->companyId;
            $branchId = $dto->branchId;
            $voucherAmount = Money::of($dto->amount);

            if ($voucherAmount->getBigDecimal()->isNegativeOrZero()) {
                throw new InvalidArgumentException('Voucher amount must be greater than zero.');
            }

            $paymentDate = $dto->paymentDate instanceof CarbonInterface
                ? $dto->paymentDate->toDateString()
                : Carbon::parse($dto->paymentDate)->toDateString();

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
                throw new InvalidArgumentException("No open accounting period found for date [{$paymentDate}].");
            }

            // Validate total allocation doesn't exceed voucher amount
            $totalAllocated = Money::zero('BDT');
            foreach ($dto->allocations as $alloc) {
                $allocAmount = Money::of($alloc->amount);
                $totalAllocated = $totalAllocated->add($allocAmount);
            }

            if ($totalAllocated->greaterThan($voucherAmount)) {
                throw new AllocationExceedsPaymentException($voucherAmount, $totalAllocated);
            }

            // Draw gapless sequence voucher number
            $paymentNumber = $this->numberSeries->getNextNumber(
                companyId: $companyId,
                branchId: $branchId,
                documentType: 'PV',
                fiscalYear: $period->fiscalYear->title
            );

            $paymentId = (string) Str::uuid();

            // Insert voucher with status = 'pending_approval'
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
                'payment_date' => $paymentDate,
                'amount' => (string) $voucherAmount->getBigDecimal(),
                'reference' => $dto->reference,
                'status' => 'pending_approval',
                'created_by' => $dto->createdBy,
                'approved_by' => null,
                'approved_at' => null,
                'gl_journal_entry_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create allocations (reserves open item balance via trigger)
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

            return Payment::findOrFail($paymentId);
        });
    }

    /**
     * Authorize and post a supplier payment voucher with Segregation of Duties (SoD §3.3) validation.
     */
    public function approveVoucher(string $paymentId, string $approverUserId): Payment
    {
        return DB::transaction(function () use ($paymentId, $approverUserId) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new InvalidArgumentException('Cannot approve voucher without active TenantContext.');
            }

            /** @var Payment $payment */
            $payment = Payment::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $paymentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status !== 'pending_approval') {
                throw new InvalidArgumentException("Payment voucher [{$paymentId}] is not pending approval (current status: {$payment->status}).");
            }

            // 1. Rule SoD-01 / Dual-Signoff: Creator cannot approve their own payment voucher
            if ($payment->created_by === $approverUserId) {
                throw new SegregationOfDutiesException('Rule SoD-01 Violation: Creator cannot approve their own payment voucher.');
            }

            // 2. Rule SoD-02: User who created or edited the supplier record cannot authorize disbursements
            /** @var Supplier $supplier */
            $supplier = Supplier::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $payment->party_id)
                ->firstOrFail();

            if ($supplier->created_by === $approverUserId) {
                throw new SegregationOfDutiesException('Rule SoD-02 Violation: User who created the supplier record cannot authorize disbursements to that supplier.');
            }

            // 3. Rule SoD-01: User who created or approved the purchase order/bill cannot post payment
            $hasConflictWithBill = DB::table('payment_allocations')
                ->join('subledger_open_items', 'payment_allocations.open_item_id', '=', 'subledger_open_items.id')
                ->join('supplier_bills', 'subledger_open_items.source_id', '=', 'supplier_bills.id')
                ->where('payment_allocations.payment_id', $paymentId)
                ->where(function ($q) use ($approverUserId) {
                    $q->where('supplier_bills.created_by', $approverUserId)
                        ->orWhere('supplier_bills.approved_by', $approverUserId);
                })
                ->exists();

            if ($hasConflictWithBill) {
                throw new SegregationOfDutiesException('Rule SoD-01 Violation: User who created or approved the purchase bill cannot post the corresponding supplier payment.');
            }

            // 4. Resolve open period for posting
            $paymentDate = $payment->payment_date->toDateString();
            /** @var AccountingPeriod|null $period */
            $period = AccountingPeriod::query()
                ->where('tenant_id', $tenantId)
                ->where('company_id', $payment->company_id)
                ->where('status', 'open')
                ->where('start_date', '<=', $paymentDate)
                ->where('end_date', '>=', $paymentDate)
                ->first();

            if (! $period) {
                throw new InvalidArgumentException("No open accounting period found for date [{$paymentDate}].");
            }

            $amount = Money::of((string) $payment->amount);
            $tenderCode = in_array($payment->payment_method, ['bank', 'cheque', 'bkash', 'nagad'], true) ? '1020' : '1010';
            $tenderAccountId = $this->getRequiredAccountId($payment->company_id, $tenderCode);
            $apAccountId = $this->getRequiredAccountId($payment->company_id, '2010');

            // 5. Post double-entry GL journal: Debit AP (2010), Credit Bank/Cash (1020/1010)
            $journalLines = [
                new JournalLineDTO(
                    accountId: $apAccountId,
                    debitAmount: $amount,
                    creditAmount: Money::zero('BDT'),
                    branchId: $payment->branch_id,
                    lineDescription: "Payment Voucher {$payment->payment_number} - Supplier Settlement",
                ),
                new JournalLineDTO(
                    accountId: $tenderAccountId,
                    debitAmount: Money::zero('BDT'),
                    creditAmount: $amount,
                    branchId: $payment->branch_id,
                    lineDescription: "Payment Voucher {$payment->payment_number} - {$payment->payment_method}",
                ),
            ];

            $journalEntryDTO = new JournalEntryDTO(
                companyId: $payment->company_id,
                periodId: $period->id,
                documentType: 'PV',
                postingDate: $paymentDate,
                businessDate: $paymentDate,
                referenceType: 'payment',
                referenceId: $payment->id,
                postedBy: $approverUserId,
                branchId: $payment->branch_id,
                narration: "Supplier Payment Voucher {$payment->payment_number} approved by user {$approverUserId}",
                lines: $journalLines
            );

            $journal = $this->postingService->post($journalEntryDTO);

            // 6. Update payment status to posted
            DB::table('payments')->where('id', $payment->id)->update([
                'status' => 'posted',
                'approved_by' => $approverUserId,
                'approved_at' => now(),
                'gl_journal_entry_id' => $journal->id,
                'updated_at' => now(),
            ]);

            return Payment::findOrFail($payment->id);
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
