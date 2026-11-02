<?php

namespace App\Console\Commands;

use App\Modules\DataMigration\Services\TenantExportService;
use Illuminate\Console\Command;

class TenantExportCommand extends Command
{
    protected $signature = 'tenant:export
                            {tenant_id : The UUID of the tenant to export}
                            {--output= : Optional path to write the JSON archive}';

    protected $description = 'Exports all data for a specific tenant into a portable verified JSON archive';

    public function handle(TenantExportService $exporter): int
    {
        $tenantId = (string) $this->argument('tenant_id');
        $output = $this->option('output');

        $this->info("Exporting tenant {$tenantId}...");

        $archive = $exporter->export(
            tenantId: $tenantId,
            outputPath: is_string($output) && ! empty($output) ? $output : null
        );

        $this->info("Successfully exported {$archive['metadata']['total_records']} record(s) across {$archive['metadata']['tables_count']} table(s).");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Tenant ID', $archive['metadata']['tenant_id']],
                ['Total Debits', $archive['metadata']['trial_balance']['total_debits']],
                ['Total Credits', $archive['metadata']['trial_balance']['total_credits']],
                ['Total Stock Qty', $archive['metadata']['stock_summary']['total_quantity']],
            ]
        );

        return self::SUCCESS;
    }
}
