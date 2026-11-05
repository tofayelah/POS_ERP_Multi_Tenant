<?php

namespace Tests\Feature\Filament;

use App\Core\Tenancy\TenantContext;
use App\Filament\Resources\SupplierQuotations\Pages\CreateSupplierQuotation;
use App\Filament\Resources\SupplierQuotations\Pages\ViewSupplierQuotation;
use App\Filament\Resources\SupplierQuotations\SupplierQuotationResource;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use App\Models\SupplierQuotationLine;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\UserLocationScope;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SupplierQuotationResourceTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected Supplier $supplierA;

    protected Supplier $supplierB;

    protected TaxCode $taxCode;

    protected Product $product;

    protected ProductVariant $variant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Setup Tenant
        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Supplier Quotation Tenant',
            'subdomain' => 'sq-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        // 2. Setup Company
        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Acme Procurement Corp Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        // 3. Setup Branch
        $this->branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Central Procurement Hub',
            'code' => 'CPH-01',
            'is_active' => true,
        ]);

        // 4. Setup Suppliers
        $this->supplierA = Supplier::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Alpha Commodity Mills Ltd',
            'is_active' => true,
        ]);

        $this->supplierB = Supplier::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Beta Agro Industries Ltd',
            'is_active' => true,
        ]);

        // 5. Setup Product & Variant
        $this->taxCode = TaxCode::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'TAX-ZERO-SQ',
            'name_en' => 'Zero Rated SQ Code',
            'description' => 'Zero rated',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'code' => 'PRD-MUSTARD',
            'name_en' => 'Mustard Seed Oil',
            'base_uom' => 'LTR',
        ]);

        $this->variant = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'tax_code_id' => $this->taxCode->id,
            'sku' => 'SKU-OIL-5LTR',
            'name_en' => '5 Litre Mustard Oil Drum',
            'cost_price' => '850.0000',
        ]);

        // 6. User & Role
        Role::firstOrCreate(['name' => 'Procurement Manager', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Chief Buyer',
            'email' => 'buyer-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);
        $this->user->assignRole('Procurement Manager');

        $membership = TenantMembership::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        UserLocationScope::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'membership_id' => $membership->id,
            'company_id' => $this->company->id,
            'branch_id' => null,
        ]);
    }

    public function test_supplier_quotation_resource_binds_correct_model_and_navigation(): void
    {
        $this->assertEquals(SupplierQuotation::class, SupplierQuotationResource::getModel());
        $this->assertEquals('Procurement & Purchasing', SupplierQuotationResource::getNavigationGroup());
        $this->assertEquals('Supplier Quotations / সরবরাহকারীর দরপত্র', SupplierQuotationResource::getNavigationLabel());
    }

    public function test_supplier_quotation_creation_preserves_stock_and_gl_immutability(): void
    {
        TenantContext::set($this->tenant);

        $initialStockCount = StockBalance::count();
        $initialMovementCount = StockMovement::count();
        $initialJournalCount = JournalEntry::count();

        $this->actingAs($this->user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->tenant);

        Livewire::test(CreateSupplierQuotation::class)
            ->fillForm([
                'company_id' => $this->company->id,
                'branch_id' => $this->branch->id,
                'supplier_id' => $this->supplierA->id,
                'rfq_number' => 'RFQ-2026-0099',
                'supplier_quote_reference' => 'VEND-REF-8877',
                'quote_date' => Carbon::today()->toDateString(),
                'valid_until' => Carbon::today()->addDays(30)->toDateString(),
                'lines' => [
                    [
                        'product_id' => $this->product->id,
                        'variant_id' => $this->variant->id,
                        'requested_quantity' => '100.0000',
                        'quoted_unit_price' => '820.0000',
                        'lead_time_days' => 7,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Verify quote created in draft
        $quote = SupplierQuotation::where('rfq_number', 'RFQ-2026-0099')->first();
        $this->assertNotNull($quote);
        $this->assertEquals('draft', $quote->status);
        $this->assertEquals('82000.0000', $quote->subtotal);
        $this->assertEquals('82000.0000', $quote->grand_total);
        $this->assertCount(1, $quote->lines);

        // Assert Stock & GL Invariant
        $this->assertEquals($initialStockCount, StockBalance::count(), 'Supplier quotation creation must not create physical stock balances');
        $this->assertEquals($initialMovementCount, StockMovement::count(), 'Supplier quotation creation must not create stock movements');
        $this->assertEquals($initialJournalCount, JournalEntry::count(), 'Supplier quotation creation must not post GL journals');
    }

    public function test_supplier_quotation_conversion_to_purchase_order_action(): void
    {
        TenantContext::set($this->tenant);

        // Create awarded quote
        $quote = SupplierQuotation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplierA->id,
            'rfq_number' => 'RFQ-2026-0100',
            'supplier_quote_reference' => 'VEND-WINNER-01',
            'quote_date' => Carbon::today()->toDateString(),
            'valid_until' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => '82000.0000',
            'tax_amount' => '0.0000',
            'grand_total' => '82000.0000',
            'status' => 'awarded',
            'awarded_at' => now(),
            'created_by' => $this->user->id,
        ]);

        SupplierQuotationLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'supplier_quotation_id' => $quote->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'requested_quantity' => '100.0000',
            'quoted_unit_price' => '820.0000',
            'tax_rate' => '0.0000',
            'tax_amount' => '0.0000',
            'line_total' => '82000.0000',
            'lead_time_days' => 5,
            'is_selected' => true,
        ]);

        $this->actingAs($this->user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->tenant);

        Livewire::test(ViewSupplierQuotation::class, ['record' => $quote->id])
            ->callAction('convert_to_purchase_order')
            ->assertHasNoActionErrors();

        $quote->refresh();
        $this->assertEquals('converted_to_po', $quote->status);
        $this->assertNotNull($quote->converted_po_id);

        // Verify generated Purchase Order
        $po = PurchaseOrder::find($quote->converted_po_id);
        $this->assertNotNull($po);
        $this->assertEquals($this->supplierA->id, $po->supplier_id);
        $this->assertEquals($this->branch->id, $po->branch_id);
        $this->assertEquals('82000.0000', $po->grand_total);
        $this->assertEquals('draft', $po->status);
        $this->assertCount(1, $po->lines);
        $this->assertEquals('820.0000', $po->lines->first()->unit_price);
        $this->assertEquals('100.0000', $po->lines->first()->ordered_quantity);
    }

    public function test_tenant_isolation_on_supplier_quotations(): void
    {
        TenantContext::set($this->tenant);

        $quoteA = SupplierQuotation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplierA->id,
            'rfq_number' => 'RFQ-TENANT-A',
            'quote_date' => Carbon::today()->toDateString(),
            'valid_until' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => '1000.0000',
            'tax_amount' => '0.0000',
            'grand_total' => '1000.0000',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        // Create Tenant B
        $tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Foreign Isolation Tenant',
            'subdomain' => 'iso-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($tenantB);

        // Query under Tenant B context must return zero rows for Tenant A
        $this->assertNull(SupplierQuotation::find($quoteA->id));
        $this->assertEquals(0, SupplierQuotation::where('id', $quoteA->id)->count());
    }

    public function test_supplier_quotation_comparison_widget_evaluates_multiple_bids(): void
    {
        TenantContext::set($this->tenant);

        // Quote A
        $quoteA = SupplierQuotation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplierA->id,
            'rfq_number' => 'RFQ-COMPARE-01',
            'supplier_quote_reference' => 'QA-01',
            'quote_date' => Carbon::today()->toDateString(),
            'valid_until' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => '8000.0000',
            'tax_amount' => '0.0000',
            'grand_total' => '8000.0000',
            'status' => 'evaluated',
            'created_by' => $this->user->id,
        ]);

        SupplierQuotationLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'supplier_quotation_id' => $quoteA->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'requested_quantity' => '10.0000',
            'quoted_unit_price' => '800.0000',
            'tax_rate' => '0.0000',
            'tax_amount' => '0.0000',
            'line_total' => '8000.0000',
            'lead_time_days' => 5,
        ]);

        // Quote B
        $quoteB = SupplierQuotation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplierB->id,
            'rfq_number' => 'RFQ-COMPARE-01',
            'supplier_quote_reference' => 'QB-01',
            'quote_date' => Carbon::today()->toDateString(),
            'valid_until' => Carbon::today()->addDays(30)->toDateString(),
            'subtotal' => '8500.0000',
            'tax_amount' => '0.0000',
            'grand_total' => '8500.0000',
            'status' => 'evaluated',
            'created_by' => $this->user->id,
        ]);

        SupplierQuotationLine::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'supplier_quotation_id' => $quoteB->id,
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'requested_quantity' => '10.0000',
            'quoted_unit_price' => '850.0000',
            'tax_rate' => '0.0000',
            'tax_amount' => '0.0000',
            'line_total' => '8500.0000',
            'lead_time_days' => 3,
        ]);

        $widget = new \App\Filament\Widgets\SupplierQuotationComparisonWidget;
        $widget->record = $quoteA;
        $comp = $widget->getComparisonData();

        $this->assertTrue($comp['has_bids']);
        $this->assertEquals('RFQ-COMPARE-01', $comp['rfq_number']);
        $this->assertCount(2, $comp['bids']);
        $this->assertCount(1, $comp['variant_comparison']);

        $variantComp = $comp['variant_comparison'][0];
        $this->assertEquals('800.0000', $variantComp['lowest_price']);
        $this->assertEquals($quoteA->id, $variantComp['lowest_supplier_id']);
    }
}
