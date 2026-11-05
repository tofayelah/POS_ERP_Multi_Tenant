<?php

namespace App\Modules\Procurement\DTOs;

readonly class CreateSupplierQuotationDTO
{
    /**
     * @param  array<int, CreateSupplierQuotationLineDTO>  $lines
     */
    public function __construct(
        public string $companyId,
        public string $branchId,
        public string $supplierId,
        public string $rfqNumber,
        public ?string $supplierQuoteReference,
        public string $quoteDate,
        public string $validUntil,
        public string $createdBy,
        public string $currency = 'BDT',
        public string $exchangeRate = '1.000000',
        public array $lines = [],
    ) {}
}
