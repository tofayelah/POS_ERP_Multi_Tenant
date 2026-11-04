<?php

namespace App\Core\Cache;

use App\Core\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Facades\Cache;

class TenantCache
{
    /**
     * Build a tenant-scoped cache key.
     */
    public static function key(string $key, ?string $tenantId = null): string
    {
        $tenantId = $tenantId ?? TenantContext::id() ?? 'global';

        return "tenant:{$tenantId}:{$key}";
    }

    /**
     * Retrieve an item from the cache.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::get(self::key($key), $default);
    }

    /**
     * Store an item in the cache.
     */
    public static function put(string $key, mixed $value, mixed $ttl = null): bool
    {
        return Cache::put(self::key($key), $value, $ttl);
    }

    /**
     * Remove an item from the cache.
     */
    public static function forget(string $key): bool
    {
        return Cache::forget(self::key($key));
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @template TCacheValue
     *
     * @param  Closure(): TCacheValue  $callback
     * @return TCacheValue
     */
    public static function remember(string $key, mixed $ttl, Closure $callback): mixed
    {
        return Cache::remember(self::key($key), $ttl, $callback);
    }
}
