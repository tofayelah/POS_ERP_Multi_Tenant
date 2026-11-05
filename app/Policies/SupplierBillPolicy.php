<?php

namespace App\Policies;

use App\Models\SupplierBill;
use App\Models\User;
use App\Policies\Concerns\EnforcesLocationScope;

class SupplierBillPolicy
{
    use EnforcesLocationScope;

    /**
     * Determine whether the user can view any supplier bills.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Financial Controller',
            'Accounts Payable Officer',
            'Accountant',
            'Auditor',
            'Procurement Manager',
        ]);
    }

    /**
     * Determine whether the user can view the specific supplier bill.
     */
    public function view(User $user, SupplierBill $bill): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $this->canAccessLocation($user, $bill->company_id, $bill->branch_id);
    }

    /**
     * Determine whether the user can create supplier bills.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Financial Controller',
            'Accounts Payable Officer',
            'Accountant',
        ]);
    }

    /**
     * Determine whether the user can update the supplier bill.
     * Approved, paid, or cancelled bills are immutable.
     */
    public function update(User $user, SupplierBill $bill): bool
    {
        if ($bill->status !== 'draft') {
            return false;
        }

        if (! $this->create($user)) {
            return false;
        }

        return $this->canAccessLocation($user, $bill->company_id, $bill->branch_id);
    }

    /**
     * Determine whether the user can delete the supplier bill.
     */
    public function delete(User $user, SupplierBill $bill): bool
    {
        return $bill->status === 'draft' && $this->update($user, $bill);
    }

    /**
     * Bulk deletion is explicitly disabled for statutory audit trails.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can approve a standard clean match supplier bill.
     */
    public function approve(User $user, SupplierBill $bill): bool
    {
        if ($bill->status !== 'draft') {
            return false;
        }

        if (! $user->hasAnyRole(['Tenant Owner', 'Tenant Admin', 'Financial Controller', 'Accounts Payable Officer', 'Accountant'])) {
            return false;
        }

        return $this->canAccessLocation($user, $bill->company_id, $bill->branch_id);
    }

    /**
     * Determine whether the user can perform a managerial override on three-way matching exceptions.
     * Segregation of Duties: Restricted strictly to Financial Controller, Tenant Admin, Tenant Owner.
     */
    public function override(User $user, SupplierBill $bill): bool
    {
        if ($bill->status !== 'draft') {
            return false;
        }

        if (! $user->hasAnyRole(['Tenant Owner', 'Tenant Admin', 'Financial Controller'])) {
            return false;
        }

        return $this->canAccessLocation($user, $bill->company_id, $bill->branch_id);
    }
}
