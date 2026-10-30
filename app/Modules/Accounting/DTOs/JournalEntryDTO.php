<?php

namespace App\Modules\Accounting\DTOs;

use Carbon\CarbonInterface;

readonly class JournalEntryDTO
{
    /**
     * @param  array<int, JournalLineDTO>  $lines
     */
    public function __construct(
        public string $companyId,
        public string $periodId,
        public string $documentType,
        public CarbonInterface|string $postingDate,
        public CarbonInterface|string $businessDate,
        public string $referenceType,
        public ?string $referenceId,
        public string $postedBy,
        public ?string $branchId = null,
        public ?string $narration = null,
        public bool $isReversal = false,
        public ?string $reversedEntryId = null,
        public array $lines = [],
    ) {}
}
