<?php

namespace App\Modules\Accounting\Subledger\Exceptions;

use App\Core\ValueObjects\Money;
use Exception;

class AllocationExceedsOpenItemException extends Exception
{
    public function __construct(
        public readonly string $openItemId,
        public readonly Money $remainingAmount,
        public readonly Money $attemptedAllocation,
        string $message = '',
    ) {
        $msg = $message ?: sprintf(
            'Allocation amount %s exceeds remaining open balance of %s on open item %s.',
            $attemptedAllocation->getFormattedAmount(),
            $remainingAmount->getFormattedAmount(),
            $openItemId
        );
        parent::__construct($msg);
    }
}
