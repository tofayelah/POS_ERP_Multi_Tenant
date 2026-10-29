<?php

namespace App\Core\Database;

use Closure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class TenantMigration extends Migration
{
    /**
     * Create a new tenant-scoped table with forced RLS and composite unique index.
     *
     * @param  string  $table
     * @param  Closure(Blueprint): void  $callback
     * @param  string  $connection
     * @return void
     */
    protected function createTenantTable(string $table, Closure $callback, string $connection = 'pgsql_owner'): void
    {
        $schema = Schema::connection($connection);

        $schema->create($table, function (Blueprint $blueprint) use ($callback, $table) {
            $blueprint->uuid('id')->primary();
            $blueprint->uuid('tenant_id');

            $callback($blueprint);

            $blueprint->unique(['tenant_id', 'id'], "uq_{$table}_tenant_id");
            $blueprint->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('restrict');
        });

        $this->enableForcedRls($table, $connection);
    }

    /**
     * Enable and force fail-closed RLS on the table for app_rw and reporting_ro.
     */
    protected function enableForcedRls(string $table, string $connection = 'pgsql_owner'): void
    {
        DB::connection($connection)->statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::connection($connection)->statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");

        DB::connection($connection)->statement("
            CREATE POLICY tenant_isolation_policy ON {$table}
                AS RESTRICTIVE
                FOR ALL
                TO app_rw, reporting_ro
                USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid)
                WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid)
        ");
    }

    /**
     * Drop a tenant-scoped table.
     */
    protected function dropTenantTable(string $table, string $connection = 'pgsql_owner'): void
    {
        Schema::connection($connection)->dropIfExists($table);
    }
}
