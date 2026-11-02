<?php

namespace App\Modules\DataMigration\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TenantRestoreService
{
    public function __construct(
        protected TenantExportService $exportService
    ) {
    }

    /**
     * Restores an exported tenant archive into a target tenant database context.
     *
     * @param  array<string, mixed>|string  $archiveDataOrPath
     * @return array<string, mixed>
     */
    public function restore(array|string $archiveDataOrPath, string $targetTenantId): array
    {
        $archive = is_string($archiveDataOrPath)
            ? (file_exists($archiveDataOrPath)
                ? json_decode((string) file_get_contents($archiveDataOrPath), true, 512, JSON_THROW_ON_ERROR)
                : json_decode($archiveDataOrPath, true, 512, JSON_THROW_ON_ERROR))
            : $archiveDataOrPath;

        if (! is_array($archive) || ! isset($archive['metadata'], $archive['tables'])) {
            throw new RuntimeException('Invalid tenant archive structure.');
        }

        $targetTenant = Tenant::find($targetTenantId);
        if (! $targetTenant) {
            throw new RuntimeException("Target tenant {$targetTenantId} does not exist.");
        }

        // Execute restore inside transaction as pgsql_owner to allow initial restore insertion
        return DB::connection('pgsql_owner')->transaction(function () use ($archive, $targetTenantId) {
            // Set session context for the target tenant
            DB::connection('pgsql_owner')->statement(
                "SELECT set_config('app.current_tenant_id', :id, true)",
                ['id' => $targetTenantId]
            );

            // Get topological order of tables
            $orderedTables = $this->exportService->getTopologicalTenantTables();

            $tablesInArchive = $archive['tables'];
            $restoredTableCount = 0;
            $postedJournalEntryIds = [];

            foreach ($orderedTables as $table) {
                if (! isset($tablesInArchive[$table]) || empty($tablesInArchive[$table])) {
                    continue;
                }

                $rows = $tablesInArchive[$table];

                // Remap tenant_id for every row
                $batch = array_map(function ($row) use ($targetTenantId, $table, &$postedJournalEntryIds) {
                    $row['tenant_id'] = $targetTenantId;

                    if ($table === 'journal_entries') {
                        if (! empty($row['is_posted'])) {
                            $postedJournalEntryIds[] = $row['id'];
                            $row['is_posted'] = false;
                        }
                    }

                    return $row;
                }, $rows);

                // Insert into target table
                DB::connection('pgsql_owner')->table($table)->insert($batch);
                $restoredTableCount++;
            }

            // Transition posted journal entries back to is_posted = true now that lines exist
            if (! empty($postedJournalEntryIds)) {
                DB::connection('pgsql_owner')
                    ->table('journal_entries')
                    ->where('tenant_id', $targetTenantId)
                    ->whereIn('id', $postedJournalEntryIds)
                    ->update(['is_posted' => true]);
            }

            // Recalculate financial and inventory totals in target tenant
            $restoredDebits = DB::connection('pgsql_owner')
                ->table('journal_lines')
                ->where('tenant_id', $targetTenantId)
                ->sum('debit_amount');
            $restoredCredits = DB::connection('pgsql_owner')
                ->table('journal_lines')
                ->where('tenant_id', $targetTenantId)
                ->sum('credit_amount');

            $expectedDebits = $archive['metadata']['trial_balance']['total_debits'] ?? '0.00';
            $expectedCredits = $archive['metadata']['trial_balance']['total_credits'] ?? '0.00';

            $formattedDebits = number_format((float) $restoredDebits, 2, '.', '');
            $formattedCredits = number_format((float) $restoredCredits, 2, '.', '');

            if ($formattedDebits !== $expectedDebits || $formattedCredits !== $expectedCredits) {
                throw new RuntimeException(
                    "Trial balance verification failed during restore: expected Dr {$expectedDebits} / Cr {$expectedCredits}, got Dr {$formattedDebits} / Cr {$formattedCredits}"
                );
            }

            $restoredStock = DB::connection('pgsql_owner')
                ->table('stock_balances')
                ->where('tenant_id', $targetTenantId)
                ->sum('quantity_on_hand');
            $formattedStock = number_format((float) $restoredStock, 3, '.', '');

            return [
                'success' => true,
                'target_tenant_id' => $targetTenantId,
                'restored_tables' => $restoredTableCount,
                'trial_balance' => [
                    'total_debits' => $formattedDebits,
                    'total_credits' => $formattedCredits,
                ],
                'stock_summary' => [
                    'total_quantity' => $formattedStock,
                ],
            ];
        });
    }
}
