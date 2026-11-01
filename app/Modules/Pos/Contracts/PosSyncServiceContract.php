<?php

namespace App\Modules\Pos\Contracts;

interface PosSyncServiceContract
{
    /**
     * Synchronize a batch of offline sales with per-sale savepoint isolation.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function syncBatch(array $payload, string $clientVersion = '1.2.0'): array;
}
