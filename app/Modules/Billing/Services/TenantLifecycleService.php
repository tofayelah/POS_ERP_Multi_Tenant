<?php

namespace App\Modules\Billing\Services;

use App\Core\Exceptions\TenantSuspendedException;
use App\Models\Tenant;
use App\Modules\Billing\Contracts\TenantLifecycleServiceContract;
use DateTimeInterface;

class TenantLifecycleService implements TenantLifecycleServiceContract
{
    public function isActive(Tenant $tenant): bool
    {
        return $tenant->status === 'active';
    }

    public function isInArrears(Tenant $tenant): bool
    {
        return $tenant->status === 'in_arrears';
    }

    public function isSuspended(Tenant $tenant): bool
    {
        return $tenant->status === 'suspended';
    }

    public function canOpenPosShift(Tenant $tenant): bool
    {
        return ! $this->isSuspended($tenant);
    }

    public function canClosePosShift(Tenant $tenant): bool
    {
        // Suspended tenants are explicitly allowed to finish & close their in-flight shift (TEN-11)
        return true;
    }

    public function assertCanOpenShift(Tenant $tenant): void
    {
        if ($this->isSuspended($tenant)) {
            throw new TenantSuspendedException(
                "Tenant [{$tenant->name}] is currently suspended. Opening new POS shifts is blocked (TEN-11)."
            );
        }
    }

    public function suspend(Tenant $tenant, ?string $reason = null): void
    {
        $tenant->update(['status' => 'suspended']);

        $activeSub = $tenant->activeSubscription;
        if ($activeSub) {
            $activeSub->update(['status' => 'suspended']);
        }
    }

    public function reinstate(Tenant $tenant): void
    {
        $tenant->update(['status' => 'active']);

        $activeSub = $tenant->subscriptions()->latest('created_at')->first();
        if ($activeSub && $activeSub->status === 'suspended') {
            $activeSub->update(['status' => 'active']);
        }
    }

    public function markInArrears(Tenant $tenant, ?DateTimeInterface $gracePeriodEndsAt = null): void
    {
        $tenant->update(['status' => 'in_arrears']);

        $activeSub = $tenant->activeSubscription;
        if ($activeSub) {
            $activeSub->update([
                'status' => 'in_arrears',
                'grace_period_ends_at' => $gracePeriodEndsAt,
            ]);
        }
    }
}
