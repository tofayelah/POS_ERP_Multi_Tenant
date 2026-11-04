<?php

namespace App\Modules\Accounting\Subledger\DTOs;

readonly class PartyAgingItemDTO
{
    public function __construct(
        public string $partyId,
        public string $partyName,
        public AgingBucketDTO $buckets,
    ) {}
}
