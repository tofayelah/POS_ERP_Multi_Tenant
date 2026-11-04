<?php

use App\Core\Exceptions\ModuleHasActiveDependentsException;
use App\Core\Exceptions\ModuleHasOpenDocumentsException;
use App\Core\Tenancy\TenantContext;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Modules\Billing\Contracts\ModuleEntitlementServiceContract;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name' => 'Entitlement Test Tenant',
        'subdomain' => 'ent-'.Str::random(6),
        'status' => 'active',
        'data_residency_region' => 'dhaka-1',
    ]);

    TenantContext::set($this->tenant);

    $this->service = app(ModuleEntitlementServiceContract::class);
});

test('syncFromSubscription entitles modules and Pennant mirrors them (MOD-04)', function () {
    $plan = Plan::create([
        'code' => 'plan_'.Str::random(6),
        'name' => 'Standard Retail',
        'included_modules' => ['catalog', 'tax', 'accounting', 'sales'],
    ]);

    $sub = TenantSubscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'current_period_starts_at' => now(),
        'current_period_ends_at' => now()->addMonth(),
        'extra_modules' => ['pos'],
    ]);

    $this->service->syncFromSubscription($sub);

    expect($this->service->isEntitled($this->tenant, 'accounting'))->toBeTrue()
        ->and($this->service->isEntitled($this->tenant, 'pos'))->toBeTrue()
        ->and($this->service->isEntitled($this->tenant, 'inventory'))->toBeFalse();

    // Verify Pennant feature flag mirrors entitlement
    expect(Feature::for($this->tenant)->active('accounting'))->toBeTrue()
        ->and(Feature::for($this->tenant)->active('pos'))->toBeTrue()
        ->and(Feature::for($this->tenant)->active('inventory'))->toBeFalse();
});

test('disabling module with active dependents throws ModuleHasActiveDependentsException (MOD-03)', function () {
    $plan = Plan::create([
        'code' => 'plan_'.Str::random(6),
        'name' => 'Full Suite',
        'included_modules' => ['catalog', 'tax', 'accounting', 'sales', 'pos'],
    ]);

    $sub = TenantSubscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'current_period_starts_at' => now(),
        'current_period_ends_at' => now()->addMonth(),
    ]);

    $this->service->syncFromSubscription($sub);

    // Sales and POS depend on Accounting; disabling accounting must throw
    expect(fn () => $this->service->disable($this->tenant, 'accounting'))
        ->toThrow(ModuleHasActiveDependentsException::class);

    // Accounting remains enabled
    expect($this->service->isEntitled($this->tenant, 'accounting'))->toBeTrue();
});

test('disabling module with open documents throws ModuleHasOpenDocumentsException (MOD-03)', function () {
    $plan = Plan::create([
        'code' => 'plan_'.Str::random(6),
        'name' => 'Accounting Only',
        'included_modules' => ['catalog', 'tax', 'accounting'],
    ]);

    $sub = TenantSubscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'current_period_starts_at' => now(),
        'current_period_ends_at' => now()->addMonth(),
    ]);

    $this->service->syncFromSubscription($sub);

    $company = Company::create([
        'tenant_id' => $this->tenant->id,
        'legal_name' => 'Test Company',
        'base_currency' => 'BDT',
        'fiscal_year_start_month' => 7,
        'tax_regime' => 'standard',
    ]);

    $fiscalYear = FiscalYear::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $company->id,
        'title' => 'FY 2026-2027',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
        'is_closed' => false,
    ]);

    $period = AccountingPeriod::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $company->id,
        'fiscal_year_id' => $fiscalYear->id,
        'period_number' => 1,
        'period_name' => 'July 2026',
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'status' => 'open',
    ]);

    $user = User::create([
        'name' => 'Test Accountant',
        'email' => 'acct-'.Str::random(6).'@test.com',
        'password' => Hash::make('Password123!'),
    ]);

    // Create draft unposted journal entry in accounting
    JournalEntry::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $company->id,
        'period_id' => $period->id,
        'entry_number' => 'JV-DRAFT-01',
        'posting_date' => '2026-07-15',
        'business_date' => '2026-07-15',
        'reference_type' => 'manual',
        'posted_by' => $user->id,
        'is_posted' => false,
        'narration' => 'Draft test journal',
    ]);

    // Disabling accounting must be blocked by open draft document
    expect(fn () => $this->service->disable($this->tenant, 'accounting'))
        ->toThrow(ModuleHasOpenDocumentsException::class);
});

test('disabling module succeeds when dependents are disabled and no open documents exist', function () {
    $plan = Plan::create([
        'code' => 'plan_'.Str::random(6),
        'name' => 'Sales and POS',
        'included_modules' => ['catalog', 'tax', 'accounting', 'sales', 'pos'],
    ]);

    $sub = TenantSubscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'current_period_starts_at' => now(),
        'current_period_ends_at' => now()->addMonth(),
    ]);

    $this->service->syncFromSubscription($sub);

    // Disable pos first (no dependents)
    $this->service->disable($this->tenant, 'pos');
    expect($this->service->isEntitled($this->tenant, 'pos'))->toBeFalse()
        ->and(Feature::for($this->tenant)->active('pos'))->toBeFalse();

    // Now sales can be disabled
    $this->service->disable($this->tenant, 'sales');
    expect($this->service->isEntitled($this->tenant, 'sales'))->toBeFalse()
        ->and(Feature::for($this->tenant)->active('sales'))->toBeFalse();

    // Now accounting can be disabled
    $this->service->disable($this->tenant, 'accounting');
    expect($this->service->isEntitled($this->tenant, 'accounting'))->toBeFalse()
        ->and(Feature::for($this->tenant)->active('accounting'))->toBeFalse();
});
