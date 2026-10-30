<?php

namespace App\Modules\Billing\Services;

use App\Models\Plan;
use App\Models\RegistrationVerification;
use App\Modules\Billing\Contracts\OnboardingWizardServiceContract;
use App\Modules\Billing\Contracts\RegistrationServiceContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrationService implements RegistrationServiceContract
{
    public const array RESERVED_SUBDOMAINS = [
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
        'support',
        'billing',
        'status',
        'docs',
        'test',
        'demo',
    ];

    public function __construct(
        protected OnboardingWizardServiceContract $wizardService
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function initiate(array $data): RegistrationVerification
    {
        $subdomain = strtolower(trim((string) ($data['subdomain'] ?? '')));

        if (in_array($subdomain, self::RESERVED_SUBDOMAINS, true)) {
            throw ValidationException::withMessages([
                'subdomain' => ["The subdomain '{$subdomain}' is reserved by the platform."],
            ]);
        }

        if (! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $subdomain) || strlen($subdomain) < 3 || strlen($subdomain) > 30) {
            throw ValidationException::withMessages([
                'subdomain' => ['Subdomain must be 3-30 lowercase alphanumeric characters, separated by single hyphens.'],
            ]);
        }

        $emailToken = Str::random(40);
        $rawOtp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $phoneOtpHash = Hash::make($rawOtp);

        $payload = array_merge($data, [
            'subdomain' => $subdomain,
        ]);

        return RegistrationVerification::create([
            'email' => (string) $data['admin_email'],
            'phone' => (string) $data['admin_phone'],
            'email_token' => $emailToken,
            'email_verified_at' => null,
            'phone_otp_hash' => $phoneOtpHash,
            'raw_otp' => app()->environment('production') ? null : $rawOtp,
            'phone_verified_at' => null,
            'payload' => $payload,
            'expires_at' => Carbon::now()->addHours(24),
            'attempts' => 0,
        ]);
    }

    public function verifyEmail(string $token): RegistrationVerification
    {
        /** @var RegistrationVerification|null $verification */
        $verification = RegistrationVerification::where('email_token', $token)->first();

        if (! $verification) {
            throw ValidationException::withMessages([
                'token' => ['The provided email verification token is invalid or does not exist.'],
            ]);
        }

        if ($verification->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => ['This verification request has expired. Please register again.'],
            ]);
        }

        $verification->update([
            'email_verified_at' => Carbon::now(),
        ]);

        if ($verification->phone_verified_at !== null) {
            $this->completeRegistration($verification);
        }

        return $verification;
    }

    public function verifyPhone(string $verificationId, string $otp): RegistrationVerification
    {
        /** @var RegistrationVerification|null $verification */
        $verification = RegistrationVerification::find($verificationId);

        if (! $verification) {
            throw ValidationException::withMessages([
                'verification_id' => ['Registration session not found.'],
            ]);
        }

        if ($verification->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp' => ['This verification session has expired.'],
            ]);
        }

        if ($verification->attempts >= 3) {
            throw ValidationException::withMessages([
                'otp' => ['Maximum phone verification attempts exceeded. Please initiate a new registration.'],
            ]);
        }

        if (! Hash::check($otp, $verification->phone_otp_hash)) {
            $verification->increment('attempts');

            throw ValidationException::withMessages([
                'otp' => ['The 6-digit verification code entered is incorrect.'],
            ]);
        }

        $verification->update([
            'phone_verified_at' => Carbon::now(),
        ]);

        if ($verification->email_verified_at !== null) {
            $this->completeRegistration($verification);
        }

        return $verification;
    }

    /**
     * @return array<string, mixed>
     */
    public function completeRegistration(RegistrationVerification $verification): array
    {
        $payload = $verification->payload;

        $planCode = (string) ($payload['plan_code'] ?? 'standard');
        $plan = Plan::where('code', $planCode)->first();
        $planId = $plan ? $plan->id : null;

        $onboardData = [
            'tenant_name' => $payload['tenant_name'],
            'subdomain' => $payload['subdomain'],
            'company_name' => $payload['company_name'] ?? $payload['tenant_name'],
            'currency' => $payload['currency'] ?? 'BDT',
            'plan_id' => $planId,
            'branch_name' => $payload['branch_name'] ?? 'Main Store',
            'branch_code' => $payload['branch_code'] ?? 'BR-001',
            'terminal_code' => $payload['terminal_code'] ?? 'POS-01',
            'terminal_name' => $payload['terminal_name'] ?? 'Main Counter',
            'admin_name' => $payload['admin_name'],
            'admin_email' => $payload['admin_email'],
            'admin_phone' => $payload['admin_phone'] ?? null,
            'admin_password' => $payload['admin_password'],
        ];

        return $this->wizardService->onboard($onboardData);
    }
}
