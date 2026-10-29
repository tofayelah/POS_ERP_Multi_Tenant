<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Warehouse;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Default Tenant
        $tenant = Tenant::firstOrCreate(
            ['subdomain' => 'sonarmart'],
            [
                'name' => 'Sonar Bangla Mart',
                'status' => 'active',
                'plan' => 'business',
            ]
        );

        // 2. Create Default Company
        $company = Company::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Sonar Mart Ltd'],
            [
                'legal_name' => 'Sonar Mart Limited PLC',
                'bin_number' => '000184920-0101',
                'tin_number' => '102938475612',
                'currency' => 'BDT',
                'country_code' => 'BD',
                'fiscal_year_start_month' => 7,
                'is_active' => true,
            ]
        );

        // 3. Create Default Branch & Warehouse
        $branch = Branch::firstOrCreate(
            ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => 'DHA-01'],
            [
                'name' => 'Dhanmondi Outlet #1',
                'phone' => '+8801700112233',
                'address' => 'House 42, Road 7/A, Dhanmondi R/A, Dhaka',
                'district' => 'Dhaka',
                'is_active' => true,
            ]
        );

        Warehouse::firstOrCreate(
            ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => 'WH-DHA01'],
            [
                'branch_id' => $branch->id,
                'name' => 'Dhanmondi Central Hub Warehouse',
                'address' => 'House 42 (Basement), Road 7/A, Dhanmondi, Dhaka',
                'is_active' => true,
            ]
        );

        // 4. Create Sample FMCG & Retail Suppliers
        $suppliers = [
            [
                'name' => 'Meghna Group of Industries (MGI)',
                'code' => 'SUP-MGI-01',
                'contact_person' => 'Rafiqul Islam',
                'phone' => '+8801711234567',
                'email' => 'sales@meghnagroup.biz',
                'address' => 'Fresh Villa, House 15, Road 34, Gulshan-1, Dhaka',
                'city' => 'Dhaka',
                'bin_number' => '000100234-0101',
                'tax_number' => '100234567890',
                'vat_reg_number' => 'VAT-MGI-2026',
                'bank_name' => 'BRAC Bank PLC',
                'bank_account_name' => 'Meghna Group of Industries',
                'bank_account_number' => '1501201948291001',
                'bank_branch_name' => 'Gulshan Corporate Branch',
                'bank_routing_number' => '060261234',
                'mfs_provider' => 'bKash',
                'mfs_account_number' => '01711234567',
                'internal_notes' => 'Primary FMCG commodity supplier (Oil, Flour, Sugar). Payment terms: Net 15 days.',
                'additional_notes' => 'Authorized distributor contract signed for 2026-2027.',
                'is_active' => true,
            ],
            [
                'name' => 'Pran-RFL Group',
                'code' => 'SUP-PRAN-02',
                'contact_person' => 'Kamal Hossain',
                'phone' => '+8801819876543',
                'email' => 'orders@pranfoods.net',
                'address' => 'PRAN-RFL Center, 105 Middle Badda, Dhaka',
                'city' => 'Dhaka',
                'bin_number' => '000145678-0201',
                'tax_number' => '145678901234',
                'vat_reg_number' => 'VAT-PRAN-2026',
                'bank_name' => 'The City Bank PLC',
                'bank_account_name' => 'Pran Dairy & Beverage Ltd',
                'bank_account_number' => '2101948271001',
                'bank_branch_name' => 'Badda Branch',
                'bank_routing_number' => '225271234',
                'mfs_provider' => 'Nagad',
                'mfs_account_number' => '01819876543',
                'internal_notes' => 'Packaged juice, confectionery, snacks, and dairy supplier. Weekly delivery schedule.',
                'additional_notes' => 'Tier-1 wholesale discount: 8.5% on bulk orders over BDT 50,000.',
                'is_active' => true,
            ],
            [
                'name' => 'ACI Consumer Brands',
                'code' => 'SUP-ACI-03',
                'contact_person' => 'Tanvir Chowdhury',
                'phone' => '+8801911456789',
                'email' => 'supplychain@aci-bd.com',
                'address' => 'ACI Centre, 245 Tejgaon Industrial Area, Dhaka',
                'city' => 'Dhaka',
                'bin_number' => '000198765-0301',
                'tax_number' => '198765432109',
                'vat_reg_number' => 'VAT-ACI-2026',
                'bank_name' => 'Eastern Bank PLC',
                'bank_account_name' => 'Advanced Chemical Industries Ltd',
                'bank_account_number' => '1041060281928',
                'bank_branch_name' => 'Tejgaon Industrial Branch',
                'bank_routing_number' => '095261234',
                'mfs_provider' => 'bKash',
                'mfs_account_number' => '01911456789',
                'internal_notes' => 'Hygiene, aerosol, toiletries, and antiseptic products vendor.',
                'additional_notes' => 'Return policy: Damaged / near-expiry items accepted 60 days before expiration.',
                'is_active' => true,
            ],
        ];

        foreach ($suppliers as $supplierData) {
            Supplier::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'company_id' => $company->id,
                    'code' => $supplierData['code'],
                ],
                $supplierData
            );
        }
    }
}
