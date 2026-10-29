<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('tenant_support_accesses', function (Blueprint $table) {
            $table->foreignUuid('platform_user_id')->constrained('platform_users')->cascadeOnDelete();
            $table->foreignUuid('granted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ticket_reference', 100);
            $table->timestampTz('granted_at');
            $table->timestampTz('expires_at');
            $table->boolean('is_read_only')->default(true);
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
        });

        DB::connection('pgsql_owner')->statement("
            ALTER TABLE tenant_support_accesses
            ADD CONSTRAINT chk_support_access_max_four_hours
            CHECK (expires_at <= granted_at + INTERVAL '4 hours')
        ");
    }

    public function down(): void
    {
        $this->dropTenantTable('tenant_support_accesses');
    }
};
