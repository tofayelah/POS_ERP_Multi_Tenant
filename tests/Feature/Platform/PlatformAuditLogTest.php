<?php

namespace Tests\Feature\Platform;

use App\Core\Platform\Services\PlatformAuditService;
use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use App\Models\Tenant;
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

        $this->superAdmin = PlatformUser::firstOrCreate(
            ['email' => 'admin.audit@kcerp.local'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Super Admin Audit Test',
                'password' => bcrypt('Password123!'),
                'role' => 'super_admin',
            ]
        );

        $this->auditService = new PlatformAuditService();
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
}
