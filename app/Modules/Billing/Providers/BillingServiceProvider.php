<?php

namespace App\Modules\Billing\Providers;

use App\Models\Tenant;
use App\Modules\Billing\Contracts\HierarchicalSettingsServiceContract;
use App\Modules\Billing\Contracts\ModuleEntitlementServiceContract;
use App\Modules\Billing\Contracts\TenantLifecycleServiceContract;
use App\Modules\Billing\Services\HierarchicalSettingsService;
use App\Modules\Billing\Services\ModuleEntitlementService;
use App\Modules\Billing\Services\TenantLifecycleService;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class BillingServiceProvider extends ServiceProvider
{
    /**
     * @var array<int, string>
     */
    protected array $modules = [
        'accounting',
        'tax',
        'catalog',
        'inventory',
        'procurement',
        'sales',
        'pos',
        'reporting',
    ];

    public function register(): void
    {
        $this->app->singleton(
            ModuleEntitlementServiceContract::class,
            ModuleEntitlementService::class
        );

        $this->app->singleton(
            TenantLifecycleServiceContract::class,
            TenantLifecycleService::class
        );

        $this->app->singleton(
            HierarchicalSettingsServiceContract::class,
            HierarchicalSettingsService::class
        );
    }

    public function boot(): void
    {
        // Define Pennant feature flags for each module strictly derived from tenant entitlements (MOD-04)
        foreach ($this->modules as $module) {
            Feature::define($module, function (Tenant $tenant) use ($module): bool {
                return $this->app->make(ModuleEntitlementServiceContract::class)->isEntitled($tenant, $module);
            });
        }
    }
}
