<?php

namespace Tests\Feature\Security;

use App\Core\Http\Middleware\VerifySupportAccessActive;
use App\Core\Security\SupportAccessService;
use App\Core\Tenancy\TenantContext;
use App\Models\PlatformUser;
use App\Models\Tenant;
use App\Models\TenantSupportAccess;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SupportAccessTest extends TestCase
{
    protected Tenant $tenant;

    protected User $owner;

    protected User $regularUser;

    protected PlatformUser $supportEngineer;

    protected SupportAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Uuid::uuid7(),
            'name' => 'Support Access Test Tenant',
            'subdomain' => 'support-'.uniqid(),
            'subscription_plan' => 'enterprise',
            'status' => 'active',
            'data_residency_region' => 'dhaka-1',
        ]);

        TenantContext::set($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        Role::firstOrCreate(['name' => 'Tenant Owner', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);

        $this->owner = User::create([
            'id' => (string) Uuid::uuid7(),
            'name' => 'Owner',
            'email' => 'owner.'.uniqid().'@test.com',
            'password' => Hash::make('secret'),
        ]);
        $this->owner->assignRole('Tenant Owner');

        $this->regularUser = User::create([
            'id' => (string) Uuid::uuid7(),
            'name' => 'Staff',
            'email' => 'staff.'.uniqid().'@test.com',
            'password' => Hash::make('secret'),
        ]);

        $this->supportEngineer = PlatformUser::create([
            'id' => (string) Uuid::uuid7(),
            'name' => 'Support Engineer',
            'email' => 'support.'.uniqid().'@platform.com',
            'password' => Hash::make('secret'),
            'role' => 'support_engineer',
        ]);

        $this->service = new SupportAccessService;
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * Review SEC-02:
     * Support access active within 4 hours is permitted.
     */
    public function test_support_access_active_within_four_hours(): void
    {
        $access = $this->service->grant(
            $this->tenant,
            $this->supportEngineer,
            $this->owner,
            'TICKET-12345',
            120, // 2 hours
            true
        );

        $this->assertNotNull($access);
        $this->assertTrue($access->isValid());
        $this->assertEquals('TICKET-12345', $access->ticket_reference);
        $this->assertTrue($access->is_read_only);
    }

    /**
     * Review SEC-02:
     * Granting support access exceeding 4 hours (240 minutes) is rejected.
     */
    public function test_support_access_rejects_grant_exceeding_four_hours(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Support access duration must not exceed 4 hours (240 minutes).');

        $this->service->grant(
            $this->tenant,
            $this->supportEngineer,
            $this->owner,
            'TICKET-INVALID',
            241 // > 4 hours
        );
    }

    /**
     * Review SEC-02:
     * Support access strictly expires and is rejected after 4 hours.
     */
    public function test_support_access_expires_and_is_rejected_after_four_hours(): void
    {
        $access = TenantSupportAccess::create([
            'id' => (string) Uuid::uuid7(),
            'tenant_id' => $this->tenant->id,
            'platform_user_id' => $this->supportEngineer->id,
            'granted_by_user_id' => $this->owner->id,
            'ticket_reference' => 'TICKET-EXPIRED',
            'granted_at' => now()->subHours(5),
            'expires_at' => now()->subHour(), // Expired 1 hour ago
            'is_read_only' => true,
            'revoked_at' => null,
        ]);

        $this->assertFalse($access->isValid());

        session(['active_support_access_id' => $access->id]);

        $middleware = new VerifySupportAccessActive;
        $request = Request::create('/admin/acme/dashboard', 'GET');
        $request->setLaravelSession(session()->driver());

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Support access session has expired or been revoked.');

        $middleware->handle($request, fn () => response('OK'));
    }

    /**
     * Review SEC-02:
     * Support access is read-only by default and rejects modifications.
     */
    public function test_support_access_is_read_only_by_default_and_rejects_modifications(): void
    {
        $access = $this->service->grant(
            $this->tenant,
            $this->supportEngineer,
            $this->owner,
            'TICKET-RO',
            60,
            true // Read-only
        );

        session(['active_support_access_id' => $access->id]);
        $middleware = new VerifySupportAccessActive;

        // 1. GET requests are permitted
        $getRequest = Request::create('/admin/acme/settings', 'GET');
        $getRequest->setLaravelSession(session()->driver());
        $response = $middleware->handle($getRequest, fn () => response('OK'));
        $this->assertEquals(200, $response->getStatusCode());

        // 2. POST write requests are rejected with 403 Forbidden
        $postRequest = Request::create('/admin/acme/settings', 'POST');
        $postRequest->setLaravelSession(session()->driver());

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Support access elevation is strictly read-only.');

        $middleware->handle($postRequest, fn () => response('OK'));
    }
}
