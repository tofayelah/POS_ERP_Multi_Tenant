<?php

namespace Tests\Feature\Security;

use App\Core\Tenancy\TenantContext;
use App\Filament\Pages\AuditLogPage;
use App\Filament\Pages\JournalsPage;
use App\Filament\Pages\MushakReportsPage;
use App\Filament\Pages\ProductsPage;
use App\Filament\Pages\SalesPage;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DemoTenantSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PermissionMatrixTest extends TestCase
{
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoTenantSeeder::class);

        $tenant = Tenant::where('subdomain', 'acme')->firstOrFail();
        $this->tenant = $tenant;
        TenantContext::set($tenant);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_cashier_permissions_match_design_matrix_3_2(): void
    {
        $cashier = User::where('email', 'cashier@acme.com')->firstOrFail();
        Auth::login($cashier);

        // Products: Read-Only -> Can Access
        $this->assertTrue(ProductsPage::canAccess(), 'Cashier must have access to Products');

        // Sales: Execute / POS Checkout -> Can Access
        $this->assertTrue(SalesPage::canAccess(), 'Cashier must have access to Sales');

        // Journals: None -> Must NOT Access
        $this->assertFalse(JournalsPage::canAccess(), 'Cashier must NOT have access to Journals');

        // Mushak reports: 6.3 Print -> Can Access
        $this->assertTrue(MushakReportsPage::canAccess(), 'Cashier must have access to Mushak reports');

        // Audit log: None -> Must NOT Access
        $this->assertFalse(AuditLogPage::canAccess(), 'Cashier must NOT have access to Audit log');
    }

    public function test_shift_supervisor_permissions_match_design_matrix_3_2(): void
    {
        $supervisor = User::where('email', 'supervisor@acme.com')->firstOrFail();
        Auth::login($supervisor);

        // Products: Read -> Can Access
        $this->assertTrue(ProductsPage::canAccess(), 'Shift Supervisor must have access to Products');

        // Sales: Full POS Checkout -> Can Access
        $this->assertTrue(SalesPage::canAccess(), 'Shift Supervisor must have access to Sales');

        // Journals: None -> Must NOT Access
        $this->assertFalse(JournalsPage::canAccess(), 'Shift Supervisor must NOT have access to Journals');

        // Mushak reports: None -> Must NOT Access
        $this->assertFalse(MushakReportsPage::canAccess(), 'Shift Supervisor must NOT have access to Mushak reports');

        // Audit log: None -> Must NOT Access
        $this->assertFalse(AuditLogPage::canAccess(), 'Shift Supervisor must NOT have access to Audit log');
    }

    public function test_branch_manager_permissions_match_design_matrix_3_2(): void
    {
        $branchMgr = User::where('email', 'branchmgr@acme.com')->firstOrFail();
        Auth::login($branchMgr);

        // Products: Read -> Can Access
        $this->assertTrue(ProductsPage::canAccess(), 'Branch Manager must have access to Products');

        // Sales: Full -> Can Access
        $this->assertTrue(SalesPage::canAccess(), 'Branch Manager must have access to Sales');

        // Journals: Read -> Can Access
        $this->assertTrue(JournalsPage::canAccess(), 'Branch Manager must have access to Journals');

        // Mushak reports: Branch 6.3 -> Can Access
        $this->assertTrue(MushakReportsPage::canAccess(), 'Branch Manager must have access to Mushak reports');

        // Audit log: Branch Logs -> Can Access
        $this->assertTrue(AuditLogPage::canAccess(), 'Branch Manager must have access to Audit log');
    }

    public function test_accountant_permissions_match_design_matrix_3_2(): void
    {
        $accountant = User::where('email', 'accountant@acme.com')->firstOrFail();
        Auth::login($accountant);

        // Products: Read -> Can Access
        $this->assertTrue(ProductsPage::canAccess(), 'Accountant must have access to Products');

        // Sales: Read -> Can Access
        $this->assertTrue(SalesPage::canAccess(), 'Accountant must have access to Sales');

        // Journals: Full -> Can Access
        $this->assertTrue(JournalsPage::canAccess(), 'Accountant must have access to Journals');

        // Mushak reports: Full -> Can Access
        $this->assertTrue(MushakReportsPage::canAccess(), 'Accountant must have access to Mushak reports');

        // Audit log: Read-Only -> Can Access
        $this->assertTrue(AuditLogPage::canAccess(), 'Accountant must have access to Audit log');
    }

    public function test_tenant_owner_permissions_match_design_matrix_3_2(): void
    {
        $owner = User::where('email', 'owner@acme.com')->firstOrFail();
        Auth::login($owner);

        // Products: Full -> Can Access
        $this->assertTrue(ProductsPage::canAccess(), 'Tenant Owner must have access to Products');

        // Sales: Full -> Can Access
        $this->assertTrue(SalesPage::canAccess(), 'Tenant Owner must have access to Sales');

        // Journals: Full -> Can Access
        $this->assertTrue(JournalsPage::canAccess(), 'Tenant Owner must have access to Journals');

        // Mushak reports: Full -> Can Access
        $this->assertTrue(MushakReportsPage::canAccess(), 'Tenant Owner must have access to Mushak reports');

        // Audit log: Full -> Can Access
        $this->assertTrue(AuditLogPage::canAccess(), 'Tenant Owner must have access to Audit log');
    }
}
