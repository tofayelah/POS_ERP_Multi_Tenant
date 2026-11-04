<?php

namespace Tests\Unit;

use App\Core\Tenancy\TenantContext;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\VariantBarcode;
use App\Modules\Catalog\Contracts\BarcodeResolverContract;
use App\Modules\Catalog\Exceptions\BarcodeNotFoundException;
use App\Modules\Catalog\Services\BarcodeResolver;
use Illuminate\Support\Str;
use Tests\TestCase;

class BarcodeResolverTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected ProductVariant $variant;

    protected BarcodeResolverContract $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Barcode Test Tenant',
            'subdomain' => 'barcode-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Barcode Test Co',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $taxCode = TaxCode::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'VAT-5',
            'name_en' => 'VAT 5%',
            'name_bn' => 'মূসক ৫%',
            'is_active' => true,
        ]);

        $category = Category::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'CAT-OIL',
            'name_en' => 'Edible Oil',
            'name_bn' => 'ভোজ্য তেল',
            'is_active' => true,
        ]);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'category_id' => $category->id,
            'code' => 'PRD-MUSTARD-OIL',
            'name_en' => 'Pure Mustard Oil 1L',
            'name_bn' => 'খাঁটি সরিষার তেল ১ লিটার',
            'base_uom' => 'Bottle',
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'tax_code_id' => $taxCode->id,
            'sku' => 'OIL-MUST-1L',
            'name_en' => 'Pure Mustard Oil 1L',
            'name_bn' => 'খাঁটি সরিষার তেল ১ লিটার',
            'base_uom' => 'Bottle',
            'mrp' => 280.00,
            'cost_price' => 240.00,
            'is_active' => true,
        ]);

        // Piece barcode: 1 bottle
        VariantBarcode::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'variant_id' => $this->variant->id,
            'barcode' => '8901234567890',
            'uom' => 'Piece',
            'multiplier' => '1.0000',
            'is_primary' => true,
        ]);

        // Carton barcode: 12 bottles
        VariantBarcode::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'variant_id' => $this->variant->id,
            'barcode' => '18901234567897',
            'uom' => 'Carton',
            'multiplier' => '12.0000',
            'is_primary' => false,
        ]);

        $this->resolver = new BarcodeResolver;
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_resolves_piece_barcode_with_unit_multiplier(): void
    {
        $resolution = $this->resolver->resolve('8901234567890', $this->company->id);

        $this->assertSame($this->variant->id, $resolution->variant->id);
        $this->assertSame('Piece', $resolution->uom);
        $this->assertSame('1.0000', $resolution->multiplier);
        $this->assertTrue($resolution->isPrimary);
    }

    public function test_resolves_carton_barcode_with_twelve_multiplier(): void
    {
        $resolution = $this->resolver->resolve('18901234567897', $this->company->id);

        $this->assertSame($this->variant->id, $resolution->variant->id);
        $this->assertSame('Carton', $resolution->uom);
        $this->assertSame('12.0000', $resolution->multiplier);
        $this->assertFalse($resolution->isPrimary);
    }

    public function test_throws_exception_on_unrecognized_barcode(): void
    {
        $this->expectException(BarcodeNotFoundException::class);

        $this->resolver->resolve('0000000000000', $this->company->id);
    }
}
