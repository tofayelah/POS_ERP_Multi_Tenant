<?php

namespace App\Http\Middleware;

use App\Core\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class PosSyncAdmissionControl
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = TenantContext::id() ?? (string) $request->header('X-Tenant-ID', '');
        $maxConcurrent = (int) config('pos.max_concurrent_sync_batches', 10);
        $key = null;

        if ($tenantId !== '') {
            $key = "pos_sync_concurrency:{$tenantId}";
            $currentSlots = (int) Cache::get($key, 0);

            if ($currentSlots >= $maxConcurrent) {
                $retryAfter = rand(2, 5);

                return response()->json([
                    'error' => 'TOO_MANY_REQUESTS',
                    'message' => 'POS sync queue saturated under mass reconnect load. Please retry with exponential backoff.',
                ], 429, [
                    'Retry-After' => (string) $retryAfter,
                ]);
            }

            Cache::increment($key);
        }

        try {
            return $next($request);
        } finally {
            if ($key !== null) {
                Cache::decrement($key);
            }
        }
    }
}
