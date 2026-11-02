<?php

namespace App\Core\Audit\DTOs;

class AuditChainVerificationResult
{
    /**
     * @param  array<int, array<string, mixed>>  $tamperedRecords
     */
    public function __construct(
        public string $tenantId,
        public bool $isValid = true,
        public int $verifiedCount = 0,
        public array $tamperedRecords = [],
    ) {}
}
