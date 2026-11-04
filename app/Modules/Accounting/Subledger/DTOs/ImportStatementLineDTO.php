<?php

namespace App\Modules\Accounting\Subledger\DTOs;

use Carbon\CarbonInterface;

readonly class ImportStatementLineDTO
{
    public function __construct(
        public CarbonInterface|string $lineDate,
        public string $description,
        public ?string $referenceNumber,
        public string $depositAmount,
        public string $withdrawalAmount,
        public string $balance,
    ) {}
}
