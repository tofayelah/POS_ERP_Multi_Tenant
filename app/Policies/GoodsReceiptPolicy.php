<?php

namespace App\Policies;

use App\Models\GoodsReceipt;
use App\Models\User;
use App\Policies\Concerns\EnforcesLocationScope;

class GoodsReceiptPolicy
{
    use EnforcesLocationScope;

    /**
     * Determine whether the user can view any goods receipts.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Warehouse Manager',
            'Procurement Manager',
            'Accountant',
            'Auditor',
        ]);
    }

    /**
     * Determine whether the user can view the specific goods receipt.
     */
    public function view(User $user, GoodsReceipt $grn): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $this->canAccessLocation($user, $grn->company_id, $grn->branch_id);
    }

    /**
     * Determine whether the user can create goods receipts.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Warehouse Manager',
        ]);
    }

    /**
     * Determine whether the user can update the goods receipt.
     * Confirmed/received or billed goods receipts are immutable.
     */
    public function update(User $user, GoodsReceipt $grn): bool
    {
        if ($grn->status !== 'draft') {
            return false;
        }

        if (! $user->hasAnyRole(['Tenant Owner', 'Tenant Admin', 'Warehouse Manager'])) {
            return false;
        }

        return $this->canAccessLocation($user, $grn->company_id, $grn->branch_id);
    }

    /**
     * Determine whether the user can delete the draft goods receipt.
     */
    public function delete(User $user, GoodsReceipt $grn): bool
    {
        return $grn->status === 'draft' && $this->update($user, $grn);
    }

    /**
     * Bulk deletion is explicitly disabled for document auditability.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can confirm a goods receipt.
     * Confirmation triggers physical stock movement and AVCO updates.
     */
    public function confirm(User $user, GoodsReceipt $grn): bool
    {
        if ($grn->status !== 'draft') {
            return false;
        }

        return $this->update($user, $grn);
    }
}
