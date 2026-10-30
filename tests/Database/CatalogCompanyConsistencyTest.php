<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogCompanyConsistencyTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $companyA;

    protected Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Catalog Test Tenant',
            'subdomain' => 'catalog-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->companyA = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Company A',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->companyB = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Company B',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_product_cannot_reference_another_companys_category(): void
    {
        $categoryAId = (string) Str::uuid();

        // 1. Create Category under Company A
        DB::table('categories')->insert([
            'id' => $categoryAId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'code' => 'CAT-EDIBLE-OIL',
            'name_en' => 'Edible Oil',
            'name_bn' => 'ভোজ্য তেল',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Attempt to create Product under Company B referencing Category A
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(fk_products_category|foreign key constraint|violates foreign key)/i');

        DB::table('products')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id, // Mismatch company!
            'category_id' => $categoryAId,
            'code' => 'PRD-OIL-01',
            'name_en' => 'Pure Mustard Oil',
            'name_bn' => 'খাঁটি সরিষার তেল',
            'base_uom' => 'Litre',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_variant_cannot_reference_another_companys_tax_code(): void
    {
        $categoryBId = (string) Str::uuid();
        $productBId = (string) Str::uuid();
        $taxCodeAId = (string) Str::uuid();

        // Category & Product under Company B
        DB::table('categories')->insert([
            'id' => $categoryBId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
            'code' => 'CAT-GROCERY-B',
            'name_en' => 'Grocery',
            'name_bn' => 'মুদিখানা',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('products')->insert([
            'id' => $productBId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
            'category_id' => $categoryBId,
            'code' => 'PRD-OIL-B',
            'name_en' => 'Pure Mustard Oil 1L',
            'name_bn' => 'খাঁটি সরিষার তেল ১ লিটার',
            'base_uom' => 'Bottle',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tax Code under Company A
        DB::table('tax_codes')->insert([
            'id' => $taxCodeAId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'code' => 'VAT-REDUCED-5',
            'name_en' => 'Reduced VAT 5%',
            'name_bn' => 'হ্রাসকৃত মূসক ৫%',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempt to create Variant under Company B referencing Tax Code A
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(fk_variants_tax_code|foreign key constraint|violates foreign key)/i');

        DB::table('product_variants')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
            'product_id' => $productBId,
            'tax_code_id' => $taxCodeAId, // Mismatched Tax Code!
            'sku' => 'OIL-MUST-1L',
            'name_en' => 'Pure Mustard Oil 1L',
            'name_bn' => 'খাঁটি সরিষার তেল ১ লিটার',
            'base_uom' => 'Bottle',
            'mrp' => 280.00,
            'cost_price' => 240.00,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_same_company_product_and_variant_succeed(): void
    {
        $categoryAId = (string) Str::uuid();
        $taxCodeAId = (string) Str::uuid();
        $productAId = (string) Str::uuid();
        $variantAId = (string) Str::uuid();

        DB::table('categories')->insert([
            'id' => $categoryAId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'code' => 'CAT-OIL-A',
            'name_en' => 'Edible Oil',
            'name_bn' => 'ভোজ্য তেল',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tax_codes')->insert([
            'id' => $taxCodeAId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'code' => 'VAT-5-A',
            'name_en' => 'VAT 5%',
            'name_bn' => 'মূসক ৫%',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('products')->insert([
            'id' => $productAId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'category_id' => $categoryAId,
            'code' => 'PRD-OIL-01A',
            'name_en' => 'Pure Mustard Oil',
            'name_bn' => 'খাঁটি সরিষার তেল',
            'base_uom' => 'Bottle',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_variants')->insert([
            'id' => $variantAId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'product_id' => $productAId,
            'tax_code_id' => $taxCodeAId,
            'sku' => 'OIL-MUST-1L-A',
            'name_en' => 'Pure Mustard Oil 1L',
            'name_bn' => 'খাঁটি সরিষার তেল ১ লিটার',
            'base_uom' => 'Bottle',
            'mrp' => 280.00,
            'cost_price' => 240.00,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variantAId,
            'name_bn' => 'খাঁটি সরিষার তেল ১ লিটার',
            'mrp' => 280.0000,
        ]);
    }
}
