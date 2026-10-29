<?php

namespace App\Core\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class TenantContext
{
    protected static ?Tenant $currentTenant = null;

    /**
     * Set the active tenant and bind app.current_tenant_id to the database session.
     */
    public static function set(?Tenant $tenant, string $connection = 'pgsql'): void
    {
        self::$currentTenant = $tenant;
        $tenantId = $tenant !== null ? $tenant->id : '';

        DB::connection($connection)->statement(
            "SELECT set_config('app.current_tenant_id', :tenant_id, false)",
            ['tenant_id' => $tenantId]
        );
    }

    /**
     * Set the active tenant by raw UUID.
     */
    public static function setById(?string $tenantId, string $connection = 'pgsql'): void
    {
        if ($tenantId === null || $tenantId === '') {
            self::clear($connection);

            return;
        }

        $tenant = Tenant::find($tenantId);
        self::$currentTenant = $tenant;

        DB::connection($connection)->statement(
            "SELECT set_config('app.current_tenant_id', :tenant_id, false)",
            ['tenant_id' => $tenantId]
        );
    }

    /**
     * Get the active tenant model instance.
     */
    public static function get(): ?Tenant
    {
        return self::$currentTenant;
    }

    /**
     * Get the active tenant ID string.
     */
    public static function id(): ?string
    {
        return self::$currentTenant?->id;
    }

    /**
     * Clear the active tenant and reset the database session setting.
     */
    public static function clear(string $connection = 'pgsql'): void
    {
        self::$currentTenant = null;

        DB::connection($connection)->statement(
            "SELECT set_config('app.current_tenant_id', '', false)"
        );
    }
}
