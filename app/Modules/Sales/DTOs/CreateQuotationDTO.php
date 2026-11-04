<?php

namespace App\Modules\Sales\DTOs;

readonly class CreateQuotationDTO
{
    /**
     * @param  array<int, CreateQuotationLineDTO>  $lines
     */
    public function __construct(
        public string $companyId,
        public string $branchId,
        public string $customerId,
        public string $quotationDate,
        public string $validUntil,
        public string $createdBy,
        public array $lines,
    ) {}
}
