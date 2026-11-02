<?php

namespace App\Core\Audit\Workers;

use Illuminate\Support\Facades\DB;

class BuildAuditHashChainWorker
{
    /**
     * Process pending audit log records for a given tenant under a per-tenant advisory lock.
     *
     * @return int Number of records hashed and chained
     */
    public function processTenant(string $tenantId, int $batchSize = 500): int
    {
        return DB::transaction(function () use ($tenantId, $batchSize) {
            // Per-tenant advisory lock prevents concurrent hash chain corruption (NEW-31)
            DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ['audit_hasher_'.$tenantId]);

            // Retrieve the latest verified hash and chain position
            /** @var object{chain_position: int, current_hash: string}|null $lastVerified */
            $lastVerified = DB::table('audit_logs')
                ->where('tenant_id', $tenantId)
                ->where('status', 'verified')
                ->orderBy('chain_position', 'desc')
                ->select(['chain_position', 'current_hash'])
                ->lockForUpdate()
                ->first();

            $nextPosition = $lastVerified ? ((int) $lastVerified->chain_position + 1) : 1;
            $prevHash = $lastVerified?->current_hash ?? str_repeat('0', 64);

            // Fetch pending records in deterministic commit order
            $pendingLogs = DB::table('audit_logs')
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending_hash')
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->limit($batchSize)
                ->lockForUpdate()
                ->get();

            if ($pendingLogs->isEmpty()) {
                return 0;
            }

            $count = 0;
            $now = now();

            foreach ($pendingLogs as $log) {
                $payloadHash = (string) $log->payload_hash;
                $currentHash = hash('sha256', $prevHash.$payloadHash);

                DB::table('audit_logs')
                    ->where('id', $log->id)
                    ->update([
                        'chain_position' => $nextPosition,
                        'prev_hash' => $prevHash,
                        'current_hash' => $currentHash,
                        'status' => 'verified',
                        'hashed_at' => $now,
                        'updated_at' => $now,
                    ]);

                $prevHash = $currentHash;
                $nextPosition++;
                $count++;
            }

            return $count;
        });
    }
}
