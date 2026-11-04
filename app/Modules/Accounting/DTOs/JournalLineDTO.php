<?php

namespace App\Modules\Accounting\DTOs;

use App\Core\ValueObjects\Money;

readonly class JournalLineDTO
{
    public function __construct(
        public string $accountId,
        public Money $debitAmount,
        public Money $creditAmount,
        public ?string $currency = 'BDT',
        public ?string $exchangeRate = '1.000000',
        public ?string $taxCodeId = null,
        public ?string $branchId = null,
        public ?string $partnerId = null,
        public ?string $lineDescription = null,
    ) {}
}
