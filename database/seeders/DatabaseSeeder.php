<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
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

        $warehouse = Warehouse::firstOrCreate(
            ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => 'WH-DHA01'],
            [
                'branch_id' => $branch->id,
                'name' => 'Dhanmondi Central Hub Warehouse',
                'address' => 'House 42 (Basement), Road 7/A, Dhanmondi, Dhaka',
                'is_active' => true,
            ]
        );

        // 4. Create Sample FMCG & Retail Suppliers
        $suppliersData = [
            'SUP-MGI-01' => [
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
            'SUP-PRAN-02' => [
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
            'SUP-ACI-03' => [
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

        $suppliers = [];
        foreach ($suppliersData as $code => $data) {
            $suppliers[$code] = Supplier::firstOrCreate(
                ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => $code],
                $data
            );
        }

        // 5. Units of Measure (UOM)
        $uomsData = [
            'PCS' => ['name' => 'Pieces', 'code' => 'PCS', 'symbol' => 'pcs', 'allow_decimal' => false],
            'KG' => ['name' => 'Kilogram', 'code' => 'KG', 'symbol' => 'kg', 'allow_decimal' => true],
            'LTR' => ['name' => 'Liter', 'code' => 'LTR', 'symbol' => 'L', 'allow_decimal' => true],
            'BOX' => ['name' => 'Box / Carton', 'code' => 'BOX', 'symbol' => 'bx', 'allow_decimal' => false],
            'DOZEN' => ['name' => 'Dozen', 'code' => 'DOZEN', 'symbol' => 'dz', 'allow_decimal' => false],
            'PKT' => ['name' => 'Packet', 'code' => 'PKT', 'symbol' => 'pkt', 'allow_decimal' => false],
        ];

        $uoms = [];
        foreach ($uomsData as $code => $data) {
            $uoms[$code] = UnitOfMeasure::firstOrCreate(
                ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => $code],
                array_merge($data, ['is_active' => true])
            );
        }

        // 6. Brands
        $brandsData = [
            'BR-FRESH' => ['name' => 'Fresh', 'code' => 'BR-FRESH', 'origin_country' => 'BD', 'description' => 'Meghna Group flagship consumer food brand'],
            'BR-PRAN' => ['name' => 'Pran', 'code' => 'BR-PRAN', 'origin_country' => 'BD', 'description' => 'Largest food processing & beverage company in Bangladesh'],
            'BR-ACI' => ['name' => 'ACI Pure & Savlon', 'code' => 'BR-ACI', 'origin_country' => 'BD', 'description' => 'Household hygiene, salt, flour, and antiseptic products'],
            'BR-AARONG' => ['name' => 'Aarong Dairy', 'code' => 'BR-AARONG', 'origin_country' => 'BD', 'description' => 'BRAC enterprise providing milk, butter, ghee and dairy products'],
            'BR-RADHUNI' => ['name' => 'Radhuni', 'code' => 'BR-RADHUNI', 'origin_country' => 'BD', 'description' => 'Square Consumer Products flagship spices & culinary mixes'],
            'BR-ULBD' => ['name' => 'Unilever Bangladesh', 'code' => 'BR-ULBD', 'origin_country' => 'BD', 'description' => 'Multinational personal care and home care leader'],
        ];

        $brands = [];
        foreach ($brandsData as $code => $data) {
            $brands[$code] = Brand::firstOrCreate(
                ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => $code],
                array_merge($data, ['is_active' => true])
            );
        }

        // 7. Categories (with hierarchy)
        $catGroceries = Category::firstOrCreate(
            ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => 'CAT-GROC'],
            [
                'name_en' => 'Groceries & Staples',
                'name_bn' => 'মুদি সামগ্রী ও নিত্যপ্রয়োজনীয়',
                'description' => 'Rice, flour, lentils, cooking oil, spices, and sugar',
                'is_active' => true,
            ]
        );

        $catOils = Category::firstOrCreate(
            ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => 'CAT-OIL'],
            [
                'parent_id' => $catGroceries->id,
                'name_en' => 'Edible Oils & Ghee',
                'name_bn' => 'ভোজ্য তেল ও ঘি',
                'description' => 'Soyabean oil, mustard oil, sunflower oil, and pure ghee',
                'is_active' => true,
            ]
        );

        $catSpices = Category::firstOrCreate(
            ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => 'CAT-SPICE'],
            [
                'parent_id' => $catGroceries->id,
                'name_en' => 'Spices & Culinary Mixes',
                'name_bn' => 'মসলা ও রান্নার উপকরণ',
                'description' => 'Turmeric, chili, coriander powder, cumin, meat curry mixes',
                'is_active' => true,
            ]
        );

        $catDairy = Category::firstOrCreate(
            ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => 'CAT-DAIRY'],
            [
                'name_en' => 'Dairy & Beverages',
                'name_bn' => 'দুগ্ধ ও পানীয়',
                'description' => 'Liquid milk, yogurt, juices, carbonated drinks, tea, coffee',
                'is_active' => true,
            ]
        );

        $catHygiene = Category::firstOrCreate(
            ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => 'CAT-HYGIENE'],
            [
                'name_en' => 'Personal Care & Hygiene',
                'name_bn' => 'ব্যক্তিগত পরিচর্যা ও পরিচ্ছন্নতা',
                'description' => 'Soaps, handwashes, sanitizers, shampoos, toothpaste',
                'is_active' => true,
            ]
        );

        // 8. Sample Products & Variants
        // Product 1: Fresh Refined Soyabean Oil 1L
        $prdOil = Product::firstOrCreate(
            ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => 'PRD-OIL-FRSH-1L'],
            [
                'name_en' => 'Fresh Refined Soyabean Oil 1L',
                'name_bn' => 'ফ্রেশ পরিশোধিত সয়াবিন তেল ১ লিটার',
                'category_id' => $catOils->id,
                'brand_id' => $brands['BR-FRESH']->id,
                'uom_id' => $uoms['LTR']->id,
                'supplier_id' => $suppliers['SUP-MGI-01']->id,
                'description' => 'Vitamin A enriched double-refined soyabean oil in 1L pet bottle.',
                'has_variants' => false,
                'is_active' => true,
            ]
        );

        ProductVariant::firstOrCreate(
            ['tenant_id' => $tenant->id, 'sku' => 'FRSH-OIL-1L'],
            [
                'company_id' => $company->id,
                'product_id' => $prdOil->id,
                'barcode' => '8941100234574',
                'variant_name' => '1 Liter Pet Bottle',
                'purchase_price' => 152.0000,
                'mrp_price' => 167.0000,
                'wholesale_price' => 158.0000,
                'tax_rate' => 5.00,
                'is_tax_inclusive' => true,
                'reorder_level' => 24.0000,
                'is_active' => true,
            ]
        );

        // Product 2: Radhuni Turmeric Powder (Multi-variant)
        $prdTurm = Product::firstOrCreate(
            ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => 'PRD-RAD-TURM'],
            [
                'name_en' => 'Radhuni Turmeric Powder',
                'name_bn' => 'রাঁধুনী হলুদ গুঁড়া',
                'category_id' => $catSpices->id,
                'brand_id' => $brands['BR-RADHUNI']->id,
                'uom_id' => $uoms['PKT']->id,
                'supplier_id' => $suppliers['SUP-PRAN-02']->id,
                'description' => 'Finest natural sun-dried turmeric ground to perfection.',
                'has_variants' => true,
                'is_active' => true,
            ]
        );

        ProductVariant::firstOrCreate(
            ['tenant_id' => $tenant->id, 'sku' => 'RAD-TURM-200G'],
            [
                'company_id' => $company->id,
                'product_id' => $prdTurm->id,
                'barcode' => '8941100234581',
                'variant_name' => '200g Foil Pack',
                'purchase_price' => 65.0000,
                'mrp_price' => 80.0000,
                'wholesale_price' => 72.0000,
                'tax_rate' => 5.00,
                'is_tax_inclusive' => true,
                'reorder_level' => 30.0000,
                'is_active' => true,
            ]
        );

        ProductVariant::firstOrCreate(
            ['tenant_id' => $tenant->id, 'sku' => 'RAD-TURM-500G'],
            [
                'company_id' => $company->id,
                'product_id' => $prdTurm->id,
                'barcode' => '8941100234598',
                'variant_name' => '500g Pet Jar',
                'purchase_price' => 155.0000,
                'mrp_price' => 185.0000,
                'wholesale_price' => 170.0000,
                'tax_rate' => 5.00,
                'is_tax_inclusive' => true,
                'reorder_level' => 15.0000,
                'is_active' => true,
            ]
        );

        // Product 3: Savlon Antiseptic Soap 125g
        $prdSoap = Product::firstOrCreate(
            ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => 'PRD-SAVLON-SOAP'],
            [
                'name_en' => 'Savlon Active Fresh Antiseptic Soap 125g',
                'name_bn' => 'স্যাভলন এক্টিভ ফ্রেশ অ্যান্টিসেপ্টিক সাবান ১২৫ গ্রাম',
                'category_id' => $catHygiene->id,
                'brand_id' => $brands['BR-ACI']->id,
                'uom_id' => $uoms['PCS']->id,
                'supplier_id' => $suppliers['SUP-ACI-03']->id,
                'description' => '99.9% germ protection herbal antiseptic bath soap.',
                'has_variants' => false,
                'is_active' => true,
            ]
        );

        ProductVariant::firstOrCreate(
            ['tenant_id' => $tenant->id, 'sku' => 'SAVLON-ACT-125G'],
            [
                'company_id' => $company->id,
                'product_id' => $prdSoap->id,
                'barcode' => '8941100234604',
                'variant_name' => 'Active Fresh 125g Bar',
                'purchase_price' => 45.0000,
                'mrp_price' => 55.0000,
                'wholesale_price' => 49.0000,
                'tax_rate' => 5.00,
                'is_tax_inclusive' => true,
                'reorder_level' => 48.0000,
                'is_active' => true,
            ]
        );

        // Product 4: Aarong Pasteurised Liquid Milk 1L
        $prdMilk = Product::firstOrCreate(
            ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => 'PRD-AARONG-MILK1L'],
            [
                'name_en' => 'Aarong Pasteurised Liquid Milk 1L',
                'name_bn' => 'আড়ং পাস্তুরিত তরল দুধ ১ লিটার',
                'category_id' => $catDairy->id,
                'brand_id' => $brands['BR-AARONG']->id,
                'uom_id' => $uoms['PKT']->id,
                'supplier_id' => $suppliers['SUP-PRAN-02']->id,
                'description' => 'Full cream 3.5% fat pasteurised fresh milk in poly pouch.',
                'has_variants' => false,
                'is_active' => true,
            ]
        );

        ProductVariant::firstOrCreate(
            ['tenant_id' => $tenant->id, 'sku' => 'AARONG-MILK-1L'],
            [
                'company_id' => $company->id,
                'product_id' => $prdMilk->id,
                'barcode' => '8941100234611',
                'variant_name' => 'Poly Pack 1L',
                'purchase_price' => 82.0000,
                'mrp_price' => 90.0000,
                'wholesale_price' => 85.0000,
                'tax_rate' => 0.00, // Essential dairy exempt from VAT
                'is_tax_inclusive' => true,
                'reorder_level' => 20.0000,
                'is_active' => true,
            ]
        );
    }
}
