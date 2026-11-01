<?php

namespace App\Modules\Accounting\Subledger\Exceptions;

use App\Core\ValueObjects\Money;
use Exception;

class CreditLimitExceededException extends Exception
{
    public function __construct(
        public readonly Money $creditLimit,
        public readonly Money $currentDue,
        public readonly Money $proposedAmount,
        string $message = '',
    ) {
        $msg = $message ?: sprintf(
            'Credit limit exceeded: Customer credit limit is %s, current due is %s. Attempted credit sale of %s results in projected due of %s.',
            $creditLimit->getFormattedAmount(),
            $currentDue->getFormattedAmount(),
            $proposedAmount->getFormattedAmount(),
            $currentDue->add($proposedAmount)->getFormattedAmount()
        );
        parent::__construct($msg);
    }
}
