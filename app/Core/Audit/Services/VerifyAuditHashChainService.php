<?php

namespace App\Core\Audit\Services;

use App\Core\Audit\DTOs\AuditChainVerificationResult;
use Illuminate\Support\Facades\DB;

class VerifyAuditHashChainService
{
    /**
     * Verify cryptographic Merkle chain integrity in chain_position order for a tenant.
     */
    public function verifyTenant(string $tenantId): AuditChainVerificationResult
    {
        $records = DB::table('audit_logs')
            ->where('tenant_id', $tenantId)
            ->where('status', 'verified')
            ->orderBy('chain_position', 'asc')
            ->get();

        if ($records->isEmpty()) {
            return new AuditChainVerificationResult(tenantId: $tenantId, isValid: true, verifiedCount: 0);
        }

        $expectedPosition = 1;
        $expectedPrevHash = str_repeat('0', 64);
        $tampered = [];
        $verifiedCount = 0;

        foreach ($records as $record) {
            $currentPosition = (int) $record->chain_position;
            $prevHash = (string) $record->prev_hash;
            $currentHash = (string) $record->current_hash;
            $payloadHash = (string) $record->payload_hash;

            // 1. Check sequence continuity
            if ($currentPosition !== $expectedPosition) {
                $tampered[] = [
                    'id' => $record->id,
                    'chain_position' => $currentPosition,
                    'expected_position' => $expectedPosition,
                    'reason' => 'Sequence discontinuity in audit chain',
                ];
                break;
            }

            // 2. Check previous hash link
            if ($prevHash !== $expectedPrevHash) {
                $tampered[] = [
                    'id' => $record->id,
                    'chain_position' => $currentPosition,
                    'expected_prev_hash' => $expectedPrevHash,
                    'actual_prev_hash' => $prevHash,
                    'reason' => 'Broken chain link: prev_hash does not match previous current_hash',
                ];
                break;
            }

            // 3. Check cryptographic hash calculation
            $recalculatedHash = hash('sha256', $prevHash.$payloadHash);
            if ($currentHash !== $recalculatedHash) {
                $tampered[] = [
                    'id' => $record->id,
                    'chain_position' => $currentPosition,
                    'expected_current_hash' => $recalculatedHash,
                    'actual_current_hash' => $currentHash,
                    'reason' => 'Hash mismatch: current_hash does not match sha256(prev_hash || payload_hash)',
                ];
                break;
            }

            $expectedPrevHash = $currentHash;
            $expectedPosition++;
            $verifiedCount++;
        }

        $isValid = empty($tampered);

        return new AuditChainVerificationResult(
            tenantId: $tenantId,
            isValid: $isValid,
            verifiedCount: $verifiedCount,
            tamperedRecords: $tampered,
        );
    }
}
