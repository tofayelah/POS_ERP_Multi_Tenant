<?php

namespace Tests\Feature\Platform;

use App\Core\Platform\Services\PlatformAuditService;
use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class PlatformAuditLogTest extends TestCase
{
    protected PlatformUser $superAdmin;

    protected PlatformAuditService $auditService;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('platform.audit.hmac_secret', 'test-dedicated-audit-secret-key-64-characters-for-testing-purposes');
        Config::set('platform.audit.key_version', 1);

        $this->superAdmin = PlatformUser::firstOrCreate(
            ['email' => 'admin.audit@kcerp.local'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Super Admin Audit Test',
                'password' => 'Password123!',
                'role' => 'super_admin',
            ]
        );

        $this->auditService = new PlatformAuditService;
    }

    public function test_audit_service_records_action_with_actor_and_payload_hash(): void
    {
        $log = $this->auditService->record(
            action: 'test.action',
            resource: Tenant::class,
            resourceId: (string) Str::uuid(),
            description: 'Test action execution',
            oldValues: ['status' => 'draft'],
            newValues: ['status' => 'active'],
            reason: 'Testing audit recording',
            actor: $this->superAdmin
        );

        $this->assertInstanceOf(PlatformAuditLog::class, $log);
        $this->assertSame('test.action', $log->action);
        $this->assertSame($this->superAdmin->id, $log->platform_user_id);
        $this->assertSame($this->superAdmin->name, $log->actor_name);
        $this->assertSame($this->superAdmin->email, $log->actor_email);
        $this->assertSame(['status' => 'draft'], $log->old_values);
        $this->assertSame(['status' => 'active'], $log->new_values);
        $this->assertSame('Testing audit recording', $log->reason);
        $this->assertSame(1, $log->key_version);
        $this->assertNotEmpty($log->payload_hash);
        $this->assertSame(64, strlen($log->payload_hash));
    }

    public function test_sensitive_attributes_are_automatically_redacted(): void
    {
        $log = $this->auditService->record(
            action: 'operator.credentials_changed',
            resource: PlatformUser::class,
            resourceId: $this->superAdmin->id,
            description: 'Credential modification test',
            newValues: [
                'email' => 'test@kcerp.local',
                'password' => 'supersecretpass123',
                'admin_password' => 'anothersecret',
                'two_factor_secret' => 'SECRETKEY123',
                'token' => 'bearer_token_xyz',
            ],
            actor: $this->superAdmin
        );

        $this->assertSame('test@kcerp.local', $log->new_values['email']);
        $this->assertSame('[REDACTED]', $log->new_values['password']);
        $this->assertSame('[REDACTED]', $log->new_values['admin_password']);
        $this->assertSame('[REDACTED]', $log->new_values['two_factor_secret']);
        $this->assertSame('[REDACTED]', $log->new_values['token']);
    }

    public function test_platform_audit_log_is_immutable_in_eloquent(): void
    {
        $log = $this->auditService->record(
            action: 'immutability.test',
            resource: Tenant::class,
            resourceId: (string) Str::uuid(),
            description: 'Testing immutability enforcement',
            actor: $this->superAdmin
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Platform audit logs are strictly immutable and cannot be updated.');

        $log->update(['action' => 'tampered.action']);
    }

    public function test_platform_audit_log_cannot_be_deleted(): void
    {
        $log = $this->auditService->record(
            action: 'deletion.test',
            resource: Tenant::class,
            resourceId: (string) Str::uuid(),
            description: 'Testing delete prevention',
            actor: $this->superAdmin
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Platform audit logs cannot be deleted from the audit ledger.');

        $log->delete();
    }

    public function test_platform_audit_log_update_is_rejected_by_postgresql_trigger(): void
    {
        $log = $this->auditService->record(
            action: 'sql.update.test',
            resource: Tenant::class,
            resourceId: (string) Str::uuid(),
            description: 'Testing SQL update rejection via PostgreSQL trigger',
            actor: $this->superAdmin
        );

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Platform Audit Security Violation: platform_audit_logs records are strictly immutable and cannot be updated.');

        DB::table('platform_audit_logs')
            ->where('id', $log->id)
            ->update(['action' => 'hacked.action']);
    }

    public function test_platform_audit_log_delete_is_rejected_by_postgresql_trigger(): void
    {
        $log = $this->auditService->record(
            action: 'sql.delete.test',
            resource: Tenant::class,
            resourceId: (string) Str::uuid(),
            description: 'Testing SQL delete rejection via PostgreSQL trigger',
            actor: $this->superAdmin
        );

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Platform Audit Security Violation: platform_audit_logs records cannot be deleted from the audit ledger.');

        DB::table('platform_audit_logs')
            ->where('id', $log->id)
            ->delete();
    }

    public function test_platform_audit_log_truncate_is_rejected_by_statement_trigger(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Platform Audit Security Violation: platform_audit_logs table cannot be truncated.');

        DB::connection('pgsql_owner')->statement('TRUNCATE TABLE platform_audit_logs');
    }

    public function test_new_audit_record_verifies_using_dedicated_hmac_secret(): void
    {
        $log = $this->auditService->record(
            action: 'verify.valid',
            resource: Tenant::class,
            resourceId: (string) Str::uuid(),
            description: 'Testing valid record verification',
            oldValues: ['name' => 'Old Name'],
            newValues: ['name' => 'New Name'],
            actor: $this->superAdmin
        );

        $this->assertTrue($this->auditService->verify($log));
    }

    public function test_historical_audit_record_with_key_version_0_verifies_using_app_key(): void
    {
        // Replicate a Phase 1 historical record signed with APP_KEY
        $appKey = (string) config('app.key');
        $createdAt = Carbon::now()->subDays(5);
        $resourceId = (string) Str::uuid();

        $serialized = json_encode([
            'action' => 'historical.action',
            'actor_id' => $this->superAdmin->id,
            'resource_type' => Tenant::class,
            'resource_id' => $resourceId,
            'old' => null,
            'new' => ['name' => 'Historical Tenant'],
            'reason' => 'Phase 1 Legacy Audit',
            'status' => 'success',
            'created_at' => $createdAt->toIso8601String(),
        ], JSON_THROW_ON_ERROR);

        $legacyPayloadHash = hash_hmac('sha256', $serialized, $appKey);

        $historicalLog = PlatformAuditLog::create([
            'id' => (string) Str::uuid(),
            'platform_user_id' => $this->superAdmin->id,
            'actor_name' => $this->superAdmin->name,
            'actor_email' => $this->superAdmin->email,
            'action' => 'historical.action',
            'resource_type' => Tenant::class,
            'resource_id' => $resourceId,
            'resource_description' => 'Phase 1 Historical Record',
            'old_values' => null,
            'new_values' => ['name' => 'Historical Tenant'],
            'reason' => 'Phase 1 Legacy Audit',
            'client_ip' => '127.0.0.1',
            'status' => 'success',
            'payload_hash' => $legacyPayloadHash,
            'key_version' => 0, // Legacy Phase 1 key version
            'created_at' => $createdAt,
        ]);

        $this->assertTrue(
            $this->auditService->verify($historicalLog),
            'Historical audit record with key_version 0 must verify using Laravel APP_KEY.'
        );
    }

    public function test_tampered_audit_record_fails_verification(): void
    {
        $log = $this->auditService->record(
            action: 'tamper.detect',
            resource: Tenant::class,
            resourceId: (string) Str::uuid(),
            description: 'Testing tamper detection',
            oldValues: ['tier' => 'basic'],
            newValues: ['tier' => 'enterprise'],
            actor: $this->superAdmin
        );

        // Verify valid first
        $this->assertTrue($this->auditService->verify($log));

        // Create a fake cloned model with altered new_values
        $tamperedLog = new PlatformAuditLog($log->toArray());
        $tamperedLog->new_values = ['tier' => 'free'];

        $this->assertFalse(
            $this->auditService->verify($tamperedLog),
            'Tampered record must fail HMAC verification.'
        );
    }

    public function test_key_rotation_allows_historical_and_new_records_to_coexist(): void
    {
        // 1. Create record with key_version 1
        Config::set('platform.audit.key_version', 1);
        Config::set('platform.audit.hmac_secret', 'secret-key-version-1');

        $logV1 = $this->auditService->record(
            action: 'rotation.v1',
            resource: Tenant::class,
            resourceId: (string) Str::uuid(),
            actor: $this->superAdmin
        );

        $this->assertTrue($this->auditService->verify($logV1));

        // 2. Rotate to key_version 2: v1 is archived in historical_keys
        Config::set('platform.audit.key_version', 2);
        Config::set('platform.audit.hmac_secret', 'secret-key-version-2');
        Config::set('platform.audit.historical_keys', [
            1 => 'secret-key-version-1',
        ]);

        $logV2 = $this->auditService->record(
            action: 'rotation.v2',
            resource: Tenant::class,
            resourceId: (string) Str::uuid(),
            actor: $this->superAdmin
        );

        $this->assertSame(2, $logV2->key_version);
        $this->assertTrue($this->auditService->verify($logV2), 'New V2 record must verify with V2 key.');
        $this->assertTrue($this->auditService->verify($logV1), 'Historical V1 record must verify with historical V1 key.');
    }

    public function test_audit_verification_fails_closed_when_key_is_missing(): void
    {
        Config::set('platform.audit.key_version', 99); // Unknown key version
        Config::set('platform.audit.hmac_secret', '');
        Config::set('platform.audit.historical_keys', []);

        $log = new PlatformAuditLog([
            'id' => (string) Str::uuid(),
            'action' => 'missing.key',
            'resource_type' => Tenant::class,
            'resource_id' => '123',
            'payload_hash' => 'dummy-hash',
            'key_version' => 99,
            'created_at' => Carbon::now(),
        ]);

        $this->assertFalse($this->auditService->verify($log), 'Verification must fail closed when key is missing.');
    }

    public function test_audit_recording_fails_closed_when_secret_is_unconfigured(): void
    {
        Config::set('platform.audit.key_version', 1);
        Config::set('platform.audit.hmac_secret', ''); // Empty secret

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Platform Audit Security Failure: PLATFORM_AUDIT_HMAC_SECRET is not configured.');

        $this->auditService->record(
            action: 'fail.closed',
            resource: Tenant::class,
            resourceId: (string) Str::uuid(),
            actor: $this->superAdmin
        );
    }
}
