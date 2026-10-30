<?php

namespace App\Modules\Billing\Contracts;

use App\Core\Exceptions\ModuleHasActiveDependentsException;
use App\Core\Exceptions\ModuleHasOpenDocumentsException;
use App\Models\Tenant;
use App\Models\TenantSubscription;

interface ModuleEntitlementServiceContract
{
    /**
     * Synchronize tenant module entitlements from a subscription and its plan.
     */
    public function syncFromSubscription(TenantSubscription $subscription): void;

    /**
     * Check if a tenant is entitled to use a specific module.
     */
    public function isEntitled(Tenant $tenant, string $moduleIdentifier): bool;

    /**
     * Enable a module for a tenant with a given source (plan, addon, override).
     */
    public function enable(Tenant $tenant, string $moduleIdentifier, string $source = 'override'): void;

    /**
     * Disable a module for a tenant, guarded by active dependents and open documents checks.
     *
     * @throws ModuleHasActiveDependentsException
     * @throws ModuleHasOpenDocumentsException
     */
    public function disable(Tenant $tenant, string $moduleIdentifier): void;

    /**
     * Get module dependencies for a given module.
     *
     * @return array<int, string>
     */
    public function getDependencies(string $moduleIdentifier): array;

    /**
     * Get active dependents for a given module in a tenant.
     *
     * @return array<int, string>
     */
    public function getActiveDependents(Tenant $tenant, string $moduleIdentifier): array;
}
