<?php

namespace App\Modules\Accounting\Subledger\Services;

use App\Core\Tenancy\TenantContext;
use App\Core\ValueObjects\Money;
use App\Models\Customer;
use App\Modules\Accounting\Subledger\Contracts\CustomerCreditCheckerContract;
use App\Modules\Accounting\Subledger\DTOs\CustomerCreditCheckResult;
use App\Modules\Accounting\Subledger\Exceptions\CreditLimitExceededException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CustomerCreditChecker implements CustomerCreditCheckerContract
{
    /**
     * Get the current outstanding due for a customer dynamically from open subledger items.
     * ACC-06 & NEW-16: Customer balances are never stored in customer columns; dynamically projected from subledger open items.
     */
    public function getCurrentDue(string $companyId, string $customerId): Money
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new InvalidArgumentException('Cannot query customer due without active TenantContext.');
        }

        $debitDue = DB::table('subledger_open_items')
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('party_type', 'customer')
            ->where('party_id', $customerId)
            ->where('direction', 'debit')
            ->whereIn('status', ['open', 'partially_allocated'])
            ->sum('remaining_amount');

        $creditDue = DB::table('subledger_open_items')
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('party_type', 'customer')
            ->where('party_id', $customerId)
            ->where('direction', 'credit')
            ->whereIn('status', ['open', 'partially_allocated'])
            ->sum('remaining_amount');

        $netDue = BigDecimal::of((string) ($debitDue ?: '0.0000'))
            ->minus(BigDecimal::of((string) ($creditDue ?: '0.0000')));

        if ($netDue->isNegative()) {
            $netDue = BigDecimal::zero();
        }

        return Money::of($netDue->toScale(2, RoundingMode::HALF_UP)->jsonSerialize(), 'BDT');
    }

    /**
     * Check if a proposed credit sale amount is acceptable against customer's credit limit.
     * Throws CreditLimitExceededException if exceeded.
     */
    public function checkCreditLimit(string $companyId, string $customerId, Money $proposedCreditAmount): CustomerCreditCheckResult
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new InvalidArgumentException('Cannot check credit limit without active TenantContext.');
        }

        /** @var Customer|null $customer */
        $customer = Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('id', $customerId)
            ->first();

        if (! $customer) {
            throw new InvalidArgumentException("Customer [{$customerId}] not found.");
        }

        $creditLimit = Money::of((string) $customer->credit_limit, 'BDT');
        $currentDue = $this->getCurrentDue($companyId, $customerId);
        $projectedDue = $currentDue->add($proposedCreditAmount);

        if ($proposedCreditAmount->getBigDecimal()->isPositive() && $projectedDue->greaterThan($creditLimit)) {
            throw new CreditLimitExceededException($creditLimit, $currentDue, $proposedCreditAmount);
        }

        return new CustomerCreditCheckResult(
            isAllowed: true,
            creditLimit: $creditLimit,
            currentDue: $currentDue,
            proposedAmount: $proposedCreditAmount,
            projectedDue: $projectedDue
        );
    }
}
