<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckRlsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:check-rls {--connection=pgsql_owner : Database connection to inspect catalog}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify that every tenant-scoped table enforces forced Row-Level Security with active policies for app_rw and reporting_ro';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = (string) $this->option('connection');
        $db = DB::connection($connection);

        $this->info("Checking Row-Level Security configuration on connection [{$connection}]...");

        $errors = [];

        // 1. Verify app_rw does not bypass RLS
        $appRwRole = $db->selectOne("SELECT rolname, rolbypassrls FROM pg_roles WHERE rolname = 'app_rw'");
        if (! $appRwRole) {
            $errors[] = ['Role', 'app_rw', 'Role app_rw does not exist in pg_roles'];
        } elseif ($appRwRole->rolbypassrls) {
            $errors[] = ['Role', 'app_rw', 'Role app_rw has rolbypassrls = true (FORBIDDEN)'];
        }

        // 2. Fetch role OIDs for mapping
        $roles = $db->select('SELECT oid, rolname FROM pg_roles');
        $roleMap = [];
        foreach ($roles as $r) {
            $roleMap[(int) $r->oid] = $r->rolname;
        }

        // 3. Find all tables with a tenant_id column in public schema
        $tenantTables = $db->select("
            SELECT table_name
            FROM information_schema.columns
            WHERE table_schema = 'public' AND column_name = 'tenant_id'
            ORDER BY table_name
        ");

        if (empty($tenantTables)) {
            $this->warn('No tables with tenant_id column found in public schema.');
        }

        foreach ($tenantTables as $row) {
            $table = $row->table_name;

            // Check pg_class for relrowsecurity and relforcerowsecurity
            $pgClass = $db->selectOne("
                SELECT c.relname, c.relrowsecurity, c.relforcerowsecurity
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'public' AND c.relname = :table
            ", ['table' => $table]);

            if (! $pgClass) {
                $errors[] = ['Table', $table, 'Table not found in pg_class catalog'];

                continue;
            }

            if (! $pgClass->relrowsecurity) {
                $errors[] = ['Table', $table, 'ROW LEVEL SECURITY is not enabled (relrowsecurity = false)'];
            }

            if (! $pgClass->relforcerowsecurity) {
                $errors[] = ['Table', $table, 'FORCE ROW LEVEL SECURITY is not enabled (relforcerowsecurity = false)'];
            }

            // Check pg_policy for policies covering app_rw and reporting_ro
            $policies = $db->select("
                SELECT pol.polname, pol.polroles::text as roles_str
                FROM pg_policy pol
                JOIN pg_class c ON c.oid = pol.polrelid
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'public' AND c.relname = :table
            ", ['table' => $table]);

            if (empty($policies)) {
                $errors[] = ['Policy', $table, 'No RLS policies defined on table'];

                continue;
            }

            $hasAppRwPolicy = false;
            $hasReportingRoPolicy = false;

            foreach ($policies as $pol) {
                $rolesStr = trim((string) $pol->roles_str, '{}');
                $roleOids = array_filter(explode(',', $rolesStr), fn (string $v): bool => $v !== '');

                // 0 indicates PUBLIC (applies to all roles)
                if (in_array('0', $roleOids, true)) {
                    $hasAppRwPolicy = true;
                    $hasReportingRoPolicy = true;
                    break;
                }

                foreach ($roleOids as $oidStr) {
                    $oid = (int) $oidStr;
                    $roleName = $roleMap[$oid] ?? null;
                    if ($roleName === 'app_rw') {
                        $hasAppRwPolicy = true;
                    }
                    if ($roleName === 'reporting_ro') {
                        $hasReportingRoPolicy = true;
                    }
                }
            }

            if (! $hasAppRwPolicy) {
                $errors[] = ['Policy', $table, 'Missing RLS policy coverage for role app_rw'];
            }

            if (! $hasReportingRoPolicy) {
                $errors[] = ['Policy', $table, 'Missing RLS policy coverage for role reporting_ro (NEW-04)'];
            }
        }

        if (! empty($errors)) {
            $this->error('RLS Verification FAILED: The following security violations were detected:');
            $this->table(['Type', 'Target', 'Violation Details'], $errors);

            return Command::FAILURE;
        }

        $tableCount = count($tenantTables);
        $this->info("✓ RLS Verification PASSED: All {$tableCount} tenant tables enforce forced RLS with valid policies for app_rw and reporting_ro.");

        return Command::SUCCESS;
    }
}
