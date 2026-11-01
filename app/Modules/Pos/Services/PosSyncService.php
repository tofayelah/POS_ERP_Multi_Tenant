<?php

namespace App\Modules\Pos\Services;

use App\Modules\Pos\Adapters\PosSyncPayloadAdapter;
use App\Modules\Pos\Contracts\PosSyncServiceContract;

class PosSyncService implements PosSyncServiceContract
{
    public function __construct(
        protected PosSyncPayloadAdapter $adapter,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function syncBatch(array $payload, string $clientVersion = '1.2.0'): array
    {
        $batch = $this->adapter->adapt($payload, $clientVersion);

        return [
            'batch_id' => $batch->batchId,
            'terminal_id' => $batch->terminalId,
            'status' => 'success',
            'processed_count' => count($batch->sales),
            'results' => [],
        ];
    }
}
