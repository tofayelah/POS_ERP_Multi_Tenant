<?php

namespace App\Filament\Platform\Resources\Tenants\Pages;

use App\Filament\Platform\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use App\Modules\Billing\Contracts\OnboardingWizardServiceContract;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $name = (string) $data['name'];
        $subdomain = (string) $data['subdomain'];
        $planCode = (string) ($data['subscription_plan'] ?? 'standard');
        $adminEmail = ! empty($data['admin_email']) ? (string) $data['admin_email'] : "admin@{$subdomain}.kcerp.com";
        $adminPassword = ! empty($data['admin_password']) ? (string) $data['admin_password'] : 'Password123!';
        $companyName = ! empty($data['company_name']) ? (string) $data['company_name'] : "{$name} Ltd";
        $region = (string) ($data['data_residency_region'] ?? 'dhaka-1');

        $result = app(OnboardingWizardServiceContract::class)->onboard([
            'tenant_name' => $name,
            'subdomain' => $subdomain,
            'plan_code' => $planCode,
            'company_name' => $companyName,
            'admin_name' => "{$name} Admin",
            'admin_email' => $adminEmail,
            'admin_password' => $adminPassword,
            'currency' => 'BDT',
            'branch_name' => 'Main Outlet',
            'branch_code' => 'BR-01',
            'terminal_name' => 'POS Terminal 1',
            'terminal_code' => 'T-01',
            'data_residency_region' => $region,
        ]);

        /** @var Tenant $tenant */
        $tenant = $result['tenant'];

        app(\App\Core\Platform\Services\PlatformAuditService::class)->logTenantCreation($tenant, $data);

        return $tenant;
    }
}
