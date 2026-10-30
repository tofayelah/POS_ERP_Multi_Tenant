<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\RegistrationVerification;
use App\Modules\Billing\Contracts\RegistrationServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function __construct(
        protected RegistrationServiceContract $registrationService
    ) {}

    public function showForm(): View
    {
        $plans = Plan::where('is_active', true)->get();

        return view('auth.register', [
            'plans' => $plans,
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_name' => ['required', 'string', 'max:255'],
            'subdomain' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'admin_phone' => ['required', 'string', 'max:50'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
            'plan_code' => ['nullable', 'string', 'exists:plans,code'],
        ]);

        $verification = $this->registrationService->initiate($validated);

        return response()->json([
            'verification_id' => $verification->id,
            'message' => 'Verification code sent to phone and confirmation link to email.',
            'email_sent' => true,
            'phone_otp_sent' => true,
            'raw_otp' => app()->environment('production') ? null : $verification->raw_otp,
        ], 201);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $token = (string) $request->query('token');

        if (empty($token)) {
            return response()->json([
                'message' => 'Email verification token is required.',
                'errors' => ['token' => ['Verification token missing.']],
            ], 422);
        }

        $verification = $this->registrationService->verifyEmail($token);

        return response()->json([
            'email_verified' => true,
            'phone_verified' => $verification->phone_verified_at !== null,
            'completed' => $verification->phone_verified_at !== null,
            'message' => 'Email address successfully verified.',
        ]);
    }

    public function verifyPhone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'verification_id' => ['required', 'uuid', 'exists:registration_verifications,id'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $verification = $this->registrationService->verifyPhone(
            (string) $validated['verification_id'],
            (string) $validated['otp']
        );

        $isCompleted = $verification->email_verified_at !== null;

        return response()->json([
            'phone_verified' => true,
            'email_verified' => $verification->email_verified_at !== null,
            'completed' => $isCompleted,
            'message' => 'Phone number successfully verified.',
        ]);
    }
}
