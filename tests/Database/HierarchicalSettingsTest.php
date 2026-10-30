<?php

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\PosTerminal;
use App\Models\Setting;
use App\Models\SettingAuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Billing\Contracts\HierarchicalSettingsServiceContract;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name' => 'Settings Test Tenant',
        'subdomain' => 'set-'.Str::random(6),
        'status' => 'active',
        'data_residency_region' => 'dhaka-1',
    ]);

    TenantContext::set($this->tenant);

    $this->company = Company::create([
        'tenant_id' => $this->tenant->id,
        'legal_name' => 'Settings Retail Ltd',
        'base_currency' => 'BDT',
        'fiscal_year_start_month' => 7,
        'tax_regime' => 'standard',
    ]);

    $this->branchA = Branch::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $this->company->id,
        'name' => 'Branch A',
        'code' => 'BR-A-'.Str::random(4),
        'is_active' => true,
    ]);

    $this->branchB = Branch::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $this->company->id,
        'name' => 'Branch B',
        'code' => 'BR-B-'.Str::random(4),
        'is_active' => true,
    ]);

    $this->terminal1 = PosTerminal::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branchB->id,
        'terminal_code' => 'T-01',
        'name' => 'Terminal 1',
        'is_active' => true,
    ]);

    $this->terminal2 = PosTerminal::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branchB->id,
        'terminal_code' => 'T-02',
        'name' => 'Terminal 2',
        'is_active' => true,
    ]);

    $this->user = User::create([
        'name' => 'Settings Admin',
        'email' => 'admin-'.Str::random(6).'@settings.com',
        'password' => Hash::make('Password123!'),
    ]);

    $this->settings = app(HierarchicalSettingsServiceContract::class);
});

test('branch override beats tenant default and platform default (CORE-03)', function () {
    // 1. Platform level default
    $this->settings->set('pos.cash_rounding', 'none', 'platform', null, null);

    // 2. Tenant level setting
    $this->settings->set('pos.cash_rounding', 'none', 'tenant', null, $this->tenant->id);

    // 3. Branch B override
    $this->settings->set('pos.cash_rounding', 'nearest_integer', 'branch', $this->branchB->id, $this->tenant->id);

    // Branch A has no override, should resolve to tenant default ('none')
    $resolvedA = $this->settings->get(
        key: 'pos.cash_rounding',
        branchId: $this->branchA->id,
        companyId: $this->company->id,
        tenantId: $this->tenant->id
    );
    expect($resolvedA)->toBe('none');

    // Branch B has override, should resolve to 'nearest_integer'
    $resolvedB = $this->settings->get(
        key: 'pos.cash_rounding',
        branchId: $this->branchB->id,
        companyId: $this->company->id,
        tenantId: $this->tenant->id
    );
    expect($resolvedB)->toBe('nearest_integer');
});

test('terminal override beats branch, company and tenant (CORE-03)', function () {
    // Branch level: 'nearest_integer'
    $this->settings->set('pos.cash_rounding', 'nearest_integer', 'branch', $this->branchB->id, $this->tenant->id);

    // Terminal 2 override: 'half_up'
    $this->settings->set('pos.cash_rounding', 'half_up', 'terminal', $this->terminal2->id, $this->tenant->id);

    // Terminal 1 has no terminal-level override; inherits Branch B's 'nearest_integer'
    $resolvedT1 = $this->settings->get(
        key: 'pos.cash_rounding',
        terminalId: $this->terminal1->id,
        branchId: $this->branchB->id,
        companyId: $this->company->id,
        tenantId: $this->tenant->id
    );
    expect($resolvedT1)->toBe('nearest_integer');

    // Terminal 2 has explicit override; resolves to 'half_up'
    $resolvedT2 = $this->settings->get(
        key: 'pos.cash_rounding',
        terminalId: $this->terminal2->id,
        branchId: $this->branchB->id,
        companyId: $this->company->id,
        tenantId: $this->tenant->id
    );
    expect($resolvedT2)->toBe('half_up');
});

test('updating setting logs audit record with old and new values and invalidates cache (CORE-03)', function () {
    $this->settings->set(
        key: 'receipt.header_text',
        value: 'Original Header',
        scopeType: 'tenant',
        scopeId: null,
        tenantId: $this->tenant->id,
        userId: $this->user->id
    );

    // Confirm initial value
    expect($this->settings->get('receipt.header_text', tenantId: $this->tenant->id))->toBe('Original Header');

    // Update setting
    $this->settings->set(
        key: 'receipt.header_text',
        value: 'Updated Header 2026',
        scopeType: 'tenant',
        scopeId: null,
        tenantId: $this->tenant->id,
        userId: $this->user->id
    );

    // Value must update immediately from cache
    expect($this->settings->get('receipt.header_text', tenantId: $this->tenant->id))->toBe('Updated Header 2026');

    // Verify audit log exists
    $auditLog = SettingAuditLog::where('tenant_id', $this->tenant->id)
        ->where('key', 'receipt.header_text')
        ->latest('created_at')
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->old_value)->toBe('Original Header')
        ->and($auditLog->new_value)->toBe('Updated Header 2026')
        ->and($auditLog->changed_by_user_id)->toBe($this->user->id);
});
