<?php

namespace App\Modules\Billing\Contracts;

use App\Models\RegistrationVerification;

interface RegistrationServiceContract
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function initiate(array $data): RegistrationVerification;

    public function verifyEmail(string $token): RegistrationVerification;

    public function verifyPhone(string $verificationId, string $otp): RegistrationVerification;

    /**
     * @return array<string, mixed>
     */
    public function completeRegistration(RegistrationVerification $verification): array;
}
