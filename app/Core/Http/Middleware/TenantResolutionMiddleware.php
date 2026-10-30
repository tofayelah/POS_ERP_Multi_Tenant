<?php

namespace App\Core\Http\Middleware;

use App\Core\Tenancy\TenantContext;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantResolutionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $tenant = null;

        // 1. Resolve by custom domain
        $tenant = Tenant::where('custom_domain', $host)
            ->whereIn('status', ['active', 'in_arrears', 'suspended'])
            ->first();

        // 2. Resolve by subdomain if not matched by custom domain
        if (! $tenant) {
            $parts = explode('.', $host);
            if (count($parts) >= 2) {
                $subdomain = $parts[0];
                $tenant = Tenant::where('subdomain', $subdomain)
                    ->whereIn('status', ['active', 'in_arrears', 'suspended'])
                    ->first();
            }
        }

        // 3. Assertion check for X-Tenant-ID header (TEN-05)
        // The header is NEVER trusted to select the tenant; only asserts consistency.
        if ($request->hasHeader('X-Tenant-ID')) {
            $headerTenantId = $request->header('X-Tenant-ID');

            if ($tenant && $headerTenantId !== $tenant->id) {
                abort(403, 'X-Tenant-ID header does not match resolved tenant.');
            }

            // If user has an active session tenant, assert header matches session
            if ($request->user() && isset($request->user()->tenant_id)) {
                if ($headerTenantId !== $request->user()->tenant_id) {
                    abort(403, 'X-Tenant-ID header does not match authenticated user tenant.');
                }
            }
        }

        if ($tenant) {
            TenantContext::set($tenant);
        }

        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        TenantContext::clear();
    }
}
