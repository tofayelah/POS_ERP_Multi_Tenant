<?php

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

test('can create billing plan with modules and limits', function () {
    $code = 'retail_standard_'.Str::random(6);
    $plan = Plan::create([
        'code' => $code,
        'name' => 'Retail Standard Plan',
        'description' => 'For growing multi-branch retailers in Bangladesh',
        'price_bdt' => '4999.0000',
        'billing_interval' => 'monthly',
        'included_modules' => ['accounting', 'catalog', 'tax', 'sales', 'pos'],
        'max_branches' => 3,
        'max_terminals' => 5,
        'max_users' => 10,
        'is_active' => true,
    ]);

    expect($plan->id)->not->toBeNull()
        ->and($plan->price_bdt)->toBe('4999.0000')
        ->and($plan->included_modules)->toContain('accounting', 'pos')
        ->and($plan->max_branches)->toBe(3);
});

test('duplicate plan code is rejected', function () {
    $code = 'unique_plan_'.Str::random(6);
    Plan::create([
        'code' => $code,
        'name' => 'First Plan',
    ]);

    expect(fn () => Plan::create([
        'code' => $code,
        'name' => 'Second Plan',
    ]))->toThrow(QueryException::class);
});

test('can create tenant subscription linked to tenant and plan', function () {
    $subdomain = 'apex_'.Str::random(6);
    $tenant = Tenant::create([
        'name' => 'Apex Retail Ltd',
        'subdomain' => $subdomain,
        'subscription_plan' => 'retail_standard',
        'status' => 'active',
        'data_residency_region' => 'dhaka-1',
    ]);

    $plan = Plan::create([
        'code' => 'enterprise_retail_'.Str::random(6),
        'name' => 'Enterprise Retail',
        'price_bdt' => '9999.0000',
        'included_modules' => ['accounting', 'tax', 'catalog', 'sales', 'pos', 'inventory'],
    ]);

    $sub = TenantSubscription::create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'current_period_starts_at' => now(),
        'current_period_ends_at' => now()->addMonth(),
        'extra_modules' => ['advanced_reporting'],
    ]);

    expect($sub->tenant->id)->toBe($tenant->id)
        ->and($sub->plan->id)->toBe($plan->id)
        ->and($tenant->activeSubscription->id)->toBe($sub->id)
        ->and($sub->extra_modules)->toContain('advanced_reporting');
});

test('subscription referencing nonexistent tenant is rejected', function () {
    $plan = Plan::create([
        'code' => 'basic_'.Str::random(6),
        'name' => 'Basic',
    ]);

    expect(fn () => TenantSubscription::create([
        'tenant_id' => (string) Uuid::uuid7(),
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'current_period_starts_at' => now(),
        'current_period_ends_at' => now()->addMonth(),
    ]))->toThrow(QueryException::class);
});
