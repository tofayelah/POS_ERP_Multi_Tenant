<?php

namespace App\Core\Security;

use App\Models\PlatformUser;
use App\Models\Tenant;
use App\Models\TenantSupportAccess;
use App\Models\User;

class SoD
{
    /**
     * SoD-01 (Purchase & Pay Conflict):
     * A user who creates or approves a Purchase Order cannot post the corresponding Supplier Payment.
     */
    public static function canPostPayment(User $user, object $purchaseOrder): bool
    {
        $creatorId = $purchaseOrder->created_by_user_id ?? $purchaseOrder->created_by ?? null;
        $approverId = $purchaseOrder->approved_by_user_id ?? $purchaseOrder->approved_by ?? null;

        if ($user->id === $creatorId || $user->id === $approverId) {
            return false;
        }

        return true;
    }

    /**
     * SoD-02 (Supplier Creation & Payment):
     * A user who creates or edits a Supplier record cannot authorize disbursements to that supplier.
     */
    public static function canAuthorizeDisbursement(User $user, object $supplier): bool
    {
        $creatorId = $supplier->created_by_user_id ?? $supplier->created_by ?? null;
        $editorId = $supplier->updated_by_user_id ?? $supplier->updated_by ?? null;

        if ($user->id === $creatorId || $user->id === $editorId) {
            return false;
        }

        return true;
    }

    /**
     * SoD-03 (Inventory Count & Adjustment):
     * A warehouse clerk who performs physical stock counts cannot approve stock write-off adjustments.
     */
    public static function canApproveStockAdjustment(User $user, object $stockCount): bool
    {
        $counterId = $stockCount->counted_by_user_id ?? $stockCount->counted_by ?? null;

        if ($user->id === $counterId) {
            return false;
        }

        return true;
    }

    /**
     * SoD-04 (Role Escalation Prevention):
     * Tenant Administrators cannot assign the Accountant or Tenant Owner role to themselves or without Owner counter-signature.
     */
    public static function canAssignRole(User $actor, User $target, string $role, ?User $counterSigner = null): bool
    {
        $restrictedRoles = ['Tenant Owner', 'Accountant'];

        if (! in_array($role, $restrictedRoles, true)) {
            return true;
        }

        // Actor cannot self-assign privileged financial/ownership roles
        if ($actor->id === $target->id) {
            return false;
        }

        // If actor is not Tenant Owner, counter-signature from Tenant Owner is required
        if (! $actor->hasRole('Tenant Owner')) {
            if ($counterSigner === null || ! $counterSigner->hasRole('Tenant Owner')) {
                return false;
            }
        }

        return true;
    }

    /**
     * SoD-05 (Super-Admin Elevation Protocol):
     * Validates active consented support access session.
     */
    public static function hasValidSupportAccess(PlatformUser $platformUser, Tenant $tenant): bool
    {
        return TenantSupportAccess::query()
            ->where('tenant_id', $tenant->id)
            ->where('platform_user_id', $platformUser->id)
            ->where('granted_at', '<=', now())
            ->where('expires_at', '>', now())
            ->whereNull('revoked_at')
            ->exists();
    }
}
