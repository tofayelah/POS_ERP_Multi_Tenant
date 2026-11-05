<?php

namespace App\Policies;

use App\Models\SupplierQuotation;
use App\Models\User;
use App\Policies\Concerns\EnforcesLocationScope;

class SupplierQuotationPolicy
{
    use EnforcesLocationScope;

    /**
     * Determine whether the user can view any supplier quotations.
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
     * Determine whether the user can view the specific supplier quotation.
     */
    public function view(User $user, SupplierQuotation $quote): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $this->canAccessLocation($user, $quote->company_id, $quote->branch_id);
    }

    /**
     * Determine whether the user can create supplier quotations.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Procurement Manager',
            'Procurement Officer',
        ]);
    }

    /**
     * Determine whether the user can update the supplier quotation.
     * Converted or awarded quotations cannot be modified.
     */
    public function update(User $user, SupplierQuotation $quote): bool
    {
        if (! in_array($quote->status, ['draft', 'sent', 'evaluated'], true)) {
            return false;
        }

        if (! $this->create($user)) {
            return false;
        }

        return $this->canAccessLocation($user, $quote->company_id, $quote->branch_id);
    }

    /**
     * Determine whether the user can delete the supplier quotation.
     */
    public function delete(User $user, SupplierQuotation $quote): bool
    {
        return $quote->status === 'draft' && $this->update($user, $quote);
    }

    /**
     * Bulk deletion is explicitly disabled for statutory audit trails.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can award a supplier quotation.
     */
    public function award(User $user, SupplierQuotation $quote): bool
    {
        if (! in_array($quote->status, ['draft', 'sent', 'evaluated'], true)) {
            return false;
        }

        if (! $user->hasAnyRole(['Tenant Owner', 'Tenant Admin', 'Procurement Manager'])) {
            return false;
        }

        return $this->canAccessLocation($user, $quote->company_id, $quote->branch_id);
    }

    /**
     * Determine whether the user can convert an awarded supplier quotation to a Purchase Order.
     */
    public function convert(User $user, SupplierQuotation $quote): bool
    {
        if (! in_array($quote->status, ['awarded', 'evaluated'], true)) {
            return false;
        }

        if (! $user->hasAnyRole(['Tenant Owner', 'Tenant Admin', 'Procurement Manager'])) {
            return false;
        }

        return $this->canAccessLocation($user, $quote->company_id, $quote->branch_id);
    }
}
