<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthCheckController extends Controller
{
    /**
     * Liveness probe: confirms the application HTTP layer is running.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'kcerp-backend',
            'timestamp' => Carbon::now()->toIso8601String(),
        ]);
    }

    /**
     * Readiness probe: verifies core infrastructure dependencies (Database, Cache, Disk).
     */
    public function ready(): JsonResponse
    {
        $checks = [
            'database' => 'failed',
            'cache' => 'failed',
            'storage' => 'failed',
        ];

        $isReady = true;

        // 1. Check PostgreSQL connection
        try {
            $dbResult = DB::selectOne('SELECT 1 AS alive');
            if ($dbResult && (int) $dbResult->alive === 1) {
                $checks['database'] = 'ok';
            } else {
                $isReady = false;
            }
        } catch (Throwable) {
            $isReady = false;
        }

        // 2. Check Cache
        try {
            Cache::put('readiness_heartbeat', 'ok', 10);
            if (Cache::get('readiness_heartbeat') === 'ok') {
                $checks['cache'] = 'ok';
            } else {
                $isReady = false;
            }
        } catch (Throwable) {
            $isReady = false;
        }

        // 3. Check Disk Storage write
        try {
            $testFile = storage_path('framework/cache/readiness.tmp');
            if (@file_put_contents($testFile, 'ok') !== false) {
                @unlink($testFile);
                $checks['storage'] = 'ok';
            } else {
                $isReady = false;
            }
        } catch (Throwable) {
            $isReady = false;
        }

        $statusCode = $isReady ? 200 : 503;

        return response()->json([
            'status' => $isReady ? 'ready' : 'degraded',
            'checks' => $checks,
            'timestamp' => Carbon::now()->toIso8601String(),
        ], $statusCode);
    }
}
