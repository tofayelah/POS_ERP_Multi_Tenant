<?php

namespace App\Modules\DataMigration\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TenantOffboardingService
{
    public function __construct(
        protected TenantExportService $exportService
    ) {
    }

    /**
     * Executes the tenant offboarding workflow: archives data, sets retention status,
     * and optionally purges tenant rows in reverse topological order adhering to ON DELETE RESTRICT.
     *
     * @return array{success: bool, tenant_id: string, archive_id: string, purged_records_count: int}
     */
    public function offboard(string $tenantId, bool $purgeImmediately = false): array
    {
        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            throw new RuntimeException("Tenant {$tenantId} does not exist.");
        }

        // 1. Generate full export archive for retention compliance (TEN-06)
        $archive = $this->exportService->export($tenantId);
        $archiveId = (string) Str::uuid();

        // 2. Mark tenant status offboarded
        $tenant->update(['status' => 'offboarded']);

        $purgedCount = 0;

        if ($purgeImmediately) {
            $purgedCount = DB::connection('pgsql_owner')->transaction(function () use ($tenantId) {
                // Set context
                DB::connection('pgsql_owner')->statement(
                    "SELECT set_config('app.current_tenant_id', :id, true)",
                    ['id' => $tenantId]
                );

                // Get tables in topological order, then reverse them so children are deleted before parents
                $topologicalTables = $this->exportService->getTopologicalTenantTables();
                $reverseTables = array_reverse($topologicalTables);

                $deletedTotal = 0;

                // Temporarily disable audit immutability trigger for the offboarding purge
                DB::connection('pgsql_owner')->statement('ALTER TABLE audit_logs DISABLE TRIGGER trg_audit_logs_immutable');

                try {
                    foreach ($reverseTables as $table) {
                        $deleted = DB::connection('pgsql_owner')
                            ->table($table)
                            ->where('tenant_id', $tenantId)
                            ->delete();

                        $deletedTotal += $deleted;
                    }
                } finally {
                    DB::connection('pgsql_owner')->statement('ALTER TABLE audit_logs ENABLE TRIGGER trg_audit_logs_immutable');
                }

                // Delete tenant memberships, subscriptions and entitlements
                DB::connection('pgsql_owner')
                    ->table('tenant_memberships')
                    ->where('tenant_id', $tenantId)
                    ->delete();

                DB::connection('pgsql_owner')
                    ->table('tenant_module_entitlements')
                    ->where('tenant_id', $tenantId)
                    ->delete();

                DB::connection('pgsql_owner')
                    ->table('tenant_subscriptions')
                    ->where('tenant_id', $tenantId)
                    ->delete();

                // Finally delete the tenant row
                DB::connection('pgsql_owner')
                    ->table('tenants')
                    ->where('id', $tenantId)
                    ->delete();

                return $deletedTotal;
            });
        }

        return [
            'success' => true,
            'tenant_id' => $tenantId,
            'archive_id' => $archiveId,
            'purged_records_count' => $purgedCount,
        ];
    }
}
