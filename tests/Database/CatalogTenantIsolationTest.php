<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\Category;
use App\Models\Company;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxCode;
use App\Models\TaxCodeRate;
use App\Models\Tenant;
use App\Models\UnitConversion;
use App\Models\VariantBarcode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogTenantIsolationTest extends TestCase
{
    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected Company $companyA;

    protected Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant A',
            'subdomain' => 'tenant-a-'.Str::random(6),
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant B',
            'subdomain' => 'tenant-b-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenantA);

        $this->companyA = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'legal_name' => 'Company A',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        TenantContext::set($this->tenantB);

        $this->companyB = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
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

    public function test_tenant_b_cannot_see_any_catalog_or_pricing_rows_of_tenant_a(): void
    {
        // 1. Populate full catalog stack under Tenant A
        TenantContext::set($this->tenantA);

        $taxCodeA = TaxCode::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'code' => 'VAT-STD',
            'name_en' => 'Standard VAT',
            'name_bn' => 'আদর্শ মূসক',
            'is_active' => true,
        ]);

        TaxCodeRate::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'tax_code_id' => $taxCodeA->id,
            'rate_percent' => '15.00',
            'supplementary_duty_percent' => '0.00',
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);

        $categoryA = Category::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'code' => 'CAT-OIL',
            'name_en' => 'Oil',
            'name_bn' => 'তেল',
            'is_active' => true,
        ]);

        $productA = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'category_id' => $categoryA->id,
            'code' => 'PRD-OIL-1',
            'name_en' => 'Mustard Oil',
            'name_bn' => 'সরিষার তেল',
            'base_uom' => 'Bottle',
            'is_active' => true,
        ]);

        $variantA = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'product_id' => $productA->id,
            'tax_code_id' => $taxCodeA->id,
            'sku' => 'SKU-OIL-1L',
            'name_en' => 'Mustard Oil 1L',
            'name_bn' => 'সরিষার তেল ১ লিটার',
            'base_uom' => 'Bottle',
            'mrp' => '280.0000',
            'cost_price' => '240.0000',
            'is_active' => true,
        ]);

        VariantBarcode::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'variant_id' => $variantA->id,
            'barcode' => '8901234567890',
            'uom' => 'Piece',
            'multiplier' => '1.0000',
            'is_primary' => true,
        ]);

        UnitConversion::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'from_uom' => 'Carton',
            'to_uom' => 'Piece',
            'multiplier' => '12.0000',
        ]);

        $priceListA = PriceList::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'code' => 'RETAIL',
            'name_en' => 'Retail',
            'currency' => 'BDT',
            'is_tax_inclusive' => true,
            'is_active' => true,
        ]);

        PriceListItem::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'price_list_id' => $priceListA->id,
            'variant_id' => $variantA->id,
            'unit_price' => '280.0000',
            'min_quantity' => '1.0000',
        ]);

        // 2. Switch to Tenant B
        TenantContext::set($this->tenantB);

        // Assert all 9 tables return ZERO rows for Tenant B
        $this->assertSame(0, TaxCode::count());
        $this->assertSame(0, TaxCodeRate::count());
        $this->assertSame(0, Category::count());
        $this->assertSame(0, Product::count());
        $this->assertSame(0, ProductVariant::count());
        $this->assertSame(0, VariantBarcode::count());
        $this->assertSame(0, UnitConversion::count());
        $this->assertSame(0, PriceList::count());
        $this->assertSame(0, PriceListItem::count());
    }

    public function test_tenant_b_cannot_insert_rows_with_tenant_a_id(): void
    {
        TenantContext::set($this->tenantB);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(violates row-level security policy|42501)/i');

        // Attempting to spoof tenant_id of Tenant A while running under Tenant B's context
        DB::table('categories')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id, // Mismatch with app.current_tenant_id!
            'company_id' => $this->companyB->id,
            'code' => 'SPOOF-CAT',
            'name_en' => 'Spoofed Category',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
