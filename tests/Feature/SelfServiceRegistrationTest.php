<?php

use App\Models\Plan;
use App\Models\RegistrationVerification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Ensure at least one standard plan exists for self-service sign-up
    $this->plan = Plan::firstOrCreate(
        ['code' => 'standard'],
        [
            'name' => 'Standard Retail Plan',
            'description' => 'Standard Retail Plan for Bangladesh',
            'price_bdt' => '5000.00',
            'billing_interval' => 'monthly',
            'max_branches' => 3,
            'max_terminals' => 5,
            'max_users' => 10,
            'included_modules' => ['core', 'accounting', 'tax', 'sales', 'pos'],
            'is_active' => true,
        ]
    );
});

test('rejects registration with reserved subdomains', function (string $reserved) {
    $response = $this->postJson('/api/register', [
        'tenant_name' => 'Test Reserved Tenant',
        'subdomain' => $reserved,
        'admin_name' => 'John Doe',
        'admin_email' => 'admin@example.com',
        'admin_phone' => '+8801712345678',
        'admin_password' => 'SecurePass123!@#',
        'admin_password_confirmation' => 'SecurePass123!@#',
        'plan_code' => 'standard',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['subdomain']);
})->with([
    'admin',
    'platform',
    'api',
    'app',
    'pos',
    'dev',
    'mail',
    'staging',
    'system',
    'root',
    'www',
]);

test('rejects registration with invalid subdomain format', function (string $invalidSubdomain) {
    $response = $this->postJson('/api/register', [
        'tenant_name' => 'Invalid Subdomain Store',
        'subdomain' => $invalidSubdomain,
        'admin_name' => 'John Doe',
        'admin_email' => 'valid@example.com',
        'admin_phone' => '+8801712345678',
        'admin_password' => 'SecurePass123!@#',
        'admin_password_confirmation' => 'SecurePass123!@#',
        'plan_code' => 'standard',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['subdomain']);
})->with([
    'Fresh Mart',
    'fresh_mart',
    'FRESHMART',
    '-freshmart',
    'freshmart-',
    'fr',
]);

test('enforces rate limits on self-service registration (5 attempts per hour per IP)', function () {
    for ($i = 1; $i <= 5; $i++) {
        $response = $this->postJson('/api/register', [
            'tenant_name' => "Tenant Rate {$i}",
            'subdomain' => "rate-test-{$i}-".Str::random(4),
            'admin_name' => "User {$i}",
            'admin_email' => "rate{$i}@example.com",
            'admin_phone' => "+880171000000{$i}",
            'admin_password' => 'SecurePass123!@#',
            'admin_password_confirmation' => 'SecurePass123!@#',
            'plan_code' => 'standard',
        ]);

        $response->assertSuccessful();
    }

    // 6th attempt from same IP must be throttled with HTTP 429
    $response = $this->postJson('/api/register', [
        'tenant_name' => 'Tenant Rate 6',
        'subdomain' => 'rate-test-6-'.Str::random(4),
        'admin_name' => 'User 6',
        'admin_email' => 'rate6@example.com',
        'admin_phone' => '+8801710000006',
        'admin_password' => 'SecurePass123!@#',
        'admin_password_confirmation' => 'SecurePass123!@#',
        'plan_code' => 'standard',
    ]);

    $response->assertStatus(429);
});

test('initiates registration, creates verification record, and allows email and phone verification', function () {
    $subdomain = 'demo-mart-'.Str::random(4);
    $response = $this->postJson('/api/register', [
        'tenant_name' => 'Demo Mart Global',
        'subdomain' => $subdomain,
        'company_name' => 'Demo Mart Bangladesh Ltd',
        'admin_name' => 'Demo Owner',
        'admin_email' => 'owner@demomart.com',
        'admin_phone' => '+8801712999888',
        'admin_password' => 'SecretPassword123!',
        'admin_password_confirmation' => 'SecretPassword123!',
        'plan_code' => 'standard',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'verification_id',
            'message',
            'email_sent',
            'phone_otp_sent',
        ]);

    $verificationId = $response->json('verification_id');
    $verification = RegistrationVerification::findOrFail($verificationId);

    expect($verification->email)->toBe('owner@demomart.com')
        ->and($verification->phone)->toBe('+8801712999888')
        ->and($verification->email_verified_at)->toBeNull()
        ->and($verification->phone_verified_at)->toBeNull();

    // 1. Verify invalid email token fails
    $badTokenResponse = $this->getJson('/api/verify-email?token=invalid_random_token');
    $badTokenResponse->assertStatus(422);

    // 2. Verify valid email token succeeds
    $emailResponse = $this->getJson("/api/verify-email?token={$verification->email_token}");
    $emailResponse->assertSuccessful()
        ->assertJson(['email_verified' => true]);

    $verification->refresh();
    expect($verification->email_verified_at)->not->toBeNull();

    // 3. Verify bad phone OTP fails and increments attempts
    $badOtpResponse = $this->postJson('/api/verify-phone', [
        'verification_id' => $verification->id,
        'otp' => '000000',
    ]);
    $badOtpResponse->assertStatus(422);

    $verification->refresh();
    expect($verification->attempts)->toBe(1);

    // Provide OTP stored on verification session (in testing we inspect or provide known OTP)
    // We verify with correct OTP
    expect($verification->raw_otp)->not->toBeNull();
    $goodOtpResponse = $this->postJson('/api/verify-phone', [
        'verification_id' => $verification->id,
        'otp' => $verification->raw_otp,
    ]);
    $goodOtpResponse->assertSuccessful()
        ->assertJson([
            'phone_verified' => true,
            'completed' => true,
        ]);

    // 4. Verify onboarding was triggered and tenant now exists
    $tenant = Tenant::where('subdomain', $subdomain)->first();
    expect($tenant)->not->toBeNull()
        ->and($tenant->name)->toBe('Demo Mart Global')
        ->and($tenant->status)->toBe('active');

    $user = User::where('email', 'owner@demomart.com')->first();
    expect($user)->not->toBeNull()
        ->and(Hash::check('SecretPassword123!', $user->password))->toBeTrue();
});
