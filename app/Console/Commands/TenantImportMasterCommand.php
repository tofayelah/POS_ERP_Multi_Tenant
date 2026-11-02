<?php

namespace App\Console\Commands;

use App\Modules\DataMigration\Services\MasterDataImportService;
use Illuminate\Console\Command;
use Throwable;

class TenantImportMasterCommand extends Command
{
    protected $signature = 'tenant:import-master
                            {file : Path to CSV, XLSX, or JSON file containing master records}
                            {--company= : Company UUID}
                            {--type=products : Type of master records (products, customers, suppliers)}
                            {--dry-run : Run in dry-run validation mode without inserting records}';

    protected $description = 'Imports or validates master records (products, customers, suppliers) with dry-run error reporting';

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

        try {
            $rows = $importer->parseFile($filePath);
        } catch (Throwable $e) {
            $this->error("Failed to parse file: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Processing {$type} import with ".count($rows).' row(s) (Dry-run: '.($dryRun ? 'YES' : 'NO').')...');

        $result = match ($type) {
            'customers' => $importer->importCustomers($rows, $companyId, $dryRun),
            'suppliers' => $importer->importSuppliers($rows, $companyId, $dryRun),
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
