<?php

namespace App\Core\Http\Middleware;

use App\Core\Tenancy\TenantContext;
use App\Models\Tenant;
use Closure;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTenantState
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = TenantContext::get();

        if (! $tenant) {
            $routeTenant = $request->route('tenant');
            if ($routeTenant instanceof Tenant) {
                $tenant = $routeTenant;
            } elseif (is_string($routeTenant)) {
                $tenant = Tenant::where('subdomain', $routeTenant)->first();
            }
        }

        if ($tenant instanceof Tenant && $tenant->status === 'suspended') {
            // Register suspension banner in Filament panel layout
            FilamentView::registerRenderHook(
                PanelsRenderHook::BODY_START,
                fn () => view('filament.components.suspended-banner', ['tenant' => $tenant])
            );

            // 1. Safe methods (GET, HEAD, OPTIONS) are allowed for read & statutory reporting (TEN-11)
            if ($request->isMethodSafe()) {
                return $next($request);
            }

            // 2. Allow logout / session cleanup
            if ($request->is('*/logout') || $request->routeIs('*.logout')) {
                return $next($request);
            }

            // 3. Allow statutory export requests even on POST if triggered via an export action
            if ($request->is('*/export*') || $request->has('export')) {
                return $next($request);
            }

            // 4. Reject all other mutations with HTTP 403
            abort(Response::HTTP_FORBIDDEN, 'Your tenant account is suspended. State modifications are blocked, but records remain accessible for statutory export (TEN-11).');
        }

        return $next($request);
    }
}
