<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Audit\Services\VerifyAuditHashChainService;
use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Tenant;
use Brick\Math\BigDecimal;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class PilotDailyCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pilot:daily-check
                            {tenant? : Optional tenant ID, slug, or name}
                            {--connection=reporting_ro : Database connection to run read-only reporting checks against}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform comprehensive daily pilot invariant audit on reporting_ro connection per branch';

    public function handle(VerifyAuditHashChainService $verifyAuditHashChainService): int
    {
        $tenantArg = $this->argument('tenant');
        $connection = (string) $this->option('connection');

        $this->info('====================================================================');
        $this->info(" Daily Pilot Operational & Statutory Integrity Audit ({$connection})");
        $this->info('====================================================================');

        // 1. Resolve Tenant(s)
        if (! empty($tenantArg)) {
            $tenantArgStr = (string) $tenantArg;
            /** @var Tenant|null $tenant */
            $tenant = Tenant::query()
                ->where('id', $tenantArgStr)
                ->orWhere('name', $tenantArgStr)
                ->first();

            if (! $tenant) {
                $this->error("Tenant not found matching identifier: [{$tenantArgStr}]");

                return self::FAILURE;
            }

            $tenants = collect([$tenant]);
        } else {
            /** @var Collection<int, Tenant> $tenants */
            $tenants = Tenant::where('status', 'active')->get();
        }

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found for daily inspection.');

            return self::SUCCESS;
        }

        $allDiscrepancies = [];
        $originalConnection = DB::getDefaultConnection();

        try {
            DB::setDefaultConnection($connection);

            foreach ($tenants as $tenant) {
                $this->line("--> Inspecting Tenant: <comment>{$tenant->name}</comment> ({$tenant->id})");

                // Set tenant context on the reporting connection (Rule 4: SELECT set_config per request)
                DB::connection($connection)->statement(
                    "SELECT set_config('app.current_tenant_id', :id, false)",
                    ['id' => (string) $tenant->id]
                );
                TenantContext::set($tenant, $connection);

                try {
                    $tenantDiscrepancies = $this->auditTenant(
                        $tenant,
                        $connection,
                        $verifyAuditHashChainService
                    );

                    foreach ($tenantDiscrepancies as $disc) {
                        $allDiscrepancies[] = array_merge(['tenant' => $tenant->name], $disc);
                    }
                } finally {
                    TenantContext::clear($connection);
                }
            }
        } catch (Throwable $e) {
            $this->error('Daily check encountered unexpected error: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            DB::setDefaultConnection($originalConnection);
        }

        $this->line('');

        // 2. Render Results
        if (empty($allDiscrepancies)) {
            $this->info('====================================================================');
            $this->info(' ✓ ALL CHECKS PASSED: Zero discrepancies found across all branches.');
            $this->info('   - POS Cash vs GL Cash: Reconciled to ৳0.00');
            $this->info('   - Stock Balances vs Movements: 100% Parity');
            $this->info('   - Mushak 6.2 VAT vs GL VAT: Reconciled to ৳0.00');
            $this->info('   - Terminal Offline Sync & Fiscal Signatures: Clean');
            $this->info('   - Open Stock Oversell Exceptions: None');
            $this->info('   - Cryptographic Audit Hash Chains: Verified Intact');
            $this->info('====================================================================');

            return self::SUCCESS;
        }

        $this->error('====================================================================');
        $this->error(sprintf(' FAIL: Found %d operational or statutory discrepancy issue(s):', count($allDiscrepancies)));
        $this->error('====================================================================');

        $this->table(
            ['Tenant', 'Branch', 'Audit Category', 'Discrepancy Details'],
            array_map(fn (array $d): array => [
                $d['tenant'],
                $d['branch'],
                $d['category'],
                $d['detail'],
            ], $allDiscrepancies)
        );

        return self::FAILURE;
    }

    /**
     * Audit operational invariants for a single tenant across its branches.
     *
     * @return array<int, array{branch: string, category: string, detail: string}>
     */
    protected function auditTenant(
        Tenant $tenant,
        string $connection,
        VerifyAuditHashChainService $verifyAuditHashChainService
    ): array {
        $discrepancies = [];
        $db = DB::connection($connection);

        // Fetch all branches for this tenant
        $branches = Branch::where('tenant_id', $tenant->id)->get();

        if ($branches->isEmpty()) {
            // Fallback virtual branch for single-store / initial configuration
            $virtualBranch = new Branch([
                'id' => '00000000-0000-0000-0000-000000000000',
                'name' => 'Main Outlet / Store',
                'code' => 'MAIN',
                'tenant_id' => $tenant->id,
            ]);
            $branches = collect([$virtualBranch]);
        }

        foreach ($branches as $branch) {
            $branchId = (string) $branch->id;
            $branchName = $branch->name;

            // -----------------------------------------------------------------
            // 1. POS Cash Counted against the GL Cash Account (1010)
            // -----------------------------------------------------------------
            $posCashCountedRaw = $db->table('pos_shifts')
                ->where('tenant_id', $tenant->id)
                ->when($branchId !== '00000000-0000-0000-0000-000000000000', fn ($q) => $q->where('branch_id', $branchId))
                ->whereNotNull('closing_cash')
                ->sum('closing_cash');

            $glCashAccount = $db->table('chart_of_accounts')
                ->where('tenant_id', $tenant->id)
                ->where('account_code', '1010')
                ->first();

            $glCashNet = '0.0000';
            if ($glCashAccount) {
                $glLineNet = $db->table('journal_lines')
                    ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                    ->where('journal_entries.tenant_id', $tenant->id)
                    ->where('journal_entries.is_posted', true)
                    ->where('journal_lines.account_id', $glCashAccount->id)
                    ->when($branchId !== '00000000-0000-0000-0000-000000000000', fn ($q) => $q->where('journal_lines.branch_id', $branchId))
                    ->selectRaw('COALESCE(SUM(journal_lines.debit_amount - journal_lines.credit_amount), 0) as net')
                    ->value('net');

                $glCashNet = (string) ($glLineNet ?? '0.0000');
            }

            $posCashDec = BigDecimal::of((string) $posCashCountedRaw);
            $glCashDec = BigDecimal::of($glCashNet);
            $cashVariance = $posCashDec->minus($glCashDec);

            if (! $cashVariance->isZero()) {
                $discrepancies[] = [
                    'branch' => $branchName,
                    'category' => 'POS Cash vs GL (1010)',
                    'detail' => sprintf(
                        'POS Counted: ৳%s | GL Cash: ৳%s | Variance: ৳%s',
                        (string) $posCashDec->toScale(2),
                        (string) $glCashDec->toScale(2),
                        (string) $cashVariance->toScale(2)
                    ),
                ];
            }

            // -----------------------------------------------------------------
            // 2. Stock Balances against the Sum of Movements
            // -----------------------------------------------------------------
            $warehouseIds = $db->table('warehouses')
                ->where('tenant_id', $tenant->id)
                ->when($branchId !== '00000000-0000-0000-0000-000000000000', fn ($q) => $q->where('branch_id', $branchId))
                ->pluck('id');

            if ($warehouseIds->isNotEmpty()) {
                $stockMismatches = $db->table('stock_balances as sb')
                    ->leftJoin('stock_movements as sm', function ($join) {
                        $join->on('sb.tenant_id', '=', 'sm.tenant_id')
                            ->on('sb.warehouse_id', '=', 'sm.warehouse_id')
                            ->on('sb.product_variant_id', '=', 'sm.product_variant_id');
                    })
                    ->where('sb.tenant_id', $tenant->id)
                    ->whereIn('sb.warehouse_id', $warehouseIds)
                    ->groupBy('sb.warehouse_id', 'sb.product_variant_id', 'sb.quantity_on_hand')
                    ->havingRaw('sb.quantity_on_hand != COALESCE(SUM(sm.quantity), 0)')
                    ->select(
                        'sb.warehouse_id',
                        'sb.product_variant_id',
                        'sb.quantity_on_hand',
                        DB::raw('COALESCE(SUM(sm.quantity), 0) as total_movements')
                    )
                    ->get();

                foreach ($stockMismatches as $sm) {
                    $discrepancies[] = [
                        'branch' => $branchName,
                        'category' => 'Stock Ledger Parity',
                        'detail' => sprintf(
                            'Variant [%s] in Warehouse [%s]: On-Hand = %s, Movements Sum = %s',
                            $sm->product_variant_id,
                            $sm->warehouse_id,
                            $sm->quantity_on_hand,
                            $sm->total_movements
                        ),
                    ];
                }
            }

            // -----------------------------------------------------------------
            // 3. Mushak 6.2 VAT Total against the GL VAT Payable Account (2020)
            // -----------------------------------------------------------------
            $registerVatRaw = $db->table('sales_invoices')
                ->where('tenant_id', $tenant->id)
                ->when($branchId !== '00000000-0000-0000-0000-000000000000', fn ($q) => $q->where('branch_id', $branchId))
                ->where('status', 'issued')
                ->sum('tax_amount');

            // Less Credit Note VAT (reversals)
            $creditNoteVatRaw = $db->table('credit_notes')
                ->where('tenant_id', $tenant->id)
                ->when($branchId !== '00000000-0000-0000-0000-000000000000', fn ($q) => $q->where('branch_id', $branchId))
                ->where('status', 'issued')
                ->sum('tax_amount');

            $netRegisterVatDec = BigDecimal::of((string) $registerVatRaw)
                ->minus(BigDecimal::of((string) $creditNoteVatRaw));

            $vatAccount = $db->table('chart_of_accounts')
                ->where('tenant_id', $tenant->id)
                ->where('account_code', '2020')
                ->first();

            $glVatNet = '0.0000';
            if ($vatAccount) {
                $glLineNet = $db->table('journal_lines')
                    ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                    ->where('journal_entries.tenant_id', $tenant->id)
                    ->where('journal_entries.is_posted', true)
                    ->where('journal_lines.account_id', $vatAccount->id)
                    ->when($branchId !== '00000000-0000-0000-0000-000000000000', fn ($q) => $q->where('journal_lines.branch_id', $branchId))
                    ->selectRaw('COALESCE(SUM(journal_lines.credit_amount - journal_lines.debit_amount), 0) as net')
                    ->value('net');

                $glVatNet = (string) ($glLineNet ?? '0.0000');
            }

            $glVatDec = BigDecimal::of($glVatNet);
            $vatVariance = $netRegisterVatDec->minus($glVatDec);

            if (! $vatVariance->isZero()) {
                $discrepancies[] = [
                    'branch' => $branchName,
                    'category' => 'Mushak 6.2 vs GL VAT (2020)',
                    'detail' => sprintf(
                        'Mushak 6.2 Net VAT: ৳%s | GL VAT Payable: ৳%s | Variance: ৳%s',
                        (string) $netRegisterVatDec->toScale(2),
                        (string) $glVatDec->toScale(2),
                        (string) $vatVariance->toScale(2)
                    ),
                ];
            }

            // -----------------------------------------------------------------
            // 4. Unsynced Sales (> 4 Hours) & Unfiscalized Invoices per Terminal
            // -----------------------------------------------------------------
            $terminals = $db->table('pos_terminals')
                ->where('tenant_id', $tenant->id)
                ->when($branchId !== '00000000-0000-0000-0000-000000000000', fn ($q) => $q->where('branch_id', $branchId))
                ->get();

            $fourHoursAgo = now()->subHours(4);

            foreach ($terminals as $terminal) {
                // Unsynced sales older than 4 hours:
                // a) draft sales older than 4 hours
                // b) offline sync delays where server_received_time > device_time + 4 hours
                $unsyncedCount = $db->table('sales_invoices')
                    ->where('tenant_id', $tenant->id)
                    ->where('terminal_id', $terminal->id)
                    ->where(function ($q) use ($fourHoursAgo) {
                        $q->where(function ($q1) use ($fourHoursAgo) {
                            $q1->where('status', 'draft')
                                ->where('created_at', '<', $fourHoursAgo);
                        })->orWhere(function ($q2) {
                            $q2->where('is_synced_from_offline', true)
                                ->whereRaw("server_received_time > device_time + INTERVAL '4 hours'");
                        });
                    })
                    ->count();

                if ($unsyncedCount > 0) {
                    $discrepancies[] = [
                        'branch' => $branchName,
                        'category' => 'Unsynced Sales (>4h)',
                        'detail' => sprintf('Terminal [%s] (%s): %d sale(s) unsynced for > 4 hours', $terminal->name, $terminal->terminal_code, $unsyncedCount),
                    ];
                }

                // Unfiscalized issued invoices for this terminal:
                // Issued sales invoices missing SDC fiscal signature or SDC invoice number
                $unfiscalizedCount = $db->table('sales_invoices')
                    ->where('tenant_id', $tenant->id)
                    ->where('terminal_id', $terminal->id)
                    ->where('status', 'issued')
                    ->where(function ($q) {
                        $q->whereNull('fiscal_signature')
                            ->orWhere('fiscal_signature', '')
                            ->orWhereNull('sdc_invoice_number');
                    })
                    ->count();

                if ($unfiscalizedCount > 0) {
                    $discrepancies[] = [
                        'branch' => $branchName,
                        'category' => 'Unfiscalized Invoices',
                        'detail' => sprintf('Terminal [%s] (%s): %d issued invoice(s) missing SDC fiscal signature', $terminal->name, $terminal->terminal_code, $unfiscalizedCount),
                    ];
                }
            }

            // -----------------------------------------------------------------
            // 5. Open Stock Oversell Exceptions (ADR-016)
            // -----------------------------------------------------------------
            $openExceptions = $db->table('stock_oversell_exceptions')
                ->where('tenant_id', $tenant->id)
                ->where('status', 'OPEN')
                ->when($branchId !== '00000000-0000-0000-0000-000000000000', function ($q) use ($branchId, $warehouseIds) {
                    $q->where('branch_id', $branchId)
                        ->orWhereIn('warehouse_id', $warehouseIds);
                })
                ->get();

            if ($openExceptions->isNotEmpty()) {
                foreach ($openExceptions as $exc) {
                    $discrepancies[] = [
                        'branch' => $branchName,
                        'category' => 'Open Oversell Exception',
                        'detail' => sprintf(
                            'Exception ID: %s | Variant: %s | Qty Oversold: %s | Status: %s',
                            $exc->id,
                            $exc->product_variant_id,
                            $exc->quantity_oversold,
                            $exc->status
                        ),
                    ];
                }
            }
        }

        // ---------------------------------------------------------------------
        // 6. Cryptographic Audit-Chain Verification (Tenant-wide)
        // ---------------------------------------------------------------------
        $auditResult = $verifyAuditHashChainService->verifyTenant((string) $tenant->id);
        if (! $auditResult->isValid) {
            $discrepancies[] = [
                'branch' => 'Tenant-wide',
                'category' => 'Audit Chain Tampering',
                'detail' => sprintf(
                    'Cryptographic hash chain broken! Found %d tampered record(s). Reason: %s',
                    count($auditResult->tamperedRecords),
                    $auditResult->tamperedRecords[0]['reason'] ?? 'Checksum or sequence failure'
                ),
            ];
        }

        return $discrepancies;
    }
}
