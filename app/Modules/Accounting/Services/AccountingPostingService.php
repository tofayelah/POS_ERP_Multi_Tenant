<?php

namespace App\Modules\Accounting\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Modules\Accounting\Contracts\AccountingPostingServiceContract;
use App\Modules\Accounting\Contracts\NumberSeriesContract;
use App\Modules\Accounting\DTOs\JournalEntryDTO;
use App\Modules\Accounting\Exceptions\PostingWithoutTransactionException;
use App\Modules\Accounting\Exceptions\UnbalancedJournalException;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountingPostingService implements AccountingPostingServiceContract
{
    public function __construct(
        protected NumberSeriesContract $numberSeries
    ) {}

    /**
     * Post a balanced journal entry synchronously within caller's active database transaction.
     *
     * @param JournalEntryDTO $entryDto
     * @return JournalEntry
     *
     * @throws PostingWithoutTransactionException if DB::transactionLevel() === 0
     * @throws UnbalancedJournalException if debits != credits or line count < 2
     */
    public function post(JournalEntryDTO $entryDto): JournalEntry
    {
        // Enforce Design §5.4 (MOD-06): Must post inside caller's active transaction
        if (DB::transactionLevel() === 0) {
            throw new PostingWithoutTransactionException(
                'Accounting posting must occur within caller active database transaction (Design §5.4).'
            );
        }

        // Validate line count
        if (count($entryDto->lines) < 2) {
            throw new UnbalancedJournalException(
                'Financial Integrity Violation: Journal entry must contain at least 2 lines.'
            );
        }

        // Validate debits = credits across lines in base currency
        $totalDebits = BigDecimal::zero();
        $totalCredits = BigDecimal::zero();

        foreach ($entryDto->lines as $line) {
            $exchangeRate = BigDecimal::of($line->exchangeRate ?? '1.000000');
            $baseDebit = $line->debitAmount->getBigDecimal()->multipliedBy($exchangeRate);
            $baseCredit = $line->creditAmount->getBigDecimal()->multipliedBy($exchangeRate);

            $totalDebits = $totalDebits->plus($baseDebit);
            $totalCredits = $totalCredits->plus($baseCredit);
        }

        if (! $totalDebits->isEqualTo($totalCredits)) {
            throw new UnbalancedJournalException(
                sprintf(
                    'Financial Integrity Violation: Journal Entry is unbalanced. Debits (%s) != Credits (%s)',
                    $totalDebits->toScale(4),
                    $totalCredits->toScale(4)
                )
            );
        }

        // Resolve fiscal year string for number sequence
        /** @var AccountingPeriod $period */
        $period = AccountingPeriod::with('fiscalYear')->findOrFail($entryDto->periodId);
        $fiscalYear = $period->fiscalYear;

        $startYear = Carbon::parse($fiscalYear->start_date)->format('Y');
        $endYear = Carbon::parse($fiscalYear->end_date)->format('Y');
        $fiscalYearStr = sprintf('%s-%s', $startYear, $endYear);

        // Draw sequential document number via pessimistic lock
        $entryNumber = $this->numberSeries->nextNumber(
            companyId: $entryDto->companyId,
            branchId: $entryDto->branchId,
            documentType: $entryDto->documentType,
            fiscalYear: $fiscalYearStr
        );

        $tenantId = TenantContext::getTenantId() ?? (string) Str::uuid();

        // 1. Create entry in draft state (is_posted = false) to pass insert guard
        /** @var JournalEntry $entry */
        $entry = JournalEntry::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'company_id' => $entryDto->companyId,
            'period_id' => $entryDto->periodId,
            'entry_number' => $entryNumber,
            'posting_date' => $entryDto->postingDate,
            'business_date' => $entryDto->businessDate,
            'reference_type' => $entryDto->referenceType,
            'reference_id' => $entryDto->referenceId,
            'narration' => $entryDto->narration,
            'is_posted' => false,
            'is_reversal' => $entryDto->isReversal,
            'reversed_entry_id' => $entryDto->reversedEntryId,
            'posted_by' => $entryDto->postedBy,
        ]);

        // 2. Insert lines
        foreach ($entryDto->lines as $lineDto) {
            $exchangeRate = BigDecimal::of($lineDto->exchangeRate ?? '1.000000');
            $baseDebit = $lineDto->debitAmount->getBigDecimal()->multipliedBy($exchangeRate)->toScale(4);
            $baseCredit = $lineDto->creditAmount->getBigDecimal()->multipliedBy($exchangeRate)->toScale(4);

            JournalLine::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'company_id' => $entryDto->companyId,
                'journal_entry_id' => $entry->id,
                'account_id' => $lineDto->accountId,
                'debit_amount' => (string) $lineDto->debitAmount->getBigDecimal()->toScale(4),
                'credit_amount' => (string) $lineDto->creditAmount->getBigDecimal()->toScale(4),
                'currency' => $lineDto->currency ?? 'BDT',
                'exchange_rate' => (string) $exchangeRate->toScale(6),
                'base_debit_amount' => (string) $baseDebit,
                'base_credit_amount' => (string) $baseCredit,
                'partner_id' => $lineDto->partnerId,
                'tax_code_id' => $lineDto->taxCodeId,
                'branch_id' => $lineDto->branchId ?? $entryDto->branchId,
                'line_description' => $lineDto->lineDescription,
            ]);
        }

        // 3. Post entry (draft -> posted), firing PostgreSQL trigger trg_journal_entry_immutability
        $entry->is_posted = true;
        $entry->save();

        /** @var JournalEntry $freshEntry */
        $freshEntry = $entry->fresh(['lines']);

        return $freshEntry;
    }
}
