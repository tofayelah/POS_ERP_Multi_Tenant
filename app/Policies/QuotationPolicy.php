<?php

namespace App\Policies;

use App\Models\Quotation;
use App\Models\User;
use App\Policies\Concerns\EnforcesLocationScope;

class QuotationPolicy
{
    use EnforcesLocationScope;

    /**
     * Determine whether the user can view any quotations.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Sales Manager',
            'Sales Representative',
            'Branch Manager',
            'Accountant',
            'Auditor',
        ]);
    }

    /**
     * Determine whether the user can view the specific quotation.
     */
    public function view(User $user, Quotation $quotation): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $this->canAccessLocation($user, $quotation->company_id, $quotation->branch_id);
    }

    /**
     * Determine whether the user can create quotations.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Sales Manager',
            'Sales Representative',
        ]);
    }

    /**
     * Determine whether the user can update the quotation.
     * Converted or cancelled quotations are immutable.
     */
    public function update(User $user, Quotation $quotation): bool
    {
        if ($quotation->status !== 'draft') {
            return false;
        }

        if (! $user->hasAnyRole(['Tenant Owner', 'Tenant Admin', 'Sales Manager', 'Sales Representative'])) {
            return false;
        }

        return $this->canAccessLocation($user, $quotation->company_id, $quotation->branch_id);
    }

    /**
     * Determine whether the user can delete the quotation.
     */
    public function delete(User $user, Quotation $quotation): bool
    {
        return $quotation->status === 'draft' && $this->update($user, $quotation);
    }

    /**
     * Bulk deletion is explicitly disabled.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can convert a quotation into a Sales Order.
     * Expired quotations cannot be converted without renewal.
     */
    public function convert(User $user, Quotation $quotation): bool
    {
        if (! in_array($quotation->status, ['draft', 'sent'], true)) {
            return false;
        }

        if ($quotation->valid_until->isPast()) {
            return false;
        }

        if (! $user->hasAnyRole(['Tenant Owner', 'Tenant Admin', 'Sales Manager'])) {
            return false;
        }

        return $this->canAccessLocation($user, $quotation->company_id, $quotation->branch_id);
    }

    /**
     * Determine whether the user can cancel the quotation.
     */
    public function cancel(User $user, Quotation $quotation): bool
    {
        if ($quotation->status === 'converted') {
            return false;
        }

        if (! $user->hasAnyRole(['Tenant Owner', 'Tenant Admin', 'Sales Manager'])) {
            return false;
        }

        return $this->canAccessLocation($user, $quotation->company_id, $quotation->branch_id);
    }
}
