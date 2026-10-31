<?php

namespace App\Modules\Inventory\Services;

use App\Core\Tenancy\TenantContext;
use App\Core\ValueObjects\Money;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Modules\Accounting\Contracts\AccountingPostingServiceContract;
use App\Modules\Accounting\DTOs\JournalEntryDTO;
use App\Modules\Accounting\DTOs\JournalLineDTO;
use App\Modules\Inventory\Contracts\InventoryGlReconciliationContract;
use App\Modules\Inventory\DTOs\InventoryReconciliationDTO;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class InventoryGlReconciliationService implements InventoryGlReconciliationContract
{
    public function __construct(
        protected AccountingPostingServiceContract $postingService
    ) {}

    /**
     * Compute real-time stock valuation and reconcile against GL Account 1040 (Merchandise Inventory).
     */
    public function getReconciliationReport(string $companyId): InventoryReconciliationDTO
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant context must be active to generate inventory reconciliation report.');
        }

        // 1. Calculate physical stock valuation from stock_balances
        $balances = StockBalance::where('company_id', $companyId)->get();
        $totalValuation = BigDecimal::zero();

        foreach ($balances as $balance) {
            $onHand = BigDecimal::of($balance->quantity_on_hand ?? '0.0000');
            if ($onHand->isPositive()) {
                $wac = BigDecimal::of($balance->weighted_avg_cost ?? '0.0000');
                $totalValuation = $totalValuation->plus($onHand->multipliedBy($wac));
            }
        }

        $stockValuationStr = (string) $totalValuation->toScale(2, RoundingMode::HalfUp);

        // 2. Calculate net posted GL balance for Account 1040 (Merchandise Inventory)
        $invAccount = ChartOfAccount::where('company_id', $companyId)
            ->where('account_code', '1040')
            ->first();

        $glBalanceStr = '0.00';
        if ($invAccount) {
            $lines = JournalLine::where('company_id', $companyId)
                ->where('account_id', $invAccount->id)
                ->whereHas('entry', fn ($q) => $q->where('is_posted', true))
                ->get();

            $glNet = BigDecimal::zero();
            foreach ($lines as $line) {
                $glNet = $glNet->plus(BigDecimal::of($line->base_debit_amount))
                    ->minus(BigDecimal::of($line->base_credit_amount));
            }

            $glBalanceStr = (string) $glNet->toScale(2, RoundingMode::HalfUp);
        }

        // 3. Compute reconciliation variance
        $variance = BigDecimal::of($stockValuationStr)->minus(BigDecimal::of($glBalanceStr));
        $varianceStr = (string) $variance->toScale(2, RoundingMode::HalfUp);
        $isReconciled = $variance->isZero();

        return new InventoryReconciliationDTO(
            companyId: $companyId,
            stockValuation: $stockValuationStr,
            glBalance: $glBalanceStr,
            variance: $varianceStr,
            isReconciled: $isReconciled,
            asOf: Carbon::now()->toIso8601String()
        );
    }

    /**
     * Synchronously post balanced double-entry GL journal entry for an inventory movement.
     */
    public function postInventoryGlJournal(StockMovement $movement, ?string $userId = null): ?JournalEntry
    {
        // Enforce Non-Negotiable Rule 7: GL posting in caller's active transaction
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Inventory GL posting must occur within caller active database transaction.');
        }

        $companyId = $movement->company_id;

        // Discover required accounts
        $accounts = ChartOfAccount::where('company_id', $companyId)
            ->whereIn('account_code', ['1040', '2010', '5010', '5030'])
            ->get()
            ->keyBy('account_code');

        if (! isset($accounts['1040'])) {
            return null;
        }

        // Discover active open accounting period
        $movementDate = Carbon::parse($movement->movement_date);
        $period = AccountingPeriod::where('company_id', $companyId)
            ->where('status', 'open')
            ->where('start_date', '<=', $movementDate->toDateString())
            ->where('end_date', '>=', $movementDate->toDateString())
            ->first();

        if (! $period) {
            $period = AccountingPeriod::where('company_id', $companyId)
                ->where('status', 'open')
                ->first();
        }

        if (! $period) {
            return null;
        }

        $totalCost = BigDecimal::of($movement->total_cost)->abs()->toScale(2, RoundingMode::HalfUp);
        if ($totalCost->isZero()) {
            return null;
        }

        $moneyAmount = Money::of((string) $totalCost);
        $firstUser = User::first();
        $postedUserId = $userId ?? ($firstUser ? $firstUser->id : (string) Str::uuid());

        $lines = [];
        $narration = 'Inventory stock movement GL recognition';

        switch ($movement->movement_type) {
            case 'PURCHASE_RECEIPT':
                $account2010 = $accounts['2010'] ?? null;
                if (! $account2010) {
                    return null;
                }
                $lines = [
                    new JournalLineDTO(
                        accountId: $accounts['1040']->id,
                        debitAmount: $moneyAmount,
                        creditAmount: Money::zero(),
                        lineDescription: 'Merchandise Inventory Asset Inbound'
                    ),
                    new JournalLineDTO(
                        accountId: $account2010->id,
                        debitAmount: Money::zero(),
                        creditAmount: $moneyAmount,
                        lineDescription: 'Accounts Payable / GRN Clearing'
                    ),
                ];
                $narration = 'Goods Receipt inventory valuation';
                break;

            case 'SALE':
            case 'OFFLINE_OVERSELL':
                $account5010 = $accounts['5010'] ?? null;
                if (! $account5010) {
                    return null;
                }
                $lines = [
                    new JournalLineDTO(
                        accountId: $account5010->id,
                        debitAmount: $moneyAmount,
                        creditAmount: Money::zero(),
                        lineDescription: 'Cost of Goods Sold'
                    ),
                    new JournalLineDTO(
                        accountId: $accounts['1040']->id,
                        debitAmount: Money::zero(),
                        creditAmount: $moneyAmount,
                        lineDescription: 'Merchandise Inventory Reduction'
                    ),
                ];
                $narration = 'COGS recognized on sales dispatch';
                break;

            case 'STOCK_COUNT_ADJUSTMENT':
                $account5030 = $accounts['5030'] ?? null;
                if (! $account5030) {
                    return null;
                }
                $qty = BigDecimal::of($movement->quantity);
                if ($qty->isPositive()) {
                    // Gain: Debit 1040, Credit 5030
                    $lines = [
                        new JournalLineDTO(
                            accountId: $accounts['1040']->id,
                            debitAmount: $moneyAmount,
                            creditAmount: Money::zero(),
                            lineDescription: 'Inventory Count Gain'
                        ),
                        new JournalLineDTO(
                            accountId: $account5030->id,
                            debitAmount: Money::zero(),
                            creditAmount: $moneyAmount,
                            lineDescription: 'Inventory Adjustment'
                        ),
                    ];
                } else {
                    // Loss: Debit 5030, Credit 1040
                    $lines = [
                        new JournalLineDTO(
                            accountId: $account5030->id,
                            debitAmount: $moneyAmount,
                            creditAmount: Money::zero(),
                            lineDescription: 'Inventory Shrinkage Write-Down'
                        ),
                        new JournalLineDTO(
                            accountId: $accounts['1040']->id,
                            debitAmount: Money::zero(),
                            creditAmount: $moneyAmount,
                            lineDescription: 'Inventory Asset Reduction'
                        ),
                    ];
                }
                $narration = 'Stock count physical discrepancy adjustment';
                break;

            case 'COST_ADJUSTMENT':
                $account2010 = $accounts['2010'] ?? null;
                if (! $account2010) {
                    return null;
                }
                $lines = [
                    new JournalLineDTO(
                        accountId: $accounts['1040']->id,
                        debitAmount: $moneyAmount,
                        creditAmount: Money::zero(),
                        lineDescription: 'Landed Cost Inbound Valuation'
                    ),
                    new JournalLineDTO(
                        accountId: $account2010->id,
                        debitAmount: Money::zero(),
                        creditAmount: $moneyAmount,
                        lineDescription: 'Landed Cost Accrual'
                    ),
                ];
                $narration = 'Landed cost allocation to inventory asset';
                break;

            default:
                return null;
        }

        $dto = new JournalEntryDTO(
            companyId: $companyId,
            periodId: $period->id,
            documentType: 'JV',
            postingDate: $movementDate->toDateString(),
            businessDate: $movementDate->toDateString(),
            referenceType: $movement->reference_type ?? 'stock_movement',
            referenceId: $movement->reference_id ?? $movement->id,
            postedBy: $postedUserId,
            narration: $narration,
            lines: $lines
        );

        return $this->postingService->post($dto);
    }
}
