<?php

namespace App\Modules\Billing\Contracts;

use App\Models\Tenant;
use DateTimeInterface;

interface TenantLifecycleServiceContract
{
    public function isActive(Tenant $tenant): bool;

    public function isInArrears(Tenant $tenant): bool;

    public function isSuspended(Tenant $tenant): bool;

    public function canOpenPosShift(Tenant $tenant): bool;

    public function canClosePosShift(Tenant $tenant): bool;

    /**
     * Assert that tenant is permitted to open a new POS shift.
     *
     * @throws \App\Core\Exceptions\TenantSuspendedException
     */
    public function assertCanOpenShift(Tenant $tenant): void;

    public function suspend(Tenant $tenant, ?string $reason = null): void;

    public function reinstate(Tenant $tenant): void;

    public function markInArrears(Tenant $tenant, ?DateTimeInterface $gracePeriodEndsAt = null): void;
}
