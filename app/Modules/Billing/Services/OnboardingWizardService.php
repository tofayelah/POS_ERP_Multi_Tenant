<?php

namespace App\Modules\Billing\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Plan;
use App\Models\PosTerminal;
use App\Models\TaxCode;
use App\Models\TaxCodeRate;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Models\UserLocationScope;
use App\Modules\Accounting\Database\Seeders\BangladeshRetailCoaTemplate;
use App\Modules\Billing\Contracts\ModuleEntitlementServiceContract;
use App\Modules\Billing\Contracts\OnboardingWizardServiceContract;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class OnboardingWizardService implements OnboardingWizardServiceContract
{
    public function __construct(
        protected ModuleEntitlementServiceContract $entitlementService
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function onboard(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $planCode = (string) ($data['plan_code'] ?? 'standard');

            // 1. Create Tenant
            /** @var Tenant $tenant */
            $tenant = Tenant::create([
                'name' => (string) $data['tenant_name'],
                'subdomain' => (string) $data['subdomain'],
                'subscription_plan' => $planCode,
                'status' => 'active',
                'data_residency_region' => (string) ($data['data_residency_region'] ?? 'dhaka-1'),
            ]);

            TenantContext::set($tenant);
            if (function_exists('setPermissionsTeamId')) {
                setPermissionsTeamId($tenant->id);
            }

            // 2. Resolve Plan and create Subscription
            $plan = Plan::where('code', $planCode)->first();
            if (! $plan) {
                $plan = Plan::create([
                    'code' => $planCode,
                    'name' => 'Default Starter Plan',
                    'included_modules' => ['accounting', 'tax', 'catalog', 'sales', 'pos'],
                ]);
            }

            /** @var TenantSubscription $subscription */
            $subscription = TenantSubscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'current_period_starts_at' => now(),
                'current_period_ends_at' => now()->addMonth(),
                'extra_modules' => (array) ($data['extra_modules'] ?? []),
            ]);

            $this->entitlementService->syncFromSubscription($subscription);

            // 3. Create Primary Company
            /** @var Company $company */
            $company = Company::create([
                'tenant_id' => $tenant->id,
                'legal_name' => (string) $data['company_name'],
                'bin_number' => isset($data['bin_number']) ? (string) $data['bin_number'] : null,
                'base_currency' => (string) ($data['currency'] ?? 'BDT'),
                'fiscal_year_start_month' => (int) ($data['fiscal_year_start_month'] ?? 7),
                'tax_regime' => (string) ($data['tax_regime'] ?? 'standard'),
            ]);

            // 4. Create Primary Branch
            /** @var Branch $branch */
            $branch = Branch::create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'name' => (string) ($data['branch_name'] ?? 'Main Branch'),
                'code' => (string) ($data['branch_code'] ?? 'BR-MAIN'),
                'is_active' => true,
            ]);

            // 5. Create Primary POS Terminal
            /** @var PosTerminal $terminal */
            $terminal = PosTerminal::create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'terminal_code' => (string) ($data['terminal_code'] ?? 'T-001'),
                'name' => (string) ($data['terminal_name'] ?? 'Primary POS Terminal'),
                'is_active' => true,
            ]);

            // 6. Create Admin User
            /** @var User $user */
            $user = User::create([
                'name' => (string) $data['admin_name'],
                'email' => (string) $data['admin_email'],
                'phone' => isset($data['admin_phone']) ? (string) $data['admin_phone'] : null,
                'password' => Hash::make((string) $data['admin_password']),
            ]);

            // 7. Tenant Membership
            $membership = TenantMembership::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'is_active' => true,
                'joined_at' => now(),
            ]);

            // 8. Location Scope
            UserLocationScope::create([
                'tenant_id' => $tenant->id,
                'membership_id' => $membership->id,
                'company_id' => $company->id,
                'branch_id' => null,
            ]);

            // 9. Assign Tenant Owner Role
            $role = Role::firstOrCreate([
                'name' => 'Tenant Owner',
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);
            $user->assignRole($role);

            // 10. Seed Default Data for Accounting
            $this->seedAccountingModule($tenant, $company);

            // 11. Seed Default Data for Tax
            $this->seedTaxModule($tenant, $company);

            return [
                'tenant' => $tenant,
                'company' => $company,
                'branch' => $branch,
                'terminal' => $terminal,
                'user' => $user,
                'subscription' => $subscription,
            ];
        });
    }

    protected function seedAccountingModule(Tenant $tenant, Company $company): void
    {
        $currentMonth = (int) now()->format('n');
        $startYear = $currentMonth >= 7 ? (int) now()->format('Y') : (int) now()->format('Y') - 1;
        $endYear = $startYear + 1;

        $fyStart = Carbon::createFromDate($startYear, 7, 1)->startOfDay();
        $fyEnd = Carbon::createFromDate($endYear, 6, 30)->endOfDay();

        $fiscalYear = FiscalYear::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'title' => "FY {$startYear}-{$endYear}",
            'start_date' => $fyStart->toDateString(),
            'end_date' => $fyEnd->toDateString(),
            'is_closed' => false,
        ]);

        $periodStart = $fyStart->copy();
        for ($i = 1; $i <= 12; $i++) {
            $periodEnd = $periodStart->copy()->endOfMonth();

            AccountingPeriod::create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'fiscal_year_id' => $fiscalYear->id,
                'period_number' => $i,
                'period_name' => $periodStart->format('F Y'),
                'start_date' => $periodStart->toDateString(),
                'end_date' => $periodEnd->toDateString(),
                'status' => 'open',
            ]);

            $periodStart->addMonthNoOverflow()->startOfMonth();
        }

        (new BangladeshRetailCoaTemplate)->seedForCompany($tenant->id, $company->id);
    }

    protected function seedTaxModule(Tenant $tenant, Company $company): void
    {
        $taxCodes = [
            ['code' => 'VAT-15', 'name_en' => 'Standard VAT 15%', 'name_bn' => 'আদর্শ মূসক ১৫%', 'rate' => '15.0000', 'sd' => '0.0000'],
            ['code' => 'VAT-05', 'name_en' => 'Retail / POS Reduced VAT 5%', 'name_bn' => 'খুচরা মূসক ৫%', 'rate' => '5.0000', 'sd' => '0.0000'],
            ['code' => 'VAT-00', 'name_en' => 'Zero-Rated 0%', 'name_bn' => 'শূন্যহার ০%', 'rate' => '0.0000', 'sd' => '0.0000'],
            ['code' => 'EXEMPT', 'name_en' => 'Exempt (Schedule 1)', 'name_bn' => 'মূসক অব্যাহতিপ্রাপ্ত', 'rate' => '0.0000', 'sd' => '0.0000'],
        ];

        foreach ($taxCodes as $tcData) {
            $tc = TaxCode::create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'code' => $tcData['code'],
                'name_en' => $tcData['name_en'],
                'name_bn' => $tcData['name_bn'],
                'is_active' => true,
            ]);

            TaxCodeRate::create([
                'tenant_id' => $tenant->id,
                'company_id' => $company->id,
                'tax_code_id' => $tc->id,
                'rate_percent' => $tcData['rate'],
                'supplementary_duty_percent' => $tcData['sd'],
                'effective_from' => '2024-01-01',
                'effective_to' => null,
            ]);
        }
    }
}
