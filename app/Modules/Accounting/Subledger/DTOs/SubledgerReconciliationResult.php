<?php

namespace App\Modules\Accounting\Subledger\DTOs;

use App\Core\ValueObjects\Money;

readonly class SubledgerReconciliationResult
{
    public function __construct(
        public string $controlAccountCode,
        public string $partyType,
        public Money $subledgerTotal,
        public Money $glControlTotal,
        public Money $variance,
        public bool $isReconciled,
    ) {}
}
