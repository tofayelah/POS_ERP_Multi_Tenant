<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->string('scope_type', 50);
            $table->uuid('scope_id')->nullable();
            $table->string('key', 100);
            $table->jsonb('value');
            $table->boolean('is_secret')->default(false);
            $table->timestampsTz();

            $table->foreign('tenant_id', 'fk_settings_tenant')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });

        // Partial unique index ensuring uniqueness across platform and tenant scopes
        DB::connection('pgsql_owner')->statement("
            CREATE UNIQUE INDEX uq_settings_scope_key ON settings (
                COALESCE(tenant_id, '00000000-0000-0000-0000-000000000000'::uuid),
                scope_type,
                COALESCE(scope_id, '00000000-0000-0000-0000-000000000000'::uuid),
                key
            )
        ");

        // Enforce RLS on settings
        DB::connection('pgsql_owner')->statement('ALTER TABLE settings ENABLE ROW LEVEL SECURITY;');
        DB::connection('pgsql_owner')->statement('ALTER TABLE settings FORCE ROW LEVEL SECURITY;');

        DB::connection('pgsql_owner')->statement("
            CREATE POLICY settings_app_rw ON settings
            FOR ALL TO app_rw
            USING (tenant_id IS NULL OR tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid)
            WITH CHECK (tenant_id IS NOT NULL AND tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid);
        ");

        DB::connection('pgsql_owner')->statement("
            CREATE POLICY settings_reporting_ro ON settings
            FOR SELECT TO reporting_ro
            USING (tenant_id IS NULL OR tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid);
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
