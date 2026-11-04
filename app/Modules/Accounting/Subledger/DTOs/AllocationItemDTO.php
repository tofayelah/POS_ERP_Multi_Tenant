<?php

namespace App\Modules\Accounting\Subledger\DTOs;

use App\Core\ValueObjects\Money;

readonly class AllocationItemDTO
{
    public function __construct(
        public string $openItemId,
        public Money|string $amount,
        public ?string $notes = null,
    ) {}
}
