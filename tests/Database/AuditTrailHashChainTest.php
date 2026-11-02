<?php

namespace Tests\Database;

use App\Core\Audit\Services\VerifyAuditHashChainService;
use App\Core\Audit\Workers\BuildAuditHashChainWorker;
use App\Core\Tenancy\TenantContext;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditTrailHashChainTest extends TestCase
{
    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Audit Test Tenant',
            'subdomain' => 'audit-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Audit Officer',
            'email' => 'officer-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_audit_logs_table_structure_and_forced_rls(): void
    {
        $hasTable = DB::connection('pgsql_owner')->selectOne("
            SELECT EXISTS (
                SELECT 1 FROM information_schema.tables 
                WHERE table_name = 'audit_logs'
            ) AS exists
        ");

        $this->assertTrue($hasTable->exists, 'audit_logs table must exist in schema.');

        // Check forced RLS
        $rls = DB::connection('pgsql_owner')->selectOne("
            SELECT relrowsecurity, relforcerowsecurity 
            FROM pg_class 
            WHERE relname = 'audit_logs'
        ");

        $this->assertTrue($rls->relrowsecurity, 'Row Level Security must be enabled on audit_logs');
        $this->assertTrue($rls->relforcerowsecurity, 'FORCE Row Level Security must be enabled on audit_logs');
    }

    public function test_audit_hasher_assigns_chain_position_and_computes_sha256_chain(): void
    {
        $hasher = app(BuildAuditHashChainWorker::class);
        $verifier = app(VerifyAuditHashChainService::class);

        // Insert 3 audit records in pending_hash status
        $log1 = AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'table_name' => 'sales_invoices',
            'record_id' => (string) Str::uuid(),
            'action' => 'INSERT',
            'actor_id' => $this->user->id,
            'actor_type' => 'user',
            'old_values' => null,
            'new_values' => ['invoice_number' => 'INV-001', 'total' => '500.00'],
            'payload_hash' => hash('sha256', json_encode(['invoice_number' => 'INV-001', 'total' => '500.00'])),
            'status' => 'pending_hash',
        ]);

        $log2 = AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'table_name' => 'sales_invoices',
            'record_id' => (string) Str::uuid(),
            'action' => 'INSERT',
            'actor_id' => $this->user->id,
            'actor_type' => 'user',
            'old_values' => null,
            'new_values' => ['invoice_number' => 'INV-002', 'total' => '750.00'],
            'payload_hash' => hash('sha256', json_encode(['invoice_number' => 'INV-002', 'total' => '750.00'])),
            'status' => 'pending_hash',
        ]);

        $log3 = AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'table_name' => 'sales_invoices',
            'record_id' => (string) Str::uuid(),
            'action' => 'INSERT',
            'actor_id' => $this->user->id,
            'actor_type' => 'user',
            'old_values' => null,
            'new_values' => ['invoice_number' => 'INV-003', 'total' => '1200.00'],
            'payload_hash' => hash('sha256', json_encode(['invoice_number' => 'INV-003', 'total' => '1200.00'])),
            'status' => 'pending_hash',
        ]);

        // Run the hash chain worker
        $processedCount = $hasher->processTenant($this->tenant->id);
        $this->assertSame(3, $processedCount);

        // Reload logs
        $l1 = AuditLog::findOrFail($log1->id);
        $l2 = AuditLog::findOrFail($log2->id);
        $l3 = AuditLog::findOrFail($log3->id);

        $this->assertSame(1, (int) $l1->chain_position);
        $this->assertSame('verified', $l1->status);
        $this->assertSame(str_repeat('0', 64), $l1->prev_hash);
        $this->assertSame(hash('sha256', str_repeat('0', 64).$l1->payload_hash), $l1->current_hash);

        $this->assertSame(2, (int) $l2->chain_position);
        $this->assertSame('verified', $l2->status);
        $this->assertSame($l1->current_hash, $l2->prev_hash);
        $this->assertSame(hash('sha256', $l1->current_hash.$l2->payload_hash), $l2->current_hash);

        $this->assertSame(3, (int) $l3->chain_position);
        $this->assertSame('verified', $l3->status);
        $this->assertSame($l2->current_hash, $l3->prev_hash);
        $this->assertSame(hash('sha256', $l2->current_hash.$l3->payload_hash), $l3->current_hash);

        // Verification service checks in chain_position order
        $verification = $verifier->verifyTenant($this->tenant->id);
        $this->assertTrue($verification->isValid);
        $this->assertSame(3, $verification->verifiedCount);
        $this->assertEmpty($verification->tamperedRecords);
    }

    public function test_slow_transaction_committing_after_later_rows_does_not_cause_false_tamper_alert(): void
    {
        $hasher = app(BuildAuditHashChainWorker::class);
        $verifier = app(VerifyAuditHashChainService::class);

        // First batch: logs 1 and 2 processed
        AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'table_name' => 'sales_invoices',
            'record_id' => (string) Str::uuid(),
            'action' => 'INSERT',
            'actor_id' => $this->user->id,
            'actor_type' => 'user',
            'payload_hash' => hash('sha256', 'payload-1'),
            'status' => 'pending_hash',
        ]);
        $hasher->processTenant($this->tenant->id);

        // Now simulate a transaction that was created earlier (older created_at) but committed later
        $slowLog = AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'table_name' => 'sales_invoices',
            'record_id' => (string) Str::uuid(),
            'action' => 'INSERT',
            'actor_id' => $this->user->id,
            'actor_type' => 'user',
            'payload_hash' => hash('sha256', 'slow-tx-payload'),
            'status' => 'pending_hash',
            'created_at' => Carbon::now()->subMinutes(10), // older timestamp
        ]);

        AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'table_name' => 'sales_invoices',
            'record_id' => (string) Str::uuid(),
            'action' => 'INSERT',
            'actor_id' => $this->user->id,
            'actor_type' => 'user',
            'payload_hash' => hash('sha256', 'payload-3'),
            'status' => 'pending_hash',
            'created_at' => Carbon::now(),
        ]);

        $hasher->processTenant($this->tenant->id);

        // Verify that verifier traversing in chain_position order passes with ZERO false alarms
        $verification = $verifier->verifyTenant($this->tenant->id);
        $this->assertTrue($verification->isValid);
        $this->assertSame(3, $verification->verifiedCount);
        $this->assertEmpty($verification->tamperedRecords);
    }

    public function test_trigger_allows_one_time_hash_transition_and_rejects_edits_and_deletions(): void
    {
        $hasher = app(BuildAuditHashChainWorker::class);

        $log = AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'table_name' => 'sales_invoices',
            'record_id' => (string) Str::uuid(),
            'action' => 'INSERT',
            'actor_id' => $this->user->id,
            'actor_type' => 'user',
            'payload_hash' => hash('sha256', 'initial-payload'),
            'status' => 'pending_hash',
        ]);

        // Attempting to directly update payload_hash while pending is forbidden
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Immutability Violation|cannot be modified/i');

        DB::table('audit_logs')
            ->where('id', $log->id)
            ->update(['payload_hash' => hash('sha256', 'tampered')]);
    }

    public function test_trigger_rejects_deletion_of_audit_logs(): void
    {
        $log = AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'table_name' => 'sales_invoices',
            'record_id' => (string) Str::uuid(),
            'action' => 'INSERT',
            'actor_id' => $this->user->id,
            'actor_type' => 'user',
            'payload_hash' => hash('sha256', 'delete-payload'),
            'status' => 'pending_hash',
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Immutability Violation.*cannot be deleted/i');

        DB::table('audit_logs')->where('id', $log->id)->delete();
    }

    public function test_verifier_detects_out_of_band_tampering(): void
    {
        $hasher = app(BuildAuditHashChainWorker::class);
        $verifier = app(VerifyAuditHashChainService::class);

        $log1 = AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'table_name' => 'sales_invoices',
            'record_id' => (string) Str::uuid(),
            'action' => 'INSERT',
            'actor_id' => $this->user->id,
            'actor_type' => 'user',
            'payload_hash' => hash('sha256', 'legit-1'),
            'status' => 'pending_hash',
        ]);

        $log2 = AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'table_name' => 'sales_invoices',
            'record_id' => (string) Str::uuid(),
            'action' => 'INSERT',
            'actor_id' => $this->user->id,
            'actor_type' => 'user',
            'payload_hash' => hash('sha256', 'legit-2'),
            'status' => 'pending_hash',
        ]);

        $hasher->processTenant($this->tenant->id);

        // Bypassing triggers as pgsql_owner to tamper with log 2
        DB::connection('pgsql_owner')->statement('ALTER TABLE audit_logs DISABLE TRIGGER trg_audit_logs_immutable;');
        DB::table('audit_logs')
            ->where('id', $log2->id)
            ->update(['payload_hash' => hash('sha256', 'tampered-payload')]);
        DB::connection('pgsql_owner')->statement('ALTER TABLE audit_logs ENABLE TRIGGER trg_audit_logs_immutable;');

        // Run verifier
        $verification = $verifier->verifyTenant($this->tenant->id);

        $this->assertFalse($verification->isValid);
        $this->assertNotEmpty($verification->tamperedRecords);
        $this->assertSame($log2->id, $verification->tamperedRecords[0]['id']);
    }
}
