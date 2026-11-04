<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Modules\Catalog\Exceptions\MrpCeilingExceededException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MrpCeilingDatabaseTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected TaxCode $taxCode;

    protected Product $product;

    protected ProductVariant $variant;

    protected PriceList $priceList;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'MRP Test Tenant',
            'subdomain' => 'mrptest-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'MRP Test Co',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->taxCode = TaxCode::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'VAT-STD-15',
            'name_en' => 'Standard VAT 15%',
            'name_bn' => 'আদর্শ মূসক ১৫%',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'PRD-MUSTARD-OIL',
            'name_en' => 'Pure Mustard Oil 1L',
            'name_bn' => 'খাঁটি সরিষার তেল ১ লিটার',
            'base_uom' => 'Bottle',
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'tax_code_id' => $this->taxCode->id,
            'sku' => 'OIL-MUST-1L',
            'name_en' => 'Pure Mustard Oil 1L',
            'name_bn' => 'খাঁটি সরিষার তেল ১ লিটার',
            'base_uom' => 'Bottle',
            'mrp' => '280.0000',
            'cost_price' => '240.0000',
            'is_active' => true,
        ]);

        $this->priceList = PriceList::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'RETAIL-DEFAULT',
            'name_en' => 'Retail Default',
            'name_bn' => 'খুচরা ডিফল্ট',
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

    public function test_raw_insert_price_list_item_above_variant_mrp_rejected_by_trigger_tax_07(): void
    {
        // Variant MRP is ৳280. Attempt raw insert at ৳280.01
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(exceeds variant MRP|check_violation|23514)/i');

        DB::table('price_list_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'price_list_id' => $this->priceList->id,
            'variant_id' => $this->variant->id,
            'unit_price' => 280.0100, // Exceeds MRP 280.00
            'min_quantity' => 1.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_raw_update_price_list_item_price_above_variant_mrp_rejected_by_trigger_tax_07(): void
    {
        $itemId = (string) Str::uuid();

        // Insert valid price item at ৳250
        DB::table('price_list_items')->insert([
            'id' => $itemId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'price_list_id' => $this->priceList->id,
            'variant_id' => $this->variant->id,
            'unit_price' => 250.0000,
            'min_quantity' => 1.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempt updating price to ৳300 (above MRP 280)
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(exceeds variant MRP|check_violation|23514)/i');

        DB::table('price_list_items')
            ->where('id', $itemId)
            ->update(['unit_price' => 300.0000]);
    }

    public function test_raw_update_price_list_item_variant_to_variant_with_lower_mrp_rejected_tax_07(): void
    {
        // Create second variant with lower MRP of ৳200
        $lowMrpVariant = ProductVariant::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'tax_code_id' => $this->taxCode->id,
            'sku' => 'OIL-MUST-500ML',
            'name_en' => 'Pure Mustard Oil 500ml',
            'name_bn' => 'খাঁটি সরিষার তেল ৫০০ মি.লি.',
            'base_uom' => 'Bottle',
            'mrp' => '200.0000',
            'cost_price' => '160.0000',
            'is_active' => true,
        ]);

        $itemId = (string) Str::uuid();
        // Insert price item for 1L variant at ৳250 (valid for 1L, but > 200)
        DB::table('price_list_items')->insert([
            'id' => $itemId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'price_list_id' => $this->priceList->id,
            'variant_id' => $this->variant->id,
            'unit_price' => 250.0000,
            'min_quantity' => 1.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempt switching item to 500ml variant where price 250 exceeds MRP 200
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(exceeds variant MRP|check_violation|23514)/i');

        DB::table('price_list_items')
            ->where('id', $itemId)
            ->update(['variant_id' => $lowMrpVariant->id]);
    }

    public function test_raw_update_reducing_variant_mrp_below_existing_price_list_item_price_rejected_tax_07(): void
    {
        $itemId = (string) Str::uuid();
        // Item is at ৳270 (MRP is ৳280)
        DB::table('price_list_items')->insert([
            'id' => $itemId,
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'price_list_id' => $this->priceList->id,
            'variant_id' => $this->variant->id,
            'unit_price' => 270.0000,
            'min_quantity' => 1.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempt reducing variant MRP from 280 to 260 while price is 270
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/(Cannot reduce variant MRP|check_violation|23514)/i');

        DB::table('product_variants')
            ->where('id', $this->variant->id)
            ->update(['mrp' => 260.0000]);
    }

    public function test_eloquent_save_above_variant_mrp_rejected_by_exception_tax_07(): void
    {
        $this->expectException(MrpCeilingExceededException::class);

        PriceListItem::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'price_list_id' => $this->priceList->id,
            'variant_id' => $this->variant->id,
            'unit_price' => '280.5000',
            'min_quantity' => '1.0000',
        ]);
    }

    public function test_insert_and_update_price_list_item_at_or_below_mrp_allowed_tax_07(): void
    {
        // 1. At exact MRP (৳280.00)
        $item = PriceListItem::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'price_list_id' => $this->priceList->id,
            'variant_id' => $this->variant->id,
            'unit_price' => '280.0000',
            'min_quantity' => '1.0000',
        ]);

        $this->assertDatabaseHas('price_list_items', [
            'id' => $item->id,
            'unit_price' => 280.0000,
        ]);

        // 2. Below MRP (৳265.50)
        $item->update(['unit_price' => '265.5000']);

        $this->assertDatabaseHas('price_list_items', [
            'id' => $item->id,
            'unit_price' => 265.5000,
        ]);
    }
}
