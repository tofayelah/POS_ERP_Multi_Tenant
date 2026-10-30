<?php

namespace App\Modules\Billing\Contracts;

use App\Models\Setting;

interface HierarchicalSettingsServiceContract
{
    /**
     * Resolve a setting key along the 5-level hierarchy:
     * Terminal -> Branch -> Company -> Tenant -> Platform -> $default
     */
    public function get(
        string $key,
        ?string $terminalId = null,
        ?string $branchId = null,
        ?string $companyId = null,
        ?string $tenantId = null,
        mixed $default = null
    ): mixed;

    /**
     * Set a setting at a specific scope level, log audit change, and invalidate cache.
     */
    public function set(
        string $key,
        mixed $value,
        string $scopeType,
        ?string $scopeId = null,
        ?string $tenantId = null,
        bool $isSecret = false,
        ?string $userId = null
    ): Setting;

    /**
     * Delete a setting at a specific scope level and invalidate cache.
     */
    public function delete(
        string $key,
        string $scopeType,
        ?string $scopeId = null,
        ?string $tenantId = null,
        ?string $userId = null
    ): bool;

    /**
     * Invalidate cached settings for a tenant or platform.
     */
    public function invalidateCache(?string $tenantId = null): void;
}
