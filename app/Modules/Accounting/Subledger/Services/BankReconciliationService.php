<?php

namespace App\Modules\Accounting\Subledger\Services;

use App\Core\Tenancy\TenantContext;
use App\Core\ValueObjects\Money;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Payment;
use App\Modules\Accounting\Subledger\Contracts\BankReconciliationContract;
use App\Modules\Accounting\Subledger\DTOs\BankReconciliationSummaryDTO;
use App\Modules\Accounting\Subledger\DTOs\ImportBankStatementDTO;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BankReconciliationService implements BankReconciliationContract
{
    /**
     * Import a bank/cash statement with detailed transaction lines.
     */
    public function importStatement(ImportBankStatementDTO $dto): BankStatement
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new InvalidArgumentException('Cannot import bank statement without active TenantContext.');
            }

            $statementDate = $dto->statementDate instanceof CarbonInterface
                ? $dto->statementDate->toDateString()
                : Carbon::parse($dto->statementDate)->toDateString();

            $statementId = (string) Str::uuid();

            DB::table('bank_statements')->insert([
                'id' => $statementId,
                'tenant_id' => $tenantId,
                'company_id' => $dto->companyId,
                'chart_of_account_id' => $dto->chartOfAccountId,
                'statement_date' => $statementDate,
                'opening_balance' => (string) Money::of($dto->openingBalance)->getBigDecimal(),
                'closing_balance' => (string) Money::of($dto->closingBalance)->getBigDecimal(),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($dto->lines as $lineDTO) {
                $lineDate = $lineDTO->lineDate instanceof CarbonInterface
                    ? $lineDTO->lineDate->toDateString()
                    : Carbon::parse($lineDTO->lineDate)->toDateString();

                DB::table('bank_statement_lines')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'company_id' => $dto->companyId,
                    'bank_statement_id' => $statementId,
                    'line_date' => $lineDate,
                    'description' => $lineDTO->description,
                    'reference_number' => $lineDTO->referenceNumber,
                    'deposit_amount' => (string) Money::of($lineDTO->depositAmount)->getBigDecimal(),
                    'withdrawal_amount' => (string) Money::of($lineDTO->withdrawalAmount)->getBigDecimal(),
                    'balance' => (string) Money::of($lineDTO->balance)->getBigDecimal(),
                    'is_reconciled' => false,
                    'matched_payment_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return BankStatement::with('lines')->findOrFail($statementId);
        });
    }

    /**
     * Automatically match unreconciled statement lines against posted payments.
     */
    public function autoMatchStatement(string $statementId): int
    {
        return DB::transaction(function () use ($statementId) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new InvalidArgumentException('Cannot match statement without active TenantContext.');
            }

            /** @var BankStatement $statement */
            $statement = BankStatement::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $statementId)
                ->lockForUpdate()
                ->firstOrFail();

            $unmatchedLines = BankStatementLine::query()
                ->where('tenant_id', $tenantId)
                ->where('bank_statement_id', $statementId)
                ->where('is_reconciled', false)
                ->get();

            $matchedCount = 0;

            foreach ($unmatchedLines as $line) {
                $isDeposit = Money::of((string) $line->deposit_amount)->isPositive();
                $isWithdrawal = Money::of((string) $line->withdrawal_amount)->isPositive();

                if (! $isDeposit && ! $isWithdrawal) {
                    continue;
                }

                $paymentType = $isDeposit ? 'inbound' : 'outbound';
                $lineAmount = $isDeposit ? (string) $line->deposit_amount : (string) $line->withdrawal_amount;

                // Match against posted payment for company with matching reference and amount
                $paymentQuery = Payment::query()
                    ->where('tenant_id', $tenantId)
                    ->where('company_id', $statement->company_id)
                    ->where('payment_type', $paymentType)
                    ->where('status', 'posted')
                    ->where('amount', $lineAmount);

                if ($line->reference_number) {
                    $paymentQuery->where('reference', $line->reference_number);
                }

                /** @var Payment|null $matchedPayment */
                $matchedPayment = $paymentQuery->lockForUpdate()->first();

                if ($matchedPayment) {
                    // Update line
                    DB::table('bank_statement_lines')->where('id', $line->id)->update([
                        'is_reconciled' => true,
                        'matched_payment_id' => $matchedPayment->id,
                        'updated_at' => now(),
                    ]);

                    // Update payment
                    DB::table('payments')->where('id', $matchedPayment->id)->update([
                        'status' => 'reconciled',
                        'updated_at' => now(),
                    ]);

                    $matchedCount++;
                }
            }

            $this->updateStatementStatus($statementId);

            return $matchedCount;
        });
    }

    /**
     * Manually match a statement line to a posted payment.
     */
    public function matchLine(string $statementLineId, string $paymentId): BankStatementLine
    {
        return DB::transaction(function () use ($statementLineId, $paymentId) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new InvalidArgumentException('Cannot match line without active TenantContext.');
            }

            /** @var BankStatementLine $line */
            $line = BankStatementLine::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $statementLineId)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Payment $payment */
            $payment = Payment::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $paymentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status === 'reconciled') {
                throw new InvalidArgumentException("Payment [{$paymentId}] is already reconciled.");
            }

            // Update line
            DB::table('bank_statement_lines')->where('id', $line->id)->update([
                'is_reconciled' => true,
                'matched_payment_id' => $payment->id,
                'updated_at' => now(),
            ]);

            // Update payment
            DB::table('payments')->where('id', $payment->id)->update([
                'status' => 'reconciled',
                'updated_at' => now(),
            ]);

            $this->updateStatementStatus($line->bank_statement_id);

            return BankStatementLine::findOrFail($line->id);
        });
    }

    /**
     * Unmatch a reconciled statement line.
     */
    public function unmatchLine(string $statementLineId): BankStatementLine
    {
        return DB::transaction(function () use ($statementLineId) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new InvalidArgumentException('Cannot unmatch line without active TenantContext.');
            }

            /** @var BankStatementLine $line */
            $line = BankStatementLine::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $statementLineId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($line->matched_payment_id) {
                DB::table('payments')->where('id', $line->matched_payment_id)->update([
                    'status' => 'posted',
                    'updated_at' => now(),
                ]);
            }

            DB::table('bank_statement_lines')->where('id', $line->id)->update([
                'is_reconciled' => false,
                'matched_payment_id' => null,
                'updated_at' => now(),
            ]);

            $this->updateStatementStatus($line->bank_statement_id);

            return BankStatementLine::findOrFail($line->id);
        });
    }

    /**
     * Generate bank reconciliation summary comparing statement vs GL book balance.
     */
    public function getReconciliationSummary(string $statementId): BankReconciliationSummaryDTO
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new InvalidArgumentException('Cannot get summary without active TenantContext.');
        }

        /** @var BankStatement $statement */
        $statement = BankStatement::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $statementId)
            ->with('lines')
            ->firstOrFail();

        $statementClosing = Money::of((string) $statement->closing_balance);

        $clearedDeposits = Money::zero('BDT');
        $clearedWithdrawals = Money::zero('BDT');
        $unmatchedLinesCount = 0;
        $unmatchedLinesTotal = Money::zero('BDT');

        foreach ($statement->lines as $line) {
            if ($line->is_reconciled) {
                $clearedDeposits = $clearedDeposits->add(Money::of((string) $line->deposit_amount));
                $clearedWithdrawals = $clearedWithdrawals->add(Money::of((string) $line->withdrawal_amount));
            } else {
                $unmatchedLinesCount++;
                $dep = Money::of((string) $line->deposit_amount);
                $with = Money::of((string) $line->withdrawal_amount);
                if ($dep->isPositive()) {
                    $unmatchedLinesTotal = $unmatchedLinesTotal->add($dep);
                }
                if ($with->isPositive()) {
                    $unmatchedLinesTotal = $unmatchedLinesTotal->add($with);
                }
            }
        }

        // GL Book Balance for the bank account
        $glDebit = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.tenant_id', $tenantId)
            ->where('journal_entries.company_id', $statement->company_id)
            ->where('journal_entries.is_posted', true)
            ->where('journal_lines.account_id', $statement->chart_of_account_id)
            ->sum('journal_lines.debit_amount');

        $glCredit = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.tenant_id', $tenantId)
            ->where('journal_entries.company_id', $statement->company_id)
            ->where('journal_entries.is_posted', true)
            ->where('journal_lines.account_id', $statement->chart_of_account_id)
            ->sum('journal_lines.credit_amount');

        $glBookBalance = Money::of((string) $glDebit)->subtract(Money::of((string) $glCredit));

        // Unmatched posted payments
        $unmatchedPayments = Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $statement->company_id)
            ->where('status', 'posted')
            ->get();

        $unmatchedPaymentsCount = $unmatchedPayments->count();
        $unmatchedPaymentsTotal = Money::zero('BDT');
        foreach ($unmatchedPayments as $p) {
            $unmatchedPaymentsTotal = $unmatchedPaymentsTotal->add(Money::of((string) $p->amount));
        }

        $isFullyReconciled = ($unmatchedLinesCount === 0 && $statement->status === 'reconciled');

        return new BankReconciliationSummaryDTO(
            statementId: $statementId,
            statementClosingBalance: $statementClosing,
            glBookBalance: $glBookBalance,
            clearedDepositsTotal: $clearedDeposits,
            clearedWithdrawalsTotal: $clearedWithdrawals,
            unmatchedStatementLinesCount: $unmatchedLinesCount,
            unmatchedStatementLinesTotal: $unmatchedLinesTotal,
            unmatchedPaymentsCount: $unmatchedPaymentsCount,
            unmatchedPaymentsTotal: $unmatchedPaymentsTotal,
            isFullyReconciled: $isFullyReconciled
        );
    }

    protected function updateStatementStatus(string $statementId): void
    {
        $unreconciledCount = DB::table('bank_statement_lines')
            ->where('bank_statement_id', $statementId)
            ->where('is_reconciled', false)
            ->count();

        $status = $unreconciledCount === 0 ? 'reconciled' : 'pending';

        DB::table('bank_statements')->where('id', $statementId)->update([
            'status' => $status,
            'updated_at' => now(),
        ]);
    }
}
