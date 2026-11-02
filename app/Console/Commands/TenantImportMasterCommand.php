<?php

namespace App\Console\Commands;

use App\Modules\DataMigration\Services\MasterDataImportService;
use Illuminate\Console\Command;

class TenantImportMasterCommand extends Command
{
    protected $signature = 'tenant:import-master
                            {file : Path to CSV or JSON file containing master records}
                            {--company= : Company UUID}
                            {--type=products : Type of master records (products, customers)}
                            {--dry-run : Run in dry-run validation mode without inserting records}';

    protected $description = 'Imports or validates master records (products, customers) with dry-run error reporting';

    public function handle(MasterDataImportService $importer): int
    {
        $filePath = (string) $this->argument('file');
        $companyId = (string) $this->option('company');
        $type = (string) $this->option('type');
        $dryRun = (bool) $this->option('dry-run');

        if (empty($companyId)) {
            $this->error('--company UUID is required.');

            return self::FAILURE;
        }

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        $content = (string) file_get_contents($filePath);
        $rows = json_decode($content, true);

        if (! is_array($rows)) {
            $this->error('File must contain a valid JSON array of records.');

            return self::FAILURE;
        }

        $this->info("Processing {$type} import (Dry-run: ".($dryRun ? 'YES' : 'NO').')...');

        $result = match ($type) {
            'customers' => $importer->importCustomers($rows, $companyId, $dryRun),
            default => $importer->importProducts($rows, $companyId, $dryRun),
        };

        if (! $result['valid']) {
            $this->error('Validation errors found:');
            $this->table(['Row', 'Field', 'Message'], $result['errors']);

            return self::FAILURE;
        }

        $this->info("Import completed successfully. Processed: {$result['processed']} record(s).");

        return self::SUCCESS;
    }
}
