<?php

namespace App\Modules\Billing\Contracts;

interface OnboardingWizardServiceContract
{
    /**
     * Complete full tenant onboarding: provisions tenant, company, branch, terminal,
     * admin user, subscription entitlements, and calls seedDefaultData() across modules.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function onboard(array $data): array;
}
