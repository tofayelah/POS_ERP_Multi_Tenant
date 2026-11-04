<?php

namespace App\Core\Watchdog\Services;

use App\Core\Tenancy\TenantContext;
use App\Core\Watchdog\DTOs\TenantWatchdogResult;
use App\Core\Watchdog\DTOs\WatchdogReport;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IntegrityWatchdogService
{
    /**
     * Inspect all active tenants sequentially, ensuring transactional context isolation per tenant.
     */
    public function checkAllTenants(): WatchdogReport
    {
        $report = new WatchdogReport;

        /** @var Tenant[] $activeTenants */
        $activeTenants = Tenant::query()
            ->where('status', 'active')
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($activeTenants as $tenant) {
            $result = $this->checkTenant($tenant);
            $report->addTenantResult($result);
        }

        return $report;
    }

    /**
     * Inspect a specific tenant within an isolated transaction using set_config(..., true).
     */
    public function checkTenant(Tenant|string $tenant): TenantWatchdogResult
    {
        $tenantModel = is_string($tenant)
            ? Tenant::query()->findOrFail($tenant)
            : $tenant;

        $tenantId = (string) $tenantModel->id;

        return DB::transaction(function () use ($tenantId, $tenantModel) {
            // Rule 4: Use set_config inside transaction with true (local to transaction). Never SET with bind params.
            DB::statement("SELECT set_config('app.current_tenant_id', ?, true)", [$tenantId]);

            // Set Laravel in-memory TenantContext to match
            TenantContext::set($tenantModel);

            try {
                return $this->auditCurrentTenantContext($tenantModel);
            } finally {
                TenantContext::clear();
            }
        });
    }

    /**
     * Run all integrity checks against the currently configured session tenant context.
     * Fails loudly if tenant context is uninitialized or empty (NEW-30).
     */
    public function auditCurrentTenantContext(?Tenant $tenant = null): TenantWatchdogResult
    {
        // 1. Verify PostgreSQL session tenant context
        /** @var object{ctx: string|null}|null $setting */
        $setting = DB::selectOne("SELECT current_setting('app.current_tenant_id', true) as ctx");
        $sessionTenantId = $setting?->ctx;

        $memoryTenantId = TenantContext::id();

        $activeTenantId = $sessionTenantId ?: $memoryTenantId;

        if (empty($activeTenantId)) {
            throw new RuntimeException('Tenant context is empty; watchdog cannot proceed.');
        }

        $tenantName = $tenant ? $tenant->name : (Tenant::where('id', $activeTenantId)->value('name') ?? 'Tenant '.$activeTenantId);

        // 2. Check: Posted journals balance (debits == credits per posted entry)
        $unbalancedJournals = $this->findUnbalancedJournals($activeTenantId);

        // 3. Check: Stock balances equal their movements
        $stockDiscrepancies = $this->findStockDiscrepancies($activeTenantId);

        // 4. Check: Negative stock has an active ADR-016 exception (orphan negative stock is an invariant breach)
        $orphanNegativeStocks = $this->findOrphanNegativeStocks($activeTenantId);

        return new TenantWatchdogResult(
            tenantId: $activeTenantId,
            tenantName: $tenantName,
            unbalancedJournals: $unbalancedJournals,
            stockDiscrepancies: $stockDiscrepancies,
            orphanNegativeStocks: $orphanNegativeStocks,
        );
    }

    /**
     * Find posted journal entries whose debits do not equal credits.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function findUnbalancedJournals(string $tenantId): array
    {
        $rows = DB::select('
            SELECT e.id AS journal_entry_id,
                   e.entry_number,
                   e.company_id,
                   COALESCE(SUM(l.debit_amount), 0) AS total_debits,
                   COALESCE(SUM(l.credit_amount), 0) AS total_credits
            FROM journal_entries e
            JOIN journal_lines l ON l.journal_entry_id = e.id AND l.tenant_id = e.tenant_id
            WHERE e.is_posted = true
              AND e.tenant_id = ?
            GROUP BY e.id, e.entry_number, e.company_id
            HAVING COALESCE(SUM(l.debit_amount), 0) <> COALESCE(SUM(l.credit_amount), 0)
        ', [$tenantId]);

        return array_map(fn ($r) => (array) $r, $rows);
    }

    /**
     * Find stock balances that do not equal the sum of their stock movements.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function findStockDiscrepancies(string $tenantId): array
    {
        $rows = DB::select('
            SELECT b.id AS stock_balance_id,
                   b.warehouse_id,
                   b.product_variant_id,
                   b.quantity_on_hand,
                   COALESCE(SUM(m.quantity), 0) AS total_movements
            FROM stock_balances b
            LEFT JOIN stock_movements m ON m.tenant_id = b.tenant_id
                                       AND m.warehouse_id = b.warehouse_id
                                       AND m.product_variant_id = b.product_variant_id
            WHERE b.tenant_id = ?
            GROUP BY b.id, b.warehouse_id, b.product_variant_id, b.quantity_on_hand
            HAVING b.quantity_on_hand <> COALESCE(SUM(m.quantity), 0)
        ', [$tenantId]);

        return array_map(fn ($r) => (array) $r, $rows);
    }

    /**
     * Find negative stock rows that lack an approved OPEN record in stock_oversell_exceptions (ADR-016).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function findOrphanNegativeStocks(string $tenantId): array
    {
        $rows = DB::select("
            SELECT b.id AS stock_balance_id,
                   b.warehouse_id,
                   b.product_variant_id,
                   b.quantity_on_hand
            FROM stock_balances b
            WHERE b.tenant_id = ?
              AND b.quantity_on_hand < 0
              AND NOT EXISTS (
                  SELECT 1
                  FROM stock_oversell_exceptions x
                  WHERE x.tenant_id = b.tenant_id
                    AND x.warehouse_id = b.warehouse_id
                    AND x.product_variant_id = b.product_variant_id
                    AND x.status = 'OPEN'
              )
        ", [$tenantId]);

        return array_map(fn ($r) => (array) $r, $rows);
    }
}
