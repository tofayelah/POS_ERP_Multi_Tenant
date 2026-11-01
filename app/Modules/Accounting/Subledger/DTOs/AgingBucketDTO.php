<?php

namespace App\Modules\Accounting\Subledger\DTOs;

use App\Core\ValueObjects\Money;

readonly class AgingBucketDTO
{
    public function __construct(
        public Money $days0To30,
        public Money $days31To60,
        public Money $days61To90,
        public Money $days91To120,
        public Money $daysOver120,
        public Money $total,
    ) {}
}
