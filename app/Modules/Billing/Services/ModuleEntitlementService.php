<?php

namespace App\Modules\Billing\Services;

use App\Core\Exceptions\ModuleHasActiveDependentsException;
use App\Core\Exceptions\ModuleHasOpenDocumentsException;
use App\Models\JournalEntry;
use App\Models\PosShift;
use App\Models\Tenant;
use App\Models\TenantModuleEntitlement;
use App\Models\TenantSubscription;
use App\Modules\Billing\Contracts\ModuleEntitlementServiceContract;
use Laravel\Pennant\Feature;

class ModuleEntitlementService implements ModuleEntitlementServiceContract
{
    /**
     * Module dependency graph per system design §5.3.
     *
     * @var array<string, array<int, string>>
     */
    protected array $dependencyGraph = [
        'pos' => ['sales', 'catalog', 'tax'],
        'sales' => ['catalog', 'tax', 'accounting'],
        'procurement' => ['catalog', 'tax', 'accounting'],
        'inventory' => ['catalog'],
        'accounting' => [],
        'tax' => [],
        'catalog' => [],
        'reporting' => [],
    ];

    /**
     * @var array<int, string>
     */
    protected array $allModules = [
        'accounting',
        'tax',
        'catalog',
        'inventory',
        'procurement',
        'sales',
        'pos',
        'reporting',
    ];

    public function syncFromSubscription(TenantSubscription $subscription): void
    {
        $tenant = $subscription->tenant;
        $plan = $subscription->plan;

        $planModules = (array) ($plan->included_modules ?? []);
        $extraModules = (array) ($subscription->extra_modules ?? []);
        $entitledModules = array_unique(array_merge($planModules, $extraModules));

        foreach ($this->allModules as $module) {
            $isEntitled = in_array($module, $entitledModules, true);
            $source = in_array($module, $extraModules, true) ? 'addon' : 'plan';

            TenantModuleEntitlement::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'module_identifier' => $module,
                ],
                [
                    'is_enabled' => $isEntitled,
                    'source' => $source,
                ]
            );

            Feature::for($tenant)->forget($module);
        }
    }

    public function isEntitled(Tenant $tenant, string $moduleIdentifier): bool
    {
        $entitlement = TenantModuleEntitlement::where('tenant_id', $tenant->id)
            ->where('module_identifier', $moduleIdentifier)
            ->first();

        return $entitlement ? (bool) $entitlement->is_enabled : false;
    }

    public function enable(Tenant $tenant, string $moduleIdentifier, string $source = 'override'): void
    {
        TenantModuleEntitlement::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'module_identifier' => $moduleIdentifier,
            ],
            [
                'is_enabled' => true,
                'source' => $source,
            ]
        );

        Feature::for($tenant)->forget($moduleIdentifier);
    }

    public function disable(Tenant $tenant, string $moduleIdentifier): void
    {
        // 1. Check active dependents (MOD-03)
        $activeDependents = $this->getActiveDependents($tenant, $moduleIdentifier);
        if (! empty($activeDependents)) {
            throw new ModuleHasActiveDependentsException(
                "Cannot disable module [{$moduleIdentifier}] because active modules [" . implode(', ', $activeDependents) . '] depend on it (MOD-03).'
            );
        }

        // 2. Check open documents (MOD-03)
        $this->assertNoOpenDocuments($tenant, $moduleIdentifier);

        // 3. Mark disabled in database
        TenantModuleEntitlement::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'module_identifier' => $moduleIdentifier,
            ],
            [
                'is_enabled' => false,
            ]
        );

        Feature::for($tenant)->forget($moduleIdentifier);
    }

    /**
     * @return array<int, string>
     */
    public function getDependencies(string $moduleIdentifier): array
    {
        return $this->dependencyGraph[$moduleIdentifier] ?? [];
    }

    /**
     * @return array<int, string>
     */
    public function getActiveDependents(Tenant $tenant, string $moduleIdentifier): array
    {
        $dependents = [];

        foreach ($this->allModules as $module) {
            if ($module === $moduleIdentifier) {
                continue;
            }

            if (! $this->isEntitled($tenant, $module)) {
                continue;
            }

            $dependencies = $this->getDependencies($module);
            if (in_array($moduleIdentifier, $dependencies, true)) {
                $dependents[] = $module;
            }
        }

        return $dependents;
    }

    /**
     * Ensure no unposted or draft documents exist before disabling module.
     */
    protected function assertNoOpenDocuments(Tenant $tenant, string $moduleIdentifier): void
    {
        if ($moduleIdentifier === 'accounting') {
            $hasDraftJournals = JournalEntry::where('tenant_id', $tenant->id)
                ->where('status', 'draft')
                ->exists();

            if ($hasDraftJournals) {
                throw new ModuleHasOpenDocumentsException(
                    'Cannot disable accounting module because unposted draft journal entries exist (MOD-03).'
                );
            }
        }

        if ($moduleIdentifier === 'pos') {
            $hasOpenShifts = PosShift::where('tenant_id', $tenant->id)
                ->where('status', 'open')
                ->exists();

            if ($hasOpenShifts) {
                throw new ModuleHasOpenDocumentsException(
                    'Cannot disable POS module because open POS shifts exist (MOD-03).'
                );
            }
        }
    }
}
