<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Core\ValueObjects\Money;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Accounting\Contracts\AccountingPostingServiceContract;
use App\Modules\Accounting\Database\Seeders\BangladeshRetailCoaTemplate;
use App\Modules\Accounting\DTOs\JournalEntryDTO;
use App\Modules\Accounting\DTOs\JournalLineDTO;
use App\Modules\Accounting\Exceptions\UnbalancedJournalException;
use Brick\Math\BigDecimal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LedgerPropertyTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected FiscalYear $fiscalYear;

    protected AccountingPeriod $period;

    protected User $user;

    /**
     * @var array<int, ChartOfAccount>
     */
    protected array $accounts = [];

    protected AccountingPostingServiceContract $postingService;

    protected int $seed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed = (int) (getenv('LEDGER_TEST_SEED') ?: 424242);
        mt_srand($this->seed);
        fwrite(STDOUT, sprintf("\n[LedgerPropertyTest] Using random seed: %d\n", $this->seed));

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ledger Property Test Tenant',
            'subdomain' => 'ledger-prop-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Ledger Property Retail Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->fiscalYear = FiscalYear::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'title' => 'FY 2026-2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
        ]);

        $this->period = AccountingPeriod::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'period_number' => 1,
            'period_name' => 'July 2026',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'open',
        ]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Auditor User',
            'email' => 'auditor-'.Str::random(6).'@example.com',
            'password' => 'password',
        ]);

        $template = new BangladeshRetailCoaTemplate;
        $this->accounts = array_values($template->seedForCompany($this->tenant->id, $this->company->id));

        $this->postingService = app(AccountingPostingServiceContract::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_500_random_balanced_journals_balance_and_trial_balance_totals_zero(): void
    {
        $postedEntries = [];

        // 1. Generate and post 500 random balanced journals
        for ($i = 1; $i <= 500; $i++) {
            $dto = $this->generateBalancedJournalDto($i);

            $entry = DB::transaction(function () use ($dto) {
                return $this->postingService->post($dto);
            });

            $this->assertTrue($entry->is_posted, "Entry #{$i} was not marked posted.");
            $postedEntries[] = $entry;
        }

        $this->assertCount(500, $postedEntries);

        // 2. Assert every entry individually balances
        $entryIds = array_map(fn (JournalEntry $e) => $e->id, $postedEntries);
        $linesGrouped = JournalLine::whereIn('journal_entry_id', $entryIds)
            ->get()
            ->groupBy('journal_entry_id');

        foreach ($postedEntries as $entry) {
            $lines = $linesGrouped->get($entry->id);
            $this->assertNotNull($lines, "Lines not found for entry {$entry->entry_number}");
            $this->assertGreaterThanOrEqual(2, $lines->count());
            $this->assertLessThanOrEqual(8, $lines->count());

            $totalDebit = BigDecimal::zero();
            $totalCredit = BigDecimal::zero();

            foreach ($lines as $line) {
                $totalDebit = $totalDebit->plus(BigDecimal::of((string) $line->base_debit_amount));
                $totalCredit = $totalCredit->plus(BigDecimal::of((string) $line->base_credit_amount));
            }

            $this->assertTrue(
                $totalDebit->isEqualTo($totalCredit),
                "Entry {$entry->entry_number} is unbalanced: Debits ({$totalDebit}) != Credits ({$totalCredit})"
            );
        }

        // 3. Assert trial balance totals zero across entire ledger
        $totals = DB::table('journal_lines')
            ->where('tenant_id', $this->tenant->id)
            ->where('company_id', $this->company->id)
            ->selectRaw('COALESCE(SUM(base_debit_amount), 0) as total_debit, COALESCE(SUM(base_credit_amount), 0) as total_credit')
            ->first();

        $this->assertNotNull($totals);

        $aggregateDebit = BigDecimal::of((string) $totals->total_debit);
        $aggregateCredit = BigDecimal::of((string) $totals->total_credit);

        $this->assertTrue(
            $aggregateDebit->isEqualTo($aggregateCredit),
            sprintf('Aggregate Trial Balance unbalanced: Total Debit (%s) != Total Credit (%s)', $aggregateDebit, $aggregateCredit)
        );

        $netDifference = $aggregateDebit->minus($aggregateCredit);
        $this->assertTrue(
            $netDifference->isZero(),
            "Net trial balance difference is not zero: {$netDifference}"
        );

        // 4. Assert sum of all individual account balances equals zero
        $accountBalances = DB::table('journal_lines')
            ->where('tenant_id', $this->tenant->id)
            ->where('company_id', $this->company->id)
            ->select('account_id')
            ->selectRaw('COALESCE(SUM(base_debit_amount), 0) - COALESCE(SUM(base_credit_amount), 0) as net_balance')
            ->groupBy('account_id')
            ->get();

        $sumOfAccounts = BigDecimal::zero();
        foreach ($accountBalances as $ab) {
            $sumOfAccounts = $sumOfAccounts->plus(BigDecimal::of((string) $ab->net_balance));
        }

        $this->assertTrue(
            $sumOfAccounts->isZero(),
            "Sum of individual account balances is not zero: {$sumOfAccounts}"
        );
    }

    public function test_200_unbalanced_journals_are_rejected_and_nothing_written(): void
    {
        $initialEntryCount = JournalEntry::where('company_id', $this->company->id)->count();
        $initialLineCount = JournalLine::where('company_id', $this->company->id)->count();

        for ($i = 1; $i <= 200; $i++) {
            $unbalancedDto = $this->generateUnbalancedJournalDto($i);

            $rejected = false;
            try {
                DB::transaction(function () use ($unbalancedDto) {
                    $this->postingService->post($unbalancedDto);
                });
            } catch (\Throwable $e) {
                $rejected = true;
                $this->assertTrue(
                    $e instanceof UnbalancedJournalException || $e instanceof QueryException,
                    sprintf('Expected rejection of unbalanced entry, got %s: %s', get_class($e), $e->getMessage())
                );
            }

            $this->assertTrue($rejected, "Unbalanced journal #{$i} was not rejected!");
        }

        // Assert absolutely nothing was written to the database across all 200 rejections
        $finalEntryCount = JournalEntry::where('company_id', $this->company->id)->count();
        $finalLineCount = JournalLine::where('company_id', $this->company->id)->count();

        $this->assertSame(
            $initialEntryCount,
            $finalEntryCount,
            "Unbalanced journals leaked entries into database (Initial: {$initialEntryCount}, Final: {$finalEntryCount})"
        );
        $this->assertSame(
            $initialLineCount,
            $finalLineCount,
            "Unbalanced journals leaked lines into database (Initial: {$initialLineCount}, Final: {$finalLineCount})"
        );
    }

    /**
     * Generate a random balanced journal DTO with 2 to 8 lines.
     */
    protected function generateBalancedJournalDto(int $index): JournalEntryDTO
    {
        $lineCount = mt_rand(2, 8);
        $debitCount = mt_rand(1, $lineCount - 1);
        $creditCount = $lineCount - $debitCount;

        // Random total amount between 10.00 and 50,000.00 (1,000 to 5,000,000 cents)
        $totalCents = mt_rand(1000, 5000000);

        $debitCents = $this->partitionCents($totalCents, $debitCount);
        $creditCents = $this->partitionCents($totalCents, $creditCount);

        // Pick distinct accounts for the lines
        $accountKeys = array_rand($this->accounts, $lineCount);
        if (! is_array($accountKeys)) {
            $accountKeys = [$accountKeys];
        }
        shuffle($accountKeys);

        $lines = [];
        $keyIdx = 0;

        foreach ($debitCents as $cents) {
            $account = $this->accounts[$accountKeys[$keyIdx++]];
            $amount = Money::of(sprintf('%.2f', $cents / 100));
            $lines[] = new JournalLineDTO(
                accountId: $account->id,
                debitAmount: $amount,
                creditAmount: Money::zero(),
                lineDescription: "Debit to {$account->account_name}",
            );
        }

        foreach ($creditCents as $cents) {
            $account = $this->accounts[$accountKeys[$keyIdx++]];
            $amount = Money::of(sprintf('%.2f', $cents / 100));
            $lines[] = new JournalLineDTO(
                accountId: $account->id,
                debitAmount: Money::zero(),
                creditAmount: $amount,
                lineDescription: "Credit to {$account->account_name}",
            );
        }

        return new JournalEntryDTO(
            companyId: $this->company->id,
            periodId: $this->period->id,
            documentType: 'JV',
            postingDate: '2026-07-15',
            businessDate: '2026-07-15',
            referenceType: 'manual',
            referenceId: null,
            postedBy: $this->user->id,
            narration: "Random balanced journal #{$index} (Seed: {$this->seed})",
            lines: $lines,
        );
    }

    /**
     * Generate a journal DTO unbalanced by a random 0.01 to 100.00.
     */
    protected function generateUnbalancedJournalDto(int $index): JournalEntryDTO
    {
        $balancedDto = $this->generateBalancedJournalDto($index);
        $lines = $balancedDto->lines;

        // Random imbalance between 0.01 and 100.00 (1 to 10000 cents)
        $imbalanceCents = mt_rand(1, 10000);
        $imbalanceAmount = Money::of(sprintf('%.2f', $imbalanceCents / 100));

        // Perturb one line
        $targetIdx = mt_rand(0, count($lines) - 1);
        $targetLine = $lines[$targetIdx];

        if ($targetLine->debitAmount->isGreaterThan(Money::zero())) {
            $newDebit = (mt_rand(0, 1) === 1 || $targetLine->debitAmount->isLessThan($imbalanceAmount))
                ? $targetLine->debitAmount->add($imbalanceAmount)
                : $targetLine->debitAmount->subtract($imbalanceAmount);

            if ($newDebit->isZero()) {
                $newDebit = $targetLine->debitAmount->add($imbalanceAmount);
            }

            $lines[$targetIdx] = new JournalLineDTO(
                accountId: $targetLine->accountId,
                debitAmount: $newDebit,
                creditAmount: Money::zero(),
                currency: $targetLine->currency,
                exchangeRate: $targetLine->exchangeRate,
                taxCodeId: $targetLine->taxCodeId,
                branchId: $targetLine->branchId,
                partnerId: $targetLine->partnerId,
                lineDescription: $targetLine->lineDescription.' (unbalanced)',
            );
        } else {
            $newCredit = (mt_rand(0, 1) === 1 || $targetLine->creditAmount->isLessThan($imbalanceAmount))
                ? $targetLine->creditAmount->add($imbalanceAmount)
                : $targetLine->creditAmount->subtract($imbalanceAmount);

            if ($newCredit->isZero()) {
                $newCredit = $targetLine->creditAmount->add($imbalanceAmount);
            }

            $lines[$targetIdx] = new JournalLineDTO(
                accountId: $targetLine->accountId,
                debitAmount: Money::zero(),
                creditAmount: $newCredit,
                currency: $targetLine->currency,
                exchangeRate: $targetLine->exchangeRate,
                taxCodeId: $targetLine->taxCodeId,
                branchId: $targetLine->branchId,
                partnerId: $targetLine->partnerId,
                lineDescription: $targetLine->lineDescription.' (unbalanced)',
            );
        }

        return new JournalEntryDTO(
            companyId: $balancedDto->companyId,
            periodId: $balancedDto->periodId,
            documentType: 'JV',
            postingDate: $balancedDto->postingDate,
            businessDate: $balancedDto->businessDate,
            referenceType: 'manual',
            referenceId: null,
            postedBy: $balancedDto->postedBy,
            narration: "Random unbalanced journal #{$index} (Seed: {$this->seed})",
            lines: $lines,
        );
    }

    /**
     * Partition $totalCents into $k strictly positive integers summing to $totalCents.
     *
     * @return array<int, int>
     */
    protected function partitionCents(int $totalCents, int $k): array
    {
        if ($k <= 1) {
            return [$totalCents];
        }

        if ($totalCents < $k) {
            $parts = array_fill(0, $k, 1);
            $parts[$k - 1] += ($totalCents - $k);

            return $parts;
        }

        $cuts = [];
        while (count($cuts) < $k - 1) {
            $cut = mt_rand(1, $totalCents - 1);
            $cuts[$cut] = true;
        }

        $points = array_keys($cuts);
        sort($points);
        $points = array_merge([0], $points, [$totalCents]);

        $parts = [];
        for ($i = 0; $i < $k; $i++) {
            $parts[] = $points[$i + 1] - $points[$i];
        }

        return $parts;
    }
}
