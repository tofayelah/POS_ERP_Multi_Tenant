<?php

namespace App\Core\Contracts;

use App\Core\HealthCheckResult;

interface ModuleInterface
{
    public function getIdentifier(): string;

    public function getName(): string;

    public function getVersion(): string;

    /**
     * @return array<int, string>
     */
    public function getDependencies(): array;

    public function registerRoutes(): void;

    /**
     * @return array<int, string>
     */
    public function registerPermissions(): array;

    /**
     * @return array<int, class-string>
     */
    public function registerFilamentResources(): array;

    public function registerScheduledTasks(): void;

    public function registerEventSubscribers(): void;

    /**
     * @return array<string, mixed>
     */
    public function registerSettingsSchema(): array;

    public function seedDefaultData(string $tenantId, string $companyId): void;

    public function runHealthCheck(string $tenantId): HealthCheckResult;

    /**
     * MOD-02: Module migration file paths or descriptors.
     *
     * @return array<int, string>
     */
    public function getMigrations(): array;

    /**
     * MOD-02: Module authorization policies mapping model to policy class.
     *
     * @return array<class-string, class-string>
     */
    public function getPolicies(): array;

    /**
     * MOD-02: Module tenant installation lifecycle hook.
     */
    public function install(string $tenantId): void;

    /**
     * MOD-02: Module tenant upgrade lifecycle hook.
     */
    public function upgrade(string $tenantId, string $fromVersion, string $toVersion): void;

    /**
     * MOD-02: Module tenant uninstall lifecycle hook.
     */
    public function uninstall(string $tenantId): void;
}
