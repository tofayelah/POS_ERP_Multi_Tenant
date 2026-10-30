<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->uuid('setting_id')->nullable();
            $table->string('key', 100);
            $table->string('scope_type', 50);
            $table->uuid('scope_id')->nullable();
            $table->jsonb('old_value')->nullable();
            $table->jsonb('new_value');
            $table->uuid('changed_by_user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('tenant_id', 'fk_setting_audit_tenant')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('setting_id', 'fk_setting_audit_setting')
                ->references('id')
                ->on('settings')
                ->nullOnDelete();
        });

        // Enforce RLS on setting_audit_logs
        DB::connection('pgsql_owner')->statement('ALTER TABLE setting_audit_logs ENABLE ROW LEVEL SECURITY;');
        DB::connection('pgsql_owner')->statement('ALTER TABLE setting_audit_logs FORCE ROW LEVEL SECURITY;');

        DB::connection('pgsql_owner')->statement("
            CREATE POLICY setting_audit_logs_app_rw ON setting_audit_logs
            FOR ALL TO app_rw
            USING (tenant_id IS NULL OR tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid)
            WITH CHECK (tenant_id IS NOT NULL AND tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid);
        ");

        DB::connection('pgsql_owner')->statement("
            CREATE POLICY setting_audit_logs_reporting_ro ON setting_audit_logs
            FOR SELECT TO reporting_ro
            USING (tenant_id IS NULL OR tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid);
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_audit_logs');
    }
};
