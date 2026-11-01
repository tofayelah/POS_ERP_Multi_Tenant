<?php

namespace App\Modules\Tax\DTOs;

use Carbon\CarbonInterface;

readonly class ReturnInvoiceRequest
{
    /**
     * @param  array<int, ReturnLineItemDTO>  $lines
     * @param  array<string, string>  $accountMap
     */
    public function __construct(
        public string $salesInvoiceId,
        public string $postedBy,
        public array $lines,
        public ?string $reason = 'Customer Return',
        public CarbonInterface|string|null $returnDate = null,
        public array $accountMap = [],
    ) {}
}
