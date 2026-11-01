<?php

namespace App\Modules\Accounting\DTOs;

use App\Core\ValueObjects\Money;

readonly class BalanceSheetReportDTO
{
    /**
     * @param  array<int, array{code: string, name: string, amount: Money}>  $assetAccounts
     * @param  array<int, array{code: string, name: string, amount: Money}>  $liabilityAccounts
     * @param  array<int, array{code: string, name: string, amount: Money}>  $equityAccounts
     */
    public function __construct(
        public string $companyId,
        public ?string $periodId,
        public Money $totalAssets,
        public Money $totalLiabilities,
        public Money $totalEquity,
        public Money $currentPeriodNetIncome,
        public Money $totalLiabilitiesAndEquity,
        public bool $isBalanced,
        public array $assetAccounts = [],
        public array $liabilityAccounts = [],
        public array $equityAccounts = [],
    ) {}
}
