<?php

namespace App\Policies;

use App\Models\LandedCostVoucher;
use App\Models\User;
use App\Policies\Concerns\EnforcesLocationScope;

class LandedCostVoucherPolicy
{
    use EnforcesLocationScope;

    /**
     * Determine whether the user can view any landed cost vouchers.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Procurement Manager',
            'Procurement Officer',
            'Accountant',
            'Auditor',
        ]);
    }

    /**
     * Determine whether the user can view the specific landed cost voucher.
     */
    public function view(User $user, LandedCostVoucher $voucher): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $this->canAccessLocation($user, $voucher->company_id, $voucher->branch_id);
    }

    /**
     * Determine whether the user can create landed cost vouchers.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Procurement Manager',
            'Procurement Officer',
            'Accountant',
        ]);
    }

    /**
     * Determine whether the user can update the landed cost voucher.
     * Posted vouchers are immutable.
     */
    public function update(User $user, LandedCostVoucher $voucher): bool
    {
        if ($voucher->status !== 'draft') {
            return false;
        }

        if (! $this->create($user)) {
            return false;
        }

        return $this->canAccessLocation($user, $voucher->company_id, $voucher->branch_id);
    }

    /**
     * Determine whether the user can delete the landed cost voucher.
     */
    public function delete(User $user, LandedCostVoucher $voucher): bool
    {
        return $voucher->status === 'draft' && $this->update($user, $voucher);
    }

    /**
     * Bulk deletion is explicitly disabled for statutory audit trails.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can post the landed cost voucher.
     */
    public function post(User $user, LandedCostVoucher $voucher): bool
    {
        if ($voucher->status !== 'draft') {
            return false;
        }

        if (! $user->hasAnyRole(['Tenant Owner', 'Tenant Admin', 'Procurement Manager', 'Accountant'])) {
            return false;
        }

        return $this->canAccessLocation($user, $voucher->company_id, $voucher->branch_id);
    }
}
