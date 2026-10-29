<?php

namespace App\Core\Http\Middleware;

use App\Models\TenantSupportAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySupportAccessActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $accessId = session('active_support_access_id');

        if (! $accessId) {
            return $next($request);
        }

        /** @var TenantSupportAccess|null $access */
        $access = TenantSupportAccess::where('id', $accessId)->first();

        if (! $access || ! $access->isValid()) {
            session()->forget('active_support_access_id');
            abort(403, 'Support access session has expired or been revoked.');
        }

        // Read-only by default: block write requests unless explicitly unlocked
        if ($access->is_read_only && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            abort(403, 'Support access elevation is strictly read-only.');
        }

        return $next($request);
    }
}
