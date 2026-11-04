<?php

namespace App\Modules\Accounting\Subledger\Exceptions;

use App\Core\ValueObjects\Money;
use Exception;

class AllocationExceedsPaymentException extends Exception
{
    public function __construct(
        public readonly Money $paymentAmount,
        public readonly Money $totalAllocated,
        string $message = '',
    ) {
        $msg = $message ?: sprintf(
            'Total allocated amount %s exceeds available payment amount %s.',
            $totalAllocated->getFormattedAmount(),
            $paymentAmount->getFormattedAmount()
        );
        parent::__construct($msg);
    }
}
