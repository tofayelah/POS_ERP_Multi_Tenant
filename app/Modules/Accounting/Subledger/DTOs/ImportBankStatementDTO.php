<?php

namespace App\Modules\Accounting\Subledger\DTOs;

use Carbon\CarbonInterface;

readonly class ImportBankStatementDTO
{
    /**
     * @param  array<int, ImportStatementLineDTO>  $lines
     */
    public function __construct(
        public string $companyId,
        public string $chartOfAccountId,
        public CarbonInterface|string $statementDate,
        public string $openingBalance,
        public string $closingBalance,
        public array $lines = [],
    ) {}
}
