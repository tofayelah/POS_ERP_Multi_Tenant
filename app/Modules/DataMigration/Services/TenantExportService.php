<?php

namespace App\Modules\DataMigration\Services;

use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TenantExportService
{
    /**
     * Tables exempt from single-tenant data export/import/purge:
     * - tenant_memberships, roles, model_has_roles, model_has_permissions
     *
     * @var array<int, string>
     */
    protected array $exemptTables = [
        'tenant_memberships',
        'roles',
        'model_has_roles',
        'model_has_permissions',
    ];

    /**
     * Exports a tenant's complete database records into a portable, verified JSON structure.
     *
     * @return array<string, mixed>
     */
    public function export(string $tenantId, ?string $outputPath = null): array
    {
        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            throw new RuntimeException("Tenant with ID {$tenantId} does not exist.");
        }

        // Set tenant context for app_rw session
        DB::statement("SELECT set_config('app.current_tenant_id', :id, false)", ['id' => $tenantId]);

        // 1. Calculate financial and inventory validation checksums
        $debits = DB::table('journal_lines')
            ->where('tenant_id', $tenantId)
            ->sum('debit_amount');
        $credits = DB::table('journal_lines')
            ->where('tenant_id', $tenantId)
            ->sum('credit_amount');

        $totalDebits = number_format((float) $debits, 2, '.', '');
        $totalCredits = number_format((float) $credits, 2, '.', '');

        $stockQty = DB::table('stock_balances')
            ->where('tenant_id', $tenantId)
            ->sum('quantity_on_hand');
        $totalStockQty = number_format((float) $stockQty, 3, '.', '');

        // 2. Fetch all tenant tables in topological dependency order
        $orderedTables = $this->getTopologicalTenantTables();

        $tablesData = [];
        $totalRecords = 0;

        foreach ($orderedTables as $table) {
            $query = DB::table($table)->where('tenant_id', $tenantId);

            $hasId = DB::selectOne("
                SELECT 1 FROM information_schema.columns 
                WHERE table_schema = 'public' AND table_name = :tbl AND column_name = 'id'
            ", ['tbl' => $table]);

            if ($hasId) {
                $query->orderBy('id', 'asc');
            }

            $rows = $query->get()->map(fn ($row) => (array) $row)->toArray();

            if (! empty($rows)) {
                $tablesData[$table] = $rows;
                $totalRecords += count($rows);
            }
        }

        $archive = [
            'version' => '1.0',
            'exported_at' => Carbon::now()->toIso8601String(),
            'metadata' => [
                'tenant_id' => $tenantId,
                'tenant_name' => $tenant->name,
                'subdomain' => $tenant->subdomain,
                'trial_balance' => [
                    'total_debits' => $totalDebits,
                    'total_credits' => $totalCredits,
                ],
                'stock_summary' => [
                    'total_quantity' => $totalStockQty,
                ],
                'tables_count' => count($tablesData),
                'total_records' => $totalRecords,
            ],
            'tables' => $tablesData,
        ];

        if ($outputPath !== null) {
            file_put_contents($outputPath, json_encode($archive, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }

        return $archive;
    }

    /**
     * Computes the topological sort of tenant tables based on foreign key constraints.
     * Tables with no foreign dependencies appear first; dependent tables appear later.
     *
     * @return array<int, string>
     */
    public function getTopologicalTenantTables(): array
    {
        $tenantColumns = DB::select("
            SELECT table_name 
            FROM information_schema.columns 
            WHERE table_schema = 'public' AND column_name = 'tenant_id'
            GROUP BY table_name
        ");

        /** @var array<string, bool> $allTenantTables */
        $allTenantTables = [];
        foreach ($tenantColumns as $col) {
            $tableName = (string) $col->table_name;
            if (! in_array($tableName, $this->exemptTables, true)) {
                $allTenantTables[$tableName] = true;
            }
        }

        // Fetch foreign key relationships directly from pg_constraint catalog
        $fkRows = DB::select("
            SELECT 
                c.relname AS dependent_table,
                conf.relname AS referenced_table
            FROM pg_constraint con
            JOIN pg_class c ON c.oid = con.conrelid
            JOIN pg_class conf ON conf.oid = con.confrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE con.contype = 'f'
                AND n.nspname = 'public'
                AND con.conrelid != con.confrelid
        ");

        /** @var array<string, array<int, string>> $dependencies */
        $dependencies = [];
        /** @var array<string, int> $inDegree */
        $inDegree = [];

        foreach (array_keys($allTenantTables) as $table) {
            $dependencies[$table] = [];
            $inDegree[$table] = 0;
        }

        foreach ($fkRows as $row) {
            $dep = (string) $row->dependent_table;
            $ref = (string) $row->referenced_table;

            // Only track dependencies where both tables are tenant-scoped
            if (isset($allTenantTables[$dep], $allTenantTables[$ref])) {
                $dependencies[$ref][] = $dep;
                $inDegree[$dep]++;
            }
        }

        // Kahn's algorithm for topological sorting
        $queue = [];
        foreach ($inDegree as $table => $degree) {
            if ($degree === 0) {
                $queue[] = $table;
            }
        }

        $sorted = [];
        while (! empty($queue)) {
            $current = array_shift($queue);
            $sorted[] = $current;

            if (isset($dependencies[$current])) {
                foreach ($dependencies[$current] as $neighbor) {
                    $inDegree[$neighbor]--;
                    if ($inDegree[$neighbor] === 0) {
                        $queue[] = $neighbor;
                    }
                }
            }
        }

        // If cycles exist or isolated tables remain, append remaining
        foreach (array_keys($allTenantTables) as $table) {
            if (! in_array($table, $sorted, true)) {
                $sorted[] = $table;
            }
        }

        return $sorted;
    }
}
