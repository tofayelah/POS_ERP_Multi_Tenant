<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

class TenantContext
{
    protected static ?Tenant $tenant = null;
    protected static ?Company $company = null;

    public static function setTenant(?Tenant $tenant): void
    {
        static::$tenant = $tenant;

        if ($tenant) {
            // Set PostgreSQL session variable for fail-closed RLS
            try {
                DB::statement("SET LOCAL app.current_tenant_id = ?", [$tenant->id]);
            } catch (\Throwable $e) {
                // If not in transaction or DB unavailable, log/ignore
            }
        }
    }

    public static function getTenant(): ?Tenant
    {
        return static::$tenant;
    }

    public static function getTenantId(): ?string
    {
        return static::$tenant?->id;
    }

    public static function setCompany(?Company $company): void
    {
        static::$company = $company;
    }

    public static function getCompany(): ?Company
    {
        return static::$company;
    }

    public static function getCompanyId(): ?string
    {
        return static::$company?->id;
    }

    public static function clear(): void
    {
        static::$tenant = null;
        static::$company = null;

        try {
            DB::statement("RESET app.current_tenant_id");
        } catch (\Throwable $e) {
            // Ignore if connection already closed
        }
    }
}
