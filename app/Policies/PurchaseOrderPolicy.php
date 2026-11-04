<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Policies\Concerns\EnforcesLocationScope;
use Brick\Math\BigDecimal;

class PurchaseOrderPolicy
{
    use EnforcesLocationScope;

    /**
     * Determine whether the user can view any purchase orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Procurement Manager',
            'Accountant',
            'Auditor',
        ]);
    }

    /**
     * Determine whether the user can view the specific purchase order.
     */
    public function view(User $user, PurchaseOrder $po): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $this->canAccessLocation($user, $po->company_id, $po->branch_id);
    }

    /**
     * Determine whether the user can create purchase orders.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Procurement Manager',
        ]);
    }

    /**
     * Determine whether the user can update the purchase order.
     * Confirmed, partially received, received, closed, or cancelled orders are immutable.
     */
    public function update(User $user, PurchaseOrder $po): bool
    {
        if ($po->status !== 'draft') {
            return false;
        }

        if (! $user->hasAnyRole(['Tenant Owner', 'Tenant Admin', 'Procurement Manager'])) {
            return false;
        }

        return $this->canAccessLocation($user, $po->company_id, $po->branch_id);
    }

    /**
     * Determine whether the user can delete the purchase order.
     */
    public function delete(User $user, PurchaseOrder $po): bool
    {
        return $po->status === 'draft' && $this->update($user, $po);
    }

    /**
     * Determine whether the user can bulk delete purchase orders.
     * Bulk deletion is explicitly disabled for document auditability.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can confirm a draft purchase order.
     */
    public function confirm(User $user, PurchaseOrder $po): bool
    {
        if ($po->status !== 'draft') {
            return false;
        }

        return $this->update($user, $po);
    }

    /**
     * Determine whether the user can cancel the purchase order.
     * Orders with existing goods receipts cannot be cancelled.
     */
    public function cancel(User $user, PurchaseOrder $po): bool
    {
        if (in_array($po->status, ['received', 'closed', 'cancelled'], true)) {
            return false;
        }

        // Cannot cancel if any quantity has been received
        foreach ($po->lines as $line) {
            if (BigDecimal::of($line->received_quantity)->isPositive()) {
                return false;
            }
        }

        if (! $user->hasAnyRole(['Tenant Owner', 'Tenant Admin', 'Procurement Manager'])) {
            return false;
        }

        return $this->canAccessLocation($user, $po->company_id, $po->branch_id);
    }
}
