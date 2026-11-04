<?php

namespace App\Console\Commands;

use App\Core\Audit\Services\VerifyAuditHashChainService;
use App\Models\Tenant;
use Illuminate\Console\Command;

class VerifyAuditChainCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:verify-chain {tenant_id? : Specific tenant ID to verify}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify cryptographic Merkle hash chain integrity of audit logs (NEW-31)';

    /**
     * Execute the console command.
     */
    public function handle(VerifyAuditHashChainService $verifier): int
    {
        $tenantId = $this->argument('tenant_id');

        if ($tenantId) {
            $result = $verifier->verifyTenant((string) $tenantId);
            if (! $result->isValid) {
                $this->error("TAMPER DETECTED: Audit chain invalid for tenant [{$tenantId}]:");
                $this->table(['Record ID', 'Chain Pos', 'Reason'], array_map(fn ($t) => [
                    $t['id'],
                    $t['chain_position'] ?? 'N/A',
                    $t['reason'],
                ], $result->tamperedRecords));

                return Command::FAILURE;
            }

            $this->info("✓ Audit chain valid for tenant [{$tenantId}]. Verified {$result->verifiedCount} records.");

            return Command::SUCCESS;
        }

        $tenants = Tenant::where('status', 'active')->get();
        $hasAnyTamper = false;

        foreach ($tenants as $tenant) {
            $result = $verifier->verifyTenant((string) $tenant->id);
            if (! $result->isValid) {
                $hasAnyTamper = true;
                $this->error("TAMPER DETECTED in tenant [{$tenant->name}] ({$tenant->id})!");
            }
        }

        if ($hasAnyTamper) {
            return Command::FAILURE;
        }

        $this->info('✓ All tenant audit chains verified successfully.');

        return Command::SUCCESS;
    }
}
