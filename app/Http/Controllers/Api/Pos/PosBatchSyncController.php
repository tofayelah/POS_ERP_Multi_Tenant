<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Modules\Pos\Contracts\PosSyncServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosBatchSyncController extends Controller
{
    public function __construct(
        protected PosSyncServiceContract $syncService,
    ) {}

    public function syncBatch(Request $request): JsonResponse
    {
        $version = (string) $request->header('X-POS-Client-Version', '1.2.0');
        $result = $this->syncService->syncBatch($request->all(), $version);

        return response()->json($result);
    }
}
