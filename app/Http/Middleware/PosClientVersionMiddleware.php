<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PosClientVersionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $version = $request->header('X-POS-Client-Version');
        $minVersion = (string) config('pos.min_client_version', '1.1.0');
        $currentVersion = (string) config('pos.current_client_version', '1.2.0');

        if (! $version || version_compare((string) $version, $minVersion, '<')) {
            return response()->json([
                'error' => 'UPGRADE_REQUIRED',
                'message' => "X-POS-Client-Version header is missing or below minimum supported version {$minVersion}.",
                'minimum_version' => $minVersion,
                'current_version' => $currentVersion,
            ], 426);
        }

        return $next($request);
    }
}
