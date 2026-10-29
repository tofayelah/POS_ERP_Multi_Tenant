<?php

namespace Database\Seeders;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\PlatformUser;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\UserLocationScope;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Reset Spatie permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Create Platform Super-Admin
        PlatformUser::firstOrCreate(
            ['email' => 'superadmin@kcerp.com'],
            [
                'id' => (string) Uuid::uuid7(),
                'name' => 'System Super Admin',
                'password' => Hash::make('Password123!'),
                'role' => 'super_admin',
            ]
        );

        // 3. Create Demo Tenant: Acme Retail BD
        $tenant = Tenant::firstOrCreate(
            ['subdomain' => 'acme'],
            [
                'id' => (string) Uuid::uuid7(),
                'name' => 'Acme Retail BD',
                'subscription_plan' => 'enterprise',
                'status' => 'active',
                'data_residency_region' => 'dhaka-1',
            ]
        );

        // Bind TenantContext for RLS-protected tables
        TenantContext::set($tenant);
        setPermissionsTeamId($tenant->id);

        try {
            // 4. Create Companies under Acme Retail BD
            $companyRetail = Company::firstOrCreate(
                ['tenant_id' => $tenant->id, 'legal_name' => 'Acme Retail Ltd'],
                [
                    'id' => (string) Uuid::uuid7(),
                    'bin_number' => '000111222-0101',
                    'base_currency' => 'BDT',
                    'fiscal_year_start_month' => 7,
                    'tax_regime' => 'standard_vat',
                ]
            );

            $companyWholesale = Company::firstOrCreate(
                ['tenant_id' => $tenant->id, 'legal_name' => 'Acme Wholesale Ltd'],
                [
                    'id' => (string) Uuid::uuid7(),
                    'bin_number' => '000111222-0202',
                    'base_currency' => 'BDT',
                    'fiscal_year_start_month' => 7,
                    'tax_regime' => 'standard_vat',
                ]
            );

            // 5. Create Branches
            $branchDhanmondi = Branch::firstOrCreate(
                ['tenant_id' => $tenant->id, 'company_id' => $companyRetail->id, 'code' => 'DHN-01'],
                [
                    'id' => (string) Uuid::uuid7(),
                    'name' => 'Dhanmondi Branch',
                    'is_active' => true,
                ]
            );

            $branchGulshan = Branch::firstOrCreate(
                ['tenant_id' => $tenant->id, 'company_id' => $companyRetail->id, 'code' => 'GUL-01'],
                [
                    'id' => (string) Uuid::uuid7(),
                    'name' => 'Gulshan Branch',
                    'is_active' => true,
                ]
            );

            $branchUttara = Branch::firstOrCreate(
                ['tenant_id' => $tenant->id, 'company_id' => $companyWholesale->id, 'code' => 'UTT-01'],
                [
                    'id' => (string) Uuid::uuid7(),
                    'name' => 'Uttara Branch',
                    'is_active' => true,
                ]
            );

            // 6. Create Spatie Roles for this Tenant
            $roleNames = [
                'Tenant Owner',
                'Tenant Admin',
                'Branch Manager',
                'Shift Supervisor',
                'Accountant',
                'Procurement Manager',
                'Sales Manager',
                'Warehouse Manager',
                'Cashier',
                'Sales Representative',
                'Auditor',
            ];

            foreach ($roleNames as $roleName) {
                Role::firstOrCreate([
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'tenant_id' => $tenant->id,
                ]);
            }

            // 7. Seed Personas from Matrix (§3.2)
            $usersConfig = [
                [
                    'email' => 'owner@acme.com',
                    'name' => 'Acme Owner',
                    'role' => 'Tenant Owner',
                    'scopes' => [
                        ['company_id' => $companyRetail->id, 'branch_id' => null],
                        ['company_id' => $companyWholesale->id, 'branch_id' => null],
                    ],
                ],
                [
                    'email' => 'admin@acme.com',
                    'name' => 'Acme Admin',
                    'role' => 'Tenant Admin',
                    'scopes' => [
                        ['company_id' => $companyRetail->id, 'branch_id' => null],
                        ['company_id' => $companyWholesale->id, 'branch_id' => null],
                    ],
                ],
                [
                    'email' => 'branchmgr@acme.com',
                    'name' => 'Dhanmondi Branch Manager',
                    'role' => 'Branch Manager',
                    'scopes' => [
                        ['company_id' => $companyRetail->id, 'branch_id' => $branchDhanmondi->id],
                    ],
                ],
                [
                    'email' => 'supervisor@acme.com',
                    'name' => 'Dhanmondi Shift Supervisor',
                    'role' => 'Shift Supervisor',
                    'scopes' => [
                        ['company_id' => $companyRetail->id, 'branch_id' => $branchDhanmondi->id],
                    ],
                ],
                [
                    'email' => 'accountant@acme.com',
                    'name' => 'Lead Accountant',
                    'role' => 'Accountant',
                    'scopes' => [
                        ['company_id' => $companyRetail->id, 'branch_id' => null],
                        ['company_id' => $companyWholesale->id, 'branch_id' => null],
                    ],
                ],
                [
                    'email' => 'procurement@acme.com',
                    'name' => 'Procurement Manager',
                    'role' => 'Procurement Manager',
                    'scopes' => [
                        ['company_id' => $companyRetail->id, 'branch_id' => null],
                    ],
                ],
                [
                    'email' => 'salesmgr@acme.com',
                    'name' => 'Commercial Sales Manager',
                    'role' => 'Sales Manager',
                    'scopes' => [
                        ['company_id' => $companyRetail->id, 'branch_id' => null],
                        ['company_id' => $companyWholesale->id, 'branch_id' => null],
                    ],
                ],
                [
                    'email' => 'warehousemgr@acme.com',
                    'name' => 'Warehouse Manager',
                    'role' => 'Warehouse Manager',
                    'scopes' => [
                        ['company_id' => $companyRetail->id, 'branch_id' => $branchDhanmondi->id],
                    ],
                ],
                [
                    'email' => 'cashier@acme.com',
                    'name' => 'Dhanmondi POS Cashier',
                    'role' => 'Cashier',
                    'scopes' => [
                        ['company_id' => $companyRetail->id, 'branch_id' => $branchDhanmondi->id],
                    ],
                ],
                [
                    'email' => 'salesrep@acme.com',
                    'name' => 'Wholesale Sales Representative',
                    'role' => 'Sales Representative',
                    'scopes' => [
                        ['company_id' => $companyWholesale->id, 'branch_id' => $branchUttara->id],
                    ],
                ],
                [
                    'email' => 'auditor@acme.com',
                    'name' => 'External Compliance Auditor',
                    'role' => 'Auditor',
                    'scopes' => [
                        ['company_id' => $companyRetail->id, 'branch_id' => null],
                        ['company_id' => $companyWholesale->id, 'branch_id' => null],
                    ],
                ],
            ];

            foreach ($usersConfig as $cfg) {
                $user = User::firstOrCreate(
                    ['email' => $cfg['email']],
                    [
                        'id' => (string) Uuid::uuid7(),
                        'name' => $cfg['name'],
                        'password' => Hash::make('Password123!'),
                    ]
                );

                // Membership
                $membership = TenantMembership::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                    [
                        'id' => (string) Uuid::uuid7(),
                        'is_active' => true,
                    ]
                );

                // Assign Spatie Role in team
                $user->assignRole($cfg['role']);

                // Location Scopes
                foreach ($cfg['scopes'] as $scope) {
                    UserLocationScope::firstOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'membership_id' => $membership->id,
                            'company_id' => $scope['company_id'],
                            'branch_id' => $scope['branch_id'],
                        ],
                        [
                            'id' => (string) Uuid::uuid7(),
                        ]
                    );
                }
            }
        } finally {
            TenantContext::clear();
        }
    }
}
