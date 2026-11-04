<?php

namespace App\Console\Commands;

use App\Core\Watchdog\DTOs\TenantWatchdogResult;
use App\Core\Watchdog\Services\IntegrityWatchdogService;
use App\Models\Tenant;
use Illuminate\Console\Command;

class CheckIntegrityWatchdogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'integrity:check-books {--tenant= : Optional specific tenant ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit financial and inventory invariants across active tenants (NEW-30)';

    /**
     * Execute the console command.
     */
    public function handle(IntegrityWatchdogService $watchdog): int
    {
        $tenantId = $this->option('tenant');

        $this->info('Starting Business Integrity Watchdog inspection...');

        if ($tenantId) {
            /** @var Tenant $tenant */
            $tenant = Tenant::query()->findOrFail((string) $tenantId);
            $result = $watchdog->checkTenant($tenant);

            if ($result->hasBreaches()) {
                $this->error("Integrity breaches detected for tenant [{$tenant->name}]:");
                $this->renderBreaches($result);

                return Command::FAILURE;
            }

            $this->info("✓ Tenant [{$tenant->name}] passed all integrity invariant checks.");

            return Command::SUCCESS;
        }

        $report = $watchdog->checkAllTenants();

        if ($report->hasAnyBreach()) {
            $this->error("Integrity watchdog detected {$report->totalBreaches()} breaches across active tenants!");
            foreach ($report->results as $result) {
                if ($result->hasBreaches()) {
                    $this->renderBreaches($result);
                }
            }

            return Command::FAILURE;
        }

        $this->info('✓ Integrity Watchdog PASSED: All active tenants satisfy GL balance and inventory movement invariants.');

        return Command::SUCCESS;
    }

    protected function renderBreaches(TenantWatchdogResult $result): void
    {
        $this->warn("--- Breaches for {$result->tenantName} ({$result->tenantId}) ---");

        if (! empty($result->unbalancedJournals)) {
            $this->error('Unbalanced Posted Journals:');
            $this->table(['Journal ID', 'Entry Number', 'Debits', 'Credits'], array_map(fn ($j) => [
                $j['journal_entry_id'],
                $j['entry_number'],
                $j['total_debits'],
                $j['total_credits'],
            ], $result->unbalancedJournals));
        }

        if (! empty($result->stockDiscrepancies)) {
            $this->error('Stock Balance vs Movement Ledger Discrepancies:');
            $this->table(['Warehouse ID', 'Variant ID', 'On Hand', 'Movements Sum'], array_map(fn ($s) => [
                $s['warehouse_id'],
                $s['product_variant_id'],
                $s['quantity_on_hand'],
                $s['total_movements'],
            ], $result->stockDiscrepancies));
        }

        if (! empty($result->orphanNegativeStocks)) {
            $this->error('Orphan Negative Stock without ADR-016 Exception:');
            $this->table(['Warehouse ID', 'Variant ID', 'On Hand'], array_map(fn ($o) => [
                $o['warehouse_id'],
                $o['product_variant_id'],
                $o['quantity_on_hand'],
            ], $result->orphanNegativeStocks));
        }
    }
}
