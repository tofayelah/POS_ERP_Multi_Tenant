<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant) {
            TenantContext::setTenant($tenant);

            // Also set default company if not set
            if (!TenantContext::getCompany()) {
                $company = $tenant->companies()->first();
                if ($company) {
                    TenantContext::setCompany($company);
                }
            }
        }

        $response = $next($request);

        return $response;
    }

    protected function resolveTenant(Request $request): ?Tenant
    {
        // 1. Resolve from custom header (e.g. POS / API sync)
        if ($tenantId = $request->header('X-Tenant-ID')) {
            return Tenant::find($tenantId);
        }

        if ($subdomainHeader = $request->header('X-Tenant-Subdomain')) {
            return Tenant::where('subdomain', $subdomainHeader)->first();
        }

        // 2. Resolve from subdomain (e.g. sonarmart.kcerp.com or sonarmart.dev.kcerp.com)
        $host = $request->getHost();
        $parts = explode('.', $host);

        if (count($parts) >= 3) {
            $subdomain = $parts[0];
            // Exclude administrative subdomains
            if (!in_array($subdomain, ['www', 'admin', 'api', 'dev', 'staging'])) {
                $tenant = Tenant::where('subdomain', $subdomain)->first();
                if ($tenant) {
                    return $tenant;
                }
            }
        }

        // 3. Resolve from authenticated user
        if ($user = $request->user()) {
            if (isset($user->tenant_id) && $user->tenant_id) {
                return Tenant::find($user->tenant_id);
            }
        }

        // 4. Resolve from session
        if ($request->hasSession() && $tenantId = $request->session()->get('active_tenant_id')) {
            return Tenant::find($tenantId);
        }

        // 5. Fallback for single-tenant / staging / dev environment
        return Tenant::query()->first();
    }
}
