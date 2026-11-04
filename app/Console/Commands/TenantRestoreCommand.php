<?php

namespace App\Console\Commands;

use App\Modules\DataMigration\Services\TenantRestoreService;
use Illuminate\Console\Command;

class TenantRestoreCommand extends Command
{
    protected $signature = 'tenant:restore
                            {archive_file : Path to the JSON archive file}
                            {--target-tenant= : The UUID of the target tenant to restore into}';

    protected $description = 'Restores an exported tenant JSON archive into a target tenant context';

    public function handle(TenantRestoreService $restorer): int
    {
        $archiveFile = (string) $this->argument('archive_file');
        $targetTenantId = (string) $this->option('target-tenant');

        if (empty($targetTenantId)) {
            $this->error('--target-tenant UUID is required.');

            return self::FAILURE;
        }

        if (! file_exists($archiveFile)) {
            $this->error("Archive file not found: {$archiveFile}");

            return self::FAILURE;
        }

        $this->info("Restoring archive into tenant {$targetTenantId}...");

        $result = $restorer->restore($archiveFile, $targetTenantId);

        $this->info("Successfully restored {$result['restored_tables']} table(s).");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Target Tenant ID', $result['target_tenant_id']],
                ['Total Debits', $result['trial_balance']['total_debits']],
                ['Total Credits', $result['trial_balance']['total_credits']],
                ['Total Stock Qty', $result['stock_summary']['total_quantity']],
            ]
        );

        return self::SUCCESS;
    }
}
