<?php

namespace App\Core\Providers;

use App\Core\Contracts\ModuleInterface;
use App\Core\HealthCheckResult;
use Illuminate\Support\ServiceProvider;

abstract class AbstractModuleServiceProvider extends ServiceProvider implements ModuleInterface
{
    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function registerRoutes(): void {}

    /**
     * @return array<int, string>
     */
    public function registerPermissions(): array
    {
        return [];
    }

    /**
     * @return array<int, class-string>
     */
    public function registerFilamentResources(): array
    {
        return [];
    }

    public function registerScheduledTasks(): void {}

    public function registerEventSubscribers(): void {}

    /**
     * @return array<string, mixed>
     */
    public function registerSettingsSchema(): array
    {
        return [];
    }

    public function seedDefaultData(string $tenantId, string $companyId): void {}

    public function runHealthCheck(string $tenantId): HealthCheckResult
    {
        return HealthCheckResult::healthy("Module {$this->getName()} is operating normally.");
    }

    /**
     * @return array<int, string>
     */
    public function getMigrations(): array
    {
        return [];
    }

    /**
     * @return array<class-string, class-string>
     */
    public function getPolicies(): array
    {
        return [];
    }

    public function install(string $tenantId): void {}

    public function upgrade(string $tenantId, string $fromVersion, string $toVersion): void {}

    public function uninstall(string $tenantId): void {}
}
