<?php

use App\Core\Exceptions\TenantSuspendedException;
use App\Core\Http\Middleware\EnforceTenantState;
use App\Core\Http\Middleware\TenantResolutionMiddleware;
use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\PosShift;
use App\Models\PosTerminal;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Modules\Billing\Contracts\TenantLifecycleServiceContract;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    $this->subdomain = 'lifecycle-'.Str::random(6);
    $this->tenant = Tenant::create([
        'name' => 'Lifecycle Tenant Ltd',
        'subdomain' => $this->subdomain,
        'status' => 'active',
        'data_residency_region' => 'dhaka-1',
    ]);

    TenantContext::set($this->tenant);

    $this->company = Company::create([
        'tenant_id' => $this->tenant->id,
        'legal_name' => 'Lifecycle Company Ltd',
        'base_currency' => 'BDT',
        'fiscal_year_start_month' => 7,
        'tax_regime' => 'standard',
    ]);

    $this->branch = Branch::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $this->company->id,
        'name' => 'Gulshan Store',
        'code' => 'BR-'.Str::random(4),
        'is_active' => true,
    ]);

    $this->terminal = PosTerminal::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'terminal_code' => 'POS-01',
        'name' => 'Main POS Register',
        'is_active' => true,
    ]);

    $this->user = User::create([
        'name' => 'Lifecycle Cashier',
        'email' => 'cashier-'.Str::random(6).'@lifecycle.com',
        'password' => Hash::make('Password123!'),
    ]);

    TenantMembership::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'is_active' => true,
    ]);

    $this->lifecycle = app(TenantLifecycleServiceContract::class);
});

test('tenant resolution middleware resolves suspended tenant for statutory export access (TEN-11)', function () {
    $this->tenant->update(['status' => 'suspended']);

    $middleware = new TenantResolutionMiddleware;
    $request = Request::create("https://{$this->subdomain}.kcerp.com/export/vat-returns", 'GET');

    $response = $middleware->handle($request, function ($req) {
        return new Response('OK: resolved tenant '.TenantContext::id());
    });

    expect($response->getContent())->toBe('OK: resolved tenant '.$this->tenant->id);
});

test('enforce tenant state middleware allows GET exports but blocks mutations when suspended (TEN-11)', function () {
    $this->tenant->update(['status' => 'suspended']);

    $middleware = new EnforceTenantState;

    // 1. GET requests (exports/read) must pass
    $getReq = Request::create("https://{$this->subdomain}.kcerp.com/export/ledgers", 'GET');
    $getResponse = $middleware->handle($getReq, function ($req) {
        return new Response('EXPORT_DATA_OK');
    });
    expect($getResponse->getStatusCode())->toBe(200)
        ->and($getResponse->getContent())->toBe('EXPORT_DATA_OK');

    // 2. POST mutations must be blocked with HTTP 403
    $postReq = Request::create("https://{$this->subdomain}.kcerp.com/products", 'POST', ['name' => 'New Product']);
    try {
        $middleware->handle($postReq, function ($req) {
            return new Response('MUTATION_SHOULD_NOT_HAPPEN');
        });
        $this->fail('Expected HttpException 403');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});

test('suspended tenant allows active in-flight POS shift to close, but blocks opening new shifts (TEN-11)', function () {
    // 1. Open shift while tenant is active
    $shift = PosShift::create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'terminal_id' => $this->terminal->id,
        'user_id' => $this->user->id,
        'status' => 'open',
        'opened_at' => now(),
        'opening_cash' => '500.0000',
    ]);

    // 2. Tenant becomes suspended
    $this->lifecycle->suspend($this->tenant, 'Non-payment of subscription');
    $this->tenant->refresh();
    expect($this->tenant->status)->toBe('suspended');

    // 3. Cashier can close current in-flight shift
    expect($this->lifecycle->canClosePosShift($this->tenant))->toBeTrue();
    $shift->update([
        'status' => 'closed',
        'closed_at' => now(),
        'closing_cash' => '1200.0000',
    ]);
    expect($shift->fresh()->status)->toBe('closed');

    // 4. Cashier CANNOT open new shift
    expect($this->lifecycle->canOpenPosShift($this->tenant))->toBeFalse();
    expect(fn () => $this->lifecycle->assertCanOpenShift($this->tenant))
        ->toThrow(TenantSuspendedException::class);
});

test('lifecycle service transitions between active, in_arrears and suspended', function () {
    expect($this->lifecycle->isActive($this->tenant))->toBeTrue();

    // Mark in arrears with grace period
    $this->lifecycle->markInArrears($this->tenant, now()->addDays(7));
    expect($this->tenant->fresh()->status)->toBe('in_arrears')
        ->and($this->lifecycle->isInArrears($this->tenant->fresh()))->toBeTrue()
        ->and($this->lifecycle->canOpenPosShift($this->tenant->fresh()))->toBeTrue();

    // Suspend
    $this->lifecycle->suspend($this->tenant->fresh(), 'Grace period expired');
    expect($this->tenant->fresh()->status)->toBe('suspended')
        ->and($this->lifecycle->isSuspended($this->tenant->fresh()))->toBeTrue()
        ->and($this->lifecycle->canOpenPosShift($this->tenant->fresh()))->toBeFalse();

    // Reinstate
    $this->lifecycle->reinstate($this->tenant->fresh());
    expect($this->tenant->fresh()->status)->toBe('active')
        ->and($this->lifecycle->isActive($this->tenant->fresh()))->toBeTrue();
});
