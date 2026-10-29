<?php

namespace Tests\Feature\Security;

use App\Core\Security\SoD;
use App\Core\Tenancy\TenantContext;
use App\Models\PlatformUser;
use App\Models\Tenant;
use App\Models\TenantSupportAccess;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SegregationOfDutiesTest extends TestCase
{
    protected Tenant $tenant;

    protected User $tenantOwner;

    protected User $tenantAdmin;

    protected User $accountant;

    protected User $purchaser;

    protected User $clerk;

    protected PlatformUser $platformSupport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Uuid::uuid7(),
            'name' => 'SoD Test Tenant',
            'subdomain' => 'sod-'.substr((string) Uuid::uuid7(), 0, 8),
            'subscription_plan' => 'enterprise',
            'status' => 'active',
            'data_residency_region' => 'dhaka-1',
        ]);

        TenantContext::set($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        foreach (['Tenant Owner', 'Tenant Admin', 'Accountant'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        }

        $this->tenantOwner = User::create([
            'id' => (string) Uuid::uuid7(),
            'name' => 'Owner User',
            'email' => 'owner.'.uniqid().'@sod.test',
            'password' => Hash::make('secret'),
        ]);
        $this->tenantOwner->assignRole('Tenant Owner');

        $this->tenantAdmin = User::create([
            'id' => (string) Uuid::uuid7(),
            'name' => 'Admin User',
            'email' => 'admin.'.uniqid().'@sod.test',
            'password' => Hash::make('secret'),
        ]);
        $this->tenantAdmin->assignRole('Tenant Admin');

        $this->accountant = User::create([
            'id' => (string) Uuid::uuid7(),
            'name' => 'Accountant User',
            'email' => 'acc.'.uniqid().'@sod.test',
            'password' => Hash::make('secret'),
        ]);
        $this->accountant->assignRole('Accountant');

        $this->purchaser = User::create([
            'id' => (string) Uuid::uuid7(),
            'name' => 'Purchaser User',
            'email' => 'buyer.'.uniqid().'@sod.test',
            'password' => Hash::make('secret'),
        ]);

        $this->clerk = User::create([
            'id' => (string) Uuid::uuid7(),
            'name' => 'Clerk User',
            'email' => 'clerk.'.uniqid().'@sod.test',
            'password' => Hash::make('secret'),
        ]);

        $this->platformSupport = PlatformUser::create([
            'id' => (string) Uuid::uuid7(),
            'name' => 'Support Engineer',
            'email' => 'support.'.uniqid().'@kcerp.test',
            'password' => Hash::make('secret'),
            'role' => 'support_engineer',
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * SoD-01 (Purchase & Pay Conflict):
     * A user who creates or approves a PO cannot post the corresponding supplier payment.
     */
    public function test_sod_01_user_who_created_or_approved_po_cannot_post_payment(): void
    {
        $poCreatedByPurchaser = (object) [
            'id' => (string) Uuid::uuid7(),
            'created_by' => $this->purchaser->id,
            'approved_by' => (string) Uuid::uuid7(),
        ];

        $poApprovedByPurchaser = (object) [
            'id' => (string) Uuid::uuid7(),
            'created_by' => (string) Uuid::uuid7(),
            'approved_by' => $this->purchaser->id,
        ];

        $this->assertFalse(SoD::canPostPayment($this->purchaser, $poCreatedByPurchaser));
        $this->assertFalse(SoD::canPostPayment($this->purchaser, $poApprovedByPurchaser));
        $this->assertTrue(SoD::canPostPayment($this->accountant, $poCreatedByPurchaser));
    }

    /**
     * SoD-02 (Supplier Creation & Payment):
     * A user who creates or edits a supplier record cannot authorize disbursements to that supplier.
     */
    public function test_sod_02_user_who_created_or_edited_supplier_cannot_authorize_disbursement(): void
    {
        $supplierCreatedByClerk = (object) [
            'id' => (string) Uuid::uuid7(),
            'created_by' => $this->clerk->id,
            'updated_by' => null,
        ];

        $supplierUpdatedByClerk = (object) [
            'id' => (string) Uuid::uuid7(),
            'created_by' => (string) Uuid::uuid7(),
            'updated_by' => $this->clerk->id,
        ];

        $this->assertFalse(SoD::canAuthorizeDisbursement($this->clerk, $supplierCreatedByClerk));
        $this->assertFalse(SoD::canAuthorizeDisbursement($this->clerk, $supplierUpdatedByClerk));
        $this->assertTrue(SoD::canAuthorizeDisbursement($this->accountant, $supplierCreatedByClerk));
    }

    /**
     * SoD-03 (Inventory Count & Adjustment):
     * A warehouse clerk who performs physical stock counts cannot approve stock write-off adjustments.
     */
    public function test_sod_03_clerk_who_recorded_stock_count_cannot_approve_write_off(): void
    {
        $stockCountByClerk = (object) [
            'id' => (string) Uuid::uuid7(),
            'counted_by' => $this->clerk->id,
        ];

        $this->assertFalse(SoD::canApproveStockAdjustment($this->clerk, $stockCountByClerk));
        $this->assertTrue(SoD::canApproveStockAdjustment($this->tenantOwner, $stockCountByClerk));
    }

    /**
     * SoD-04 (Role Escalation Prevention):
     * Tenant Admin cannot assign Accountant or Tenant Owner role to themselves or without Owner counter-signature.
     */
    public function test_sod_04_tenant_admin_cannot_self_assign_accountant_or_owner_role(): void
    {
        // Admin attempts to self-assign Accountant -> Denied
        $this->assertFalse(SoD::canAssignRole($this->tenantAdmin, $this->tenantAdmin, 'Accountant'));

        // Admin attempts to self-assign Tenant Owner -> Denied
        $this->assertFalse(SoD::canAssignRole($this->tenantAdmin, $this->tenantAdmin, 'Tenant Owner'));

        // Admin attempts to assign Accountant to another user WITHOUT Owner counter-signature -> Denied
        $this->assertFalse(SoD::canAssignRole($this->tenantAdmin, $this->purchaser, 'Accountant', null));

        // Admin assigns Accountant WITH Owner counter-signature -> Allowed
        $this->assertTrue(SoD::canAssignRole($this->tenantAdmin, $this->purchaser, 'Accountant', $this->tenantOwner));

        // Tenant Owner assigning directly -> Allowed
        $this->assertTrue(SoD::canAssignRole($this->tenantOwner, $this->purchaser, 'Accountant'));
    }

    /**
     * SoD-05 (Super-Admin Elevation Protocol):
     * Platform support engineer cannot access tenant resources without active consent record.
     */
    public function test_sod_05_platform_support_cannot_access_tenant_without_active_consent(): void
    {
        // 1. Without consent record -> Denied
        $this->assertFalse(SoD::hasValidSupportAccess($this->platformSupport, $this->tenant));

        // 2. With valid active consent record -> Allowed
        $access = TenantSupportAccess::create([
            'id' => (string) Uuid::uuid7(),
            'tenant_id' => $this->tenant->id,
            'platform_user_id' => $this->platformSupport->id,
            'granted_by_user_id' => $this->tenantOwner->id,
            'ticket_reference' => 'INC-10023',
            'granted_at' => now()->subMinute(),
            'expires_at' => now()->addHours(2),
            'is_read_only' => true,
            'revoked_at' => null,
        ]);

        $this->assertTrue(SoD::hasValidSupportAccess($this->platformSupport, $this->tenant));

        // 3. When revoked -> Denied
        $access->update(['revoked_at' => now()]);
        $this->assertFalse(SoD::hasValidSupportAccess($this->platformSupport, $this->tenant));
    }
}
