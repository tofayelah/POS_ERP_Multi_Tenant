<?php

namespace App\Modules\Tax\DTOs;

use App\Core\ValueObjects\Money;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\PosTerminal;
use Carbon\CarbonInterface;

readonly class IssueInvoiceRequest
{
    /**
     * @param  array<int, InvoiceLineItemDTO>  $lines
     * @param  array<string, string>  $accountMap
     */
    public function __construct(
        public Company|string $company,
        public Branch|string $branch,
        public string $postedBy,
        public array $lines,
        public Customer|string|null $customer = null,
        public PosTerminal|string|null $terminal = null,
        public ?string $clientTxnId = null,
        public CarbonInterface|string|null $invoiceDate = null,
        public CarbonInterface|string|null $businessDate = null,
        public string $pricingBasis = 'tax_inclusive',
        public Money|string $invoiceDiscount = '0',
        public array $accountMap = [],
        public ?string $narration = null,
        public bool $isSyncedFromOffline = false,
        public ?string $paymentStatus = 'paid',
        public CarbonInterface|string|null $dueDate = null,
        public ?CarbonInterface $deviceTime = null,
        public ?CarbonInterface $serverReceivedTime = null,
        public ?int $clockSkewSeconds = null,
        public bool $isClockSkewFlagged = false,
    ) {}
}
