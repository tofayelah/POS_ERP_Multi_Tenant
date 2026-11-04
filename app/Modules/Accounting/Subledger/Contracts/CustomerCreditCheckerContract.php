<?php

namespace App\Modules\Accounting\Subledger\Contracts;

use App\Core\ValueObjects\Money;
use App\Modules\Accounting\Subledger\DTOs\CustomerCreditCheckResult;

interface CustomerCreditCheckerContract
{
    /**
     * Get the current outstanding due for a customer dynamically from open subledger items.
     */
    public function getCurrentDue(string $companyId, string $customerId): Money;

    /**
     * Check if a proposed credit sale amount is acceptable against customer's credit limit.
     * Throws CreditLimitExceededException if exceeded.
     */
    public function checkCreditLimit(string $companyId, string $customerId, Money $proposedCreditAmount): CustomerCreditCheckResult;
}
