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
            'name' => 'Catalog Consistency Tenant',
            'subdomain' => 'catcons-'.Str::random(6),
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

    public function test_product_cannot_reference_another_companys_category_on_insert_new_17(): void
    {
        $categoryAId = (string) Str::uuid();

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

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(fk_products_category|foreign key constraint|violates foreign key)/i');

        DB::table('products')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id, // Mismatched company
            'category_id' => $categoryAId,
            'code' => 'PRD-OIL-B1',
            'name_en' => 'Mustard Oil B',
            'name_bn' => 'সরিষার তেল বি',
            'base_uom' => 'Bottle',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_product_cannot_update_category_to_another_company_new_17(): void
    {
        $categoryAId = (string) Str::uuid();
        $categoryBId = (string) Str::uuid();
        $productId = (string) Str::uuid();

        DB::table('categories')->insert([
            ['id' => $categoryAId, 'tenant_id' => $this->tenant->id, 'company_id' => $this->companyA->id, 'code' => 'CAT-A', 'name_en' => 'Cat A', 'name_bn' => 'ক্যাট এ', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $categoryBId, 'tenant_id' => $this->tenant->id, 'company_id' => $this->companyB->id, 'code' => 'CAT-B', 'name_en' => 'Cat B', 'name_bn' => 'ক্যাট বি', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('products')->insert([
            'id' => $productId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'category_id' => $categoryAId,
            'code' => 'PRD-OIL-A2',
            'name_en' => 'Mustard Oil A',
            'name_bn' => 'সরিষার তেল এ',
            'base_uom' => 'Bottle',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(fk_products_category|foreign key constraint|violates foreign key)/i');

        // Attempt updating product A's category to category B
        DB::table('products')
            ->where('id', $productId)
            ->update(['category_id' => $categoryBId]);
    }

    public function test_variant_cannot_reference_another_companys_product_on_insert_new_17(): void
    {
        $productAId = (string) Str::uuid();
        $taxCodeBId = (string) Str::uuid();

        DB::table('products')->insert([
            'id' => $productAId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'code' => 'PRD-A-01',
            'name_en' => 'Product A',
            'name_bn' => 'পণ্য এ',
            'base_uom' => 'Piece',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tax_codes')->insert([
            'id' => $taxCodeBId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
            'code' => 'VAT-STD-B',
            'name_en' => 'VAT B',
            'name_bn' => 'মূসক বি',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(fk_variants_product|foreign key constraint|violates foreign key)/i');

        // Variant in Company B referencing Product in Company A
        DB::table('product_variants')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
            'product_id' => $productAId, // Mismatched company
            'tax_code_id' => $taxCodeBId,
            'sku' => 'SKU-CROSS-PROD',
            'name_en' => 'Cross Prod Variant',
            'name_bn' => 'ক্রস পণ্য ভ্যারিয়েন্ট',
            'base_uom' => 'Piece',
            'mrp' => 100.00,
            'cost_price' => 80.00,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_variant_cannot_reference_another_companys_tax_code_on_insert_new_20(): void
    {
        $productBId = (string) Str::uuid();
        $taxCodeAId = (string) Str::uuid();

        DB::table('products')->insert([
            'id' => $productBId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
            'code' => 'PRD-B-01',
            'name_en' => 'Product B',
            'name_bn' => 'পণ্য বি',
            'base_uom' => 'Piece',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tax_codes')->insert([
            'id' => $taxCodeAId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'code' => 'VAT-STD-A',
            'name_en' => 'VAT A',
            'name_bn' => 'মূসক এ',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(fk_variants_tax_code|foreign key constraint|violates foreign key)/i');

        DB::table('product_variants')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
            'product_id' => $productBId,
            'tax_code_id' => $taxCodeAId, // Mismatched Tax Code
            'sku' => 'SKU-CROSS-TAX',
            'name_en' => 'Cross Tax Variant',
            'name_bn' => 'ক্রস ট্যাক্স ভ্যারিয়েন্ট',
            'base_uom' => 'Piece',
            'mrp' => 200.00,
            'cost_price' => 150.00,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_barcode_cannot_reference_another_companys_variant_on_insert_new_17(): void
    {
        $productAId = (string) Str::uuid();
        $taxCodeAId = (string) Str::uuid();
        $variantAId = (string) Str::uuid();

        DB::table('tax_codes')->insert([
            'id' => $taxCodeAId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'code' => 'VAT-BARCODE-A',
            'name_en' => 'VAT Barcode A',
            'name_bn' => 'মূসক বারকোড এ',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('products')->insert([
            'id' => $productAId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'code' => 'PRD-BARCODE-A',
            'name_en' => 'Product Barcode A',
            'name_bn' => 'পণ্য বারকোড এ',
            'base_uom' => 'Piece',
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
            'sku' => 'VAR-BARCODE-A',
            'name_en' => 'Variant Barcode A',
            'name_bn' => 'ভ্যারিয়েন্ট বারকোড এ',
            'base_uom' => 'Piece',
            'mrp' => 50.00,
            'cost_price' => 40.00,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(fk_barcodes_variant|foreign key constraint|violates foreign key)/i');

        // Barcode in Company B referencing Variant in Company A
        DB::table('variant_barcodes')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id, // Mismatch company
            'variant_id' => $variantAId,
            'barcode' => '8909999999999',
            'uom' => 'Piece',
            'multiplier' => 1.0000,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_price_list_item_cannot_reference_another_companys_variant_on_insert_tax_07(): void
    {
        $priceListAId = (string) Str::uuid();
        $productBId = (string) Str::uuid();
        $taxCodeBId = (string) Str::uuid();
        $variantBId = (string) Str::uuid();

        DB::table('price_lists')->insert([
            'id' => $priceListAId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'code' => 'PL-A-01',
            'name_en' => 'Price List A',
            'name_bn' => 'মূল্য তালিকা এ',
            'currency' => 'BDT',
            'is_tax_inclusive' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tax_codes')->insert([
            'id' => $taxCodeBId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
            'code' => 'VAT-PLI-B',
            'name_en' => 'VAT PLI B',
            'name_bn' => 'মূসক বি',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('products')->insert([
            'id' => $productBId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
            'code' => 'PRD-PLI-B',
            'name_en' => 'Product PLI B',
            'name_bn' => 'পণ্য বি',
            'base_uom' => 'Piece',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_variants')->insert([
            'id' => $variantBId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
            'product_id' => $productBId,
            'tax_code_id' => $taxCodeBId,
            'sku' => 'VAR-PLI-B',
            'name_en' => 'Variant PLI B',
            'name_bn' => 'ভ্যারিয়েন্ট বি',
            'base_uom' => 'Piece',
            'mrp' => 120.00,
            'cost_price' => 100.00,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(fk_price_items_variant|foreign key constraint|violates foreign key)/i');

        // Price list item under Company A referencing Variant B
        DB::table('price_list_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'price_list_id' => $priceListAId,
            'variant_id' => $variantBId, // Mismatched Variant Company
            'unit_price' => 110.00,
            'min_quantity' => 1.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_category_cannot_reference_another_companys_parent_category_new_17(): void
    {
        $categoryAId = (string) Str::uuid();

        DB::table('categories')->insert([
            'id' => $categoryAId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'code' => 'CAT-PARENT-A',
            'name_en' => 'Parent A',
            'name_bn' => 'প্যারেন্ট এ',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(fk_categories_parent|foreign key constraint|violates foreign key)/i');

        // Subcategory under Company B referencing Parent Category A
        DB::table('categories')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
            'parent_id' => $categoryAId, // Mismatched Parent
            'code' => 'CAT-CHILD-B',
            'name_en' => 'Child B',
            'name_bn' => 'চাইল্ড বি',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_same_company_hierarchy_and_catalog_relations_allowed_new_17_tax_07(): void
    {
        $parentCatAId = (string) Str::uuid();
        $childCatAId = (string) Str::uuid();
        $taxCodeAId = (string) Str::uuid();
        $productAId = (string) Str::uuid();
        $variantAId = (string) Str::uuid();
        $priceListAId = (string) Str::uuid();

        // 1. Parent & Child Category
        DB::table('categories')->insert([
            'id' => $parentCatAId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'code' => 'CAT-P-OK',
            'name_en' => 'Parent OK',
            'name_bn' => 'প্যারেন্ট ঠিক',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('categories')->insert([
            'id' => $childCatAId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'parent_id' => $parentCatAId,
            'code' => 'CAT-C-OK',
            'name_en' => 'Child OK',
            'name_bn' => 'চাইল্ড ঠিক',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Tax Code
        DB::table('tax_codes')->insert([
            'id' => $taxCodeAId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'code' => 'VAT-OK',
            'name_en' => 'VAT OK',
            'name_bn' => 'মূসক ঠিক',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Product
        DB::table('products')->insert([
            'id' => $productAId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'category_id' => $childCatAId,
            'code' => 'PRD-OK',
            'name_en' => 'Product OK',
            'name_bn' => 'পণ্য ঠিক',
            'base_uom' => 'Bottle',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Variant
        DB::table('product_variants')->insert([
            'id' => $variantAId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'product_id' => $productAId,
            'tax_code_id' => $taxCodeAId,
            'sku' => 'SKU-OK',
            'name_en' => 'Variant OK',
            'name_bn' => 'ভ্যারিয়েন্ট ঠিক',
            'base_uom' => 'Bottle',
            'mrp' => 300.00,
            'cost_price' => 250.00,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. Barcodes (Piece & Carton)
        DB::table('variant_barcodes')->insert([
            ['id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->id, 'company_id' => $this->companyA->id, 'variant_id' => $variantAId, 'barcode' => '8901111111111', 'uom' => 'Piece', 'multiplier' => 1.0000, 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->id, 'company_id' => $this->companyA->id, 'variant_id' => $variantAId, 'barcode' => '18901111111118', 'uom' => 'Carton', 'multiplier' => 12.0000, 'is_primary' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 6. Price List & Item
        DB::table('price_lists')->insert([
            'id' => $priceListAId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'code' => 'PL-OK',
            'name_en' => 'Price List OK',
            'name_bn' => 'মূল্য তালিকা ঠিক',
            'currency' => 'BDT',
            'is_tax_inclusive' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('price_list_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
            'price_list_id' => $priceListAId,
            'variant_id' => $variantAId,
            'unit_price' => 295.00,
            'min_quantity' => 1.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('price_list_items', [
            'price_list_id' => $priceListAId,
            'variant_id' => $variantAId,
            'unit_price' => 295.0000,
        ]);
        $this->assertSame(2, DB::table('variant_barcodes')->where('variant_id', $variantAId)->count());
    }
}
