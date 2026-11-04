<?php

namespace Tests\Unit;

use App\Core\Tenancy\TenantContext;
use App\Models\Category;
use App\Models\Company;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Modules\Catalog\Exceptions\MrpCeilingExceededException;
use App\Modules\Catalog\Rules\MrpCeilingRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class MrpCeilingRuleTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected ProductVariant $variant;

    protected PriceList $retailPriceList;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Price Test Tenant',
            'subdomain' => 'price-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Pricing Test Co',
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
            'mrp' => '280.0000',
            'cost_price' => '240.0000',
            'is_active' => true,
        ]);

        $this->retailPriceList = PriceList::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'RETAIL-DEFAULT',
            'name_en' => 'Retail Default Price List',
            'name_bn' => 'খুচরা মূল্য তালিকা',
            'currency' => 'BDT',
            'is_tax_inclusive' => true,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_price_equal_to_mrp_is_accepted(): void
    {
        $rule = new MrpCeilingRule($this->variant);
        $validator = Validator::make(['price' => '280.0000'], ['price' => [$rule]]);

        $this->assertFalse($validator->fails());

        $item = PriceListItem::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'price_list_id' => $this->retailPriceList->id,
            'variant_id' => $this->variant->id,
            'unit_price' => '280.0000',
            'min_quantity' => '1.0000',
        ]);

        $this->assertSame('280.0000', (string) $item->unit_price);
    }

    public function test_price_below_mrp_is_accepted(): void
    {
        $rule = new MrpCeilingRule($this->variant);
        $validator = Validator::make(['price' => '275.5000'], ['price' => [$rule]]);

        $this->assertFalse($validator->fails());
    }

    public function test_price_above_mrp_is_rejected_by_rule(): void
    {
        $rule = new MrpCeilingRule($this->variant);
        $validator = Validator::make(['price' => '280.0100'], ['price' => [$rule]]);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('cannot exceed the Maximum Retail Price', $validator->errors()->first('price'));
    }

    public function test_price_list_item_model_blocks_price_exceeding_mrp(): void
    {
        $this->expectException(MrpCeilingExceededException::class);

        PriceListItem::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'price_list_id' => $this->retailPriceList->id,
            'variant_id' => $this->variant->id,
            'unit_price' => '280.5000',
            'min_quantity' => '1.0000',
        ]);
    }
}
