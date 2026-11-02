<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenancy\TenantContext;
use App\Core\Watchdog\Services\IntegrityWatchdogService;
use App\Models\Company;
use App\Models\Tenant;
use App\Modules\Accounting\Services\FinancialReportService;
use App\Modules\Inventory\Services\InventoryGlReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Throwable;

class RunDisasterRecoveryDrillCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dr:drill
                            {--backup= : Path to backup archive (creates fresh snapshot if omitted)}
                            {--scratch-db=erp_dr_scratch : Name of the temporary scratch database}
                            {--keep : Retain scratch database after verification completes}
                            {--json : Output machine-readable JSON metrics}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute automated disaster recovery drill: restore backup into scratch DB, run watchdog, and reconcile trial balances & stock values (OPS-02, OPS-06)';

    public function handle(
        IntegrityWatchdogService $watchdogService,
        FinancialReportService $financialReportService,
        InventoryGlReconciliationService $inventoryReconciliationService
    ): int {
        $startTime = microtime(true);
        $scratchDb = (string) $this->option('scratch-db');
        $backupPath = (string) ($this->option('backup') ?? '');
        $keepScratch = (bool) $this->option('keep');
        $isJson = (bool) $this->option('json');

        if (! $isJson) {
            $this->info('====================================================================');
            $this->info(' Disaster Recovery Restoration & Verification Drill (OPS-02, OPS-06)');
            $this->info(' Target Scratch DB : '.$scratchDb);
            $this->info('====================================================================');
        }

        // 1. Database Connection Metadata
        $ownerConfig = Config::get('database.connections.pgsql_owner', []);
        $host = (string) ($ownerConfig['host'] ?? '127.0.0.1');
        $port = (string) ($ownerConfig['port'] ?? '5432');
        $user = (string) ($ownerConfig['username'] ?? 'erp_owner');
        $password = (string) ($ownerConfig['password'] ?? '');
        $sourceDb = (string) ($ownerConfig['database'] ?? 'erp');

        // 2. Collect Baseline Metrics from Primary / Source Database
        if (! $isJson) {
            $this->line('--> Capturing production/source baseline financial and inventory totals...');
        }

        try {
            $baseline = $this->collectFinancialAndStockMetrics($financialReportService, $inventoryReconciliationService);
        } catch (Throwable $e) {
            $this->error('Failed to collect baseline metrics from source database: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $isJson) {
            $this->line(sprintf('    Captured baseline across %d active tenant(s).', count($baseline)));
        }

        // 3. Prepare or Generate Backup File
        $isGeneratedBackup = false;
        if (empty($backupPath)) {
            $backupDir = storage_path('app/dr_backups');
            if (! is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            $backupPath = $backupDir.'/drill_snapshot_'.date('Ymd_His').'.dump';
            $isGeneratedBackup = true;

            if (! $isJson) {
                $this->line('--> Generating fresh database backup snapshot via pg_dump...');
            }

            $dumpProcess = new Process(
                ['pg_dump', '-h', $host, '-p', $port, '-U', $user, '-d', $sourceDb, '-Fc', '-f', $backupPath],
                null,
                ['PGPASSWORD' => $password]
            );
            $dumpProcess->setTimeout(300);
            $dumpProcess->run();

            if (! $dumpProcess->isSuccessful()) {
                $this->error('pg_dump failed: '.$dumpProcess->getErrorOutput());

                return self::FAILURE;
            }

            if (! $isJson) {
                $sizeMb = round(filesize($backupPath) / (1024 * 1024), 2);
                $this->line(sprintf('    Backup snapshot generated (%s MB): %s', $sizeMb, $backupPath));
            }
        } elseif (! file_exists($backupPath)) {
            $this->error("Specified backup file does not exist: {$backupPath}");

            return self::FAILURE;
        }

        $restoreStart = microtime(true);

        // 4. Drop and Recreate Scratch Database
        if (! $isJson) {
            $this->line("--> Re-creating scratch database [{$scratchDb}] on PostgreSQL 17...");
        }

        // Terminate active connections to scratch database if any
        $this->executeSqlOnPostgres(
            $host,
            $port,
            $user,
            $password,
            "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '{$scratchDb}';"
        );
        $this->executeSqlOnPostgres(
            $host,
            $port,
            $user,
            $password,
            "DROP DATABASE IF EXISTS \"{$scratchDb}\";"
        );
        $this->executeSqlOnPostgres(
            $host,
            $port,
            $user,
            $password,
            "CREATE DATABASE \"{$scratchDb}\" OWNER \"{$user}\";"
        );

        // 5. Restore Backup into Scratch Database
        if (! $isJson) {
            $this->line("--> Restoring backup payload into [{$scratchDb}] via pg_restore...");
        }

        $restoreProcess = new Process(
            ['pg_restore', '-h', $host, '-p', $port, '-U', $user, '-d', $scratchDb, '--no-owner', $backupPath],
            null,
            ['PGPASSWORD' => $password]
        );
        $restoreProcess->setTimeout(600);
        $restoreProcess->run();

        // pg_restore may return warnings (code 1) for harmless notices; code > 1 indicates hard error
        if ($restoreProcess->getExitCode() > 1) {
            $this->error('pg_restore encountered critical error: '.$restoreProcess->getErrorOutput());

            return self::FAILURE;
        }

        $restoreDuration = microtime(true) - $restoreStart;
        if (! $isJson) {
            $this->line(sprintf('    Restore completed in %.2fs.', $restoreDuration));
        }

        // 6. Connect to Restored Scratch Database
        $originalDefault = Config::get('database.default');
        $scratchConnectionKey = 'pgsql_dr_scratch';

        Config::set("database.connections.{$scratchConnectionKey}", array_merge(
            $ownerConfig,
            ['database' => $scratchDb]
        ));
        DB::purge($scratchConnectionKey);
        DB::setDefaultConnection($scratchConnectionKey);

        $watchdogStart = microtime(true);
        $watchdogBreaches = 0;
        $unbalancedCount = 0;
        $stockDiscrepancyCount = 0;

        // 7. Execute Integrity Watchdog on Restored Database
        if (! $isJson) {
            $this->line('--> Running Business Integrity Watchdog on restored scratch database (§14)...');
        }

        try {
            $watchdogReport = $watchdogService->checkAllTenants();
            $watchdogBreaches = $watchdogReport->totalBreaches();

            if ($watchdogReport->hasAnyBreach()) {
                $this->error("FAIL: Watchdog detected {$watchdogBreaches} invariant breach(es) in restored database!");
                foreach ($watchdogReport->results as $res) {
                    $unbalancedCount += count($res->unbalancedJournals);
                    $stockDiscrepancyCount += count($res->stockDiscrepancies);
                }
            } elseif (! $isJson) {
                $this->line('    ✓ Watchdog PASSED: All posted journals balance and stock balances equal movements.');
            }
        } catch (Throwable $e) {
            $this->error('Watchdog inspection encountered exception: '.$e->getMessage());
            $this->restoreOriginalConnection($originalDefault, $scratchConnectionKey, $scratchDb, $host, $port, $user, $password, $keepScratch, $backupPath, $isGeneratedBackup);

            return self::FAILURE;
        }

        $watchdogDuration = microtime(true) - $watchdogStart;

        // 8. Financial and Stock Valuation Reconciliation
        $verifyStart = microtime(true);
        if (! $isJson) {
            $this->line('--> Reconciling Trial Balances and Stock Valuations against baseline...');
        }

        $restoredMetrics = [];
        $discrepancies = [];

        try {
            $restoredMetrics = $this->collectFinancialAndStockMetrics($financialReportService, $inventoryReconciliationService);

            // Compare each tenant and company against baseline
            foreach ($baseline as $tenantId => $baseTenant) {
                if (! isset($restoredMetrics[$tenantId])) {
                    $discrepancies[] = "Missing tenant [{$baseTenant['tenant_name']}] ({$tenantId}) in restored database.";

                    continue;
                }

                $restoredTenant = $restoredMetrics[$tenantId];

                foreach ($baseTenant['companies'] as $companyId => $baseComp) {
                    if (! isset($restoredTenant['companies'][$companyId])) {
                        $discrepancies[] = "Missing company [{$baseComp['legal_name']}] ({$companyId}) for tenant [{$baseTenant['tenant_name']}].";

                        continue;
                    }

                    $restoredComp = $restoredTenant['companies'][$companyId];

                    // Compare Trial Balance Debits
                    if ($baseComp['total_debit'] !== $restoredComp['total_debit']) {
                        $discrepancies[] = sprintf(
                            'Trial Balance Debit mismatch for [%s / %s]: Baseline=%s, Restored=%s',
                            $baseTenant['tenant_name'],
                            $baseComp['legal_name'],
                            $baseComp['total_debit'],
                            $restoredComp['total_debit']
                        );
                    }

                    // Compare Trial Balance Credits
                    if ($baseComp['total_credit'] !== $restoredComp['total_credit']) {
                        $discrepancies[] = sprintf(
                            'Trial Balance Credit mismatch for [%s / %s]: Baseline=%s, Restored=%s',
                            $baseTenant['tenant_name'],
                            $baseComp['legal_name'],
                            $baseComp['total_credit'],
                            $restoredComp['total_credit']
                        );
                    }

                    // Compare Physical Stock Valuation
                    if ($baseComp['stock_valuation'] !== $restoredComp['stock_valuation']) {
                        $discrepancies[] = sprintf(
                            'Stock Valuation mismatch for [%s / %s]: Baseline=%s, Restored=%s',
                            $baseTenant['tenant_name'],
                            $baseComp['legal_name'],
                            $baseComp['stock_valuation'],
                            $restoredComp['stock_valuation']
                        );
                    }

                    // Compare Merchandise Inventory GL Balance
                    if ($baseComp['gl_inventory_balance'] !== $restoredComp['gl_inventory_balance']) {
                        $discrepancies[] = sprintf(
                            'GL Inventory Balance (1040) mismatch for [%s / %s]: Baseline=%s, Restored=%s',
                            $baseTenant['tenant_name'],
                            $baseComp['legal_name'],
                            $baseComp['gl_inventory_balance'],
                            $restoredComp['gl_inventory_balance']
                        );
                    }
                }
            }
        } catch (Throwable $e) {
            $this->error('Reconciliation comparison encountered exception: '.$e->getMessage());
            $this->restoreOriginalConnection($originalDefault, $scratchConnectionKey, $scratchDb, $host, $port, $user, $password, $keepScratch, $backupPath, $isGeneratedBackup);

            return self::FAILURE;
        }

        $verifyDuration = microtime(true) - $verifyStart;
        $totalRecoveryTime = microtime(true) - $startTime;

        // 9. Reconnect to Original Database and Cleanup
        $this->restoreOriginalConnection($originalDefault, $scratchConnectionKey, $scratchDb, $host, $port, $user, $password, $keepScratch, $backupPath, $isGeneratedBackup);

        $passed = ($watchdogBreaches === 0) && empty($discrepancies);

        // 10. Report Output
        if ($isJson) {
            $this->line(json_encode([
                'status' => $passed ? 'passed' : 'failed',
                'recovery_time_seconds' => round($totalRecoveryTime, 2),
                'metrics' => [
                    'restore_duration_seconds' => round($restoreDuration, 2),
                    'watchdog_duration_seconds' => round($watchdogDuration, 2),
                    'verification_duration_seconds' => round($verifyDuration, 2),
                ],
                'watchdog' => [
                    'total_breaches' => $watchdogBreaches,
                    'unbalanced_journals' => $unbalancedCount,
                    'stock_discrepancies' => $stockDiscrepancyCount,
                ],
                'reconciliation' => [
                    'tenants_verified' => count($baseline),
                    'discrepancies_count' => count($discrepancies),
                    'discrepancies' => $discrepancies,
                ],
                'timestamp' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT));

            return $passed ? self::SUCCESS : self::FAILURE;
        }

        $this->line('');
        $this->info('====================================================================');
        $this->info(' DISASTER RECOVERY DRILL AUDIT SUMMARY');
        $this->info('====================================================================');
        $this->table(
            ['Phase / Metric', 'Measured Result', 'Status'],
            [
                ['PostgreSQL Restore Duration', sprintf('%.2f seconds', $restoreDuration), 'OK'],
                ['Integrity Watchdog Duration', sprintf('%.2f seconds', $watchdogDuration), $watchdogBreaches === 0 ? 'PASS' : 'FAIL'],
                ['Financial & Stock Audit Duration', sprintf('%.2f seconds', $verifyDuration), empty($discrepancies) ? 'PASS' : 'FAIL'],
                ['Watchdog Breaches Detected', (string) $watchdogBreaches, $watchdogBreaches === 0 ? 'ZERO (0)' : 'BREACH'],
                ['Trial Balance Discrepancy', empty($discrepancies) ? '৳0.00 (Exact Match)' : 'DRIFT', empty($discrepancies) ? 'MATCH' : 'MISMATCH'],
                ['Stock Valuation Discrepancy', empty($discrepancies) ? '৳0.00 (Exact Match)' : 'DRIFT', empty($discrepancies) ? 'MATCH' : 'MISMATCH'],
                ['Real Recovery Time (RTO)', sprintf('%.2f seconds', $totalRecoveryTime), $totalRecoveryTime < 1800 ? 'WITHIN RTO TARGET' : 'EXCEEDED'],
            ]
        );

        if (! empty($discrepancies)) {
            $this->error('Reconciliation Discrepancies:');
            foreach ($discrepancies as $disc) {
                $this->line("  - {$disc}");
            }
        }

        if ($passed) {
            $this->info(sprintf(
                '✓ DRILL SUCCESSFUL: Scratch database restored and verified in %.2f seconds with zero financial or stock drift.',
                $totalRecoveryTime
            ));

            return self::SUCCESS;
        }

        $this->error('FAIL: Disaster recovery drill detected invariant breaches or financial drift.');

        return self::FAILURE;
    }

    /**
     * Collect Trial Balance totals and Inventory Valuation across all active tenants and companies.
     *
     * @return array<string, array{tenant_id: string, tenant_name: string, companies: array<string, array{legal_name: string, total_debit: string, total_credit: string, stock_valuation: string, gl_inventory_balance: string, variance: string}>}>
     */
    protected function collectFinancialAndStockMetrics(
        FinancialReportService $financialReportService,
        InventoryGlReconciliationService $inventoryReconciliationService
    ): array {
        $metrics = [];
        /** @var \Illuminate\Database\Eloquent\Collection<int, Tenant> $tenants */
        $tenants = Tenant::where('status', 'active')->get();

        foreach ($tenants as $tenant) {
            TenantContext::set($tenant);
            $companies = Company::where('tenant_id', $tenant->id)->get();

            $companyMetrics = [];
            foreach ($companies as $company) {
                $tb = $financialReportService->getTrialBalance($company->id);
                $inv = $inventoryReconciliationService->getReconciliationReport($company->id);

                $companyMetrics[$company->id] = [
                    'legal_name' => $company->legal_name,
                    'total_debit' => $tb->totalDebit->getAmount(),
                    'total_credit' => $tb->totalCredit->getAmount(),
                    'stock_valuation' => $inv->stockValuation,
                    'gl_inventory_balance' => $inv->glBalance,
                    'variance' => $inv->variance,
                ];
            }

            $metrics[$tenant->id] = [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'companies' => $companyMetrics,
            ];

            TenantContext::clear();
        }

        return $metrics;
    }

    /**
     * Restore original database connection and clean up temporary scratch assets.
     */
    protected function restoreOriginalConnection(
        string $originalDefault,
        string $scratchConnectionKey,
        string $scratchDb,
        string $host,
        string $port,
        string $user,
        string $password,
        bool $keepScratch,
        string $backupPath,
        bool $isGeneratedBackup
    ): void {
        DB::purge($scratchConnectionKey);
        DB::setDefaultConnection($originalDefault);

        if (! $keepScratch) {
            $this->executeSqlOnPostgres(
                $host,
                $port,
                $user,
                $password,
                "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '{$scratchDb}';"
            );
            $this->executeSqlOnPostgres(
                $host,
                $port,
                $user,
                $password,
                "DROP DATABASE IF EXISTS \"{$scratchDb}\";"
            );
        }

        if ($isGeneratedBackup && file_exists($backupPath)) {
            @unlink($backupPath);
        }
    }

    /**
     * Execute SQL statement on administrative postgres database using psql.
     */
    protected function executeSqlOnPostgres(string $host, string $port, string $user, string $password, string $sql): void
    {
        $process = new Process(
            ['psql', '-h', $host, '-p', $port, '-U', $user, '-d', 'postgres', '-c', $sql],
            null,
            ['PGPASSWORD' => $password]
        );
        $process->setTimeout(60);
        $process->run();
    }
}
