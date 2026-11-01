<?php

namespace App\Modules\Accounting\Subledger\DTOs;

readonly class AgingReportDTO
{
    /**
     * @param  array<int, PartyAgingItemDTO>  $details
     */
    public function __construct(
        public string $companyId,
        public string $partyType,
        public string $asOfDate,
        public AgingBucketDTO $summary,
        public array $details = [],
    ) {}
}
