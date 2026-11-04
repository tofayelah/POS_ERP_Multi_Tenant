<?php

namespace App\Modules\Pos\DTOs;

use Carbon\Carbon;

readonly class SyncSaleDTO
{
    /**
     * @param  array<int, SyncSaleItemDTO>  $items
     */
    public function __construct(
        public string $clientTxnId,
        public Carbon $deviceTime,
        public ?string $customerId = null,
        public string $paymentStatus = 'paid',
        public string $paymentMethod = 'cash',
        public string $pricingBasis = 'tax_inclusive',
        public ?string $sdcInvoiceNumber = null,
        public ?string $fiscalSignature = null,
        public array $items = [],
    ) {}
}
