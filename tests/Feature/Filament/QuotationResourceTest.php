<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Quotations\Pages\CreateQuotation;
use App\Filament\Resources\Quotations\Pages\ListQuotations;
use App\Filament\Resources\Quotations\Pages\ViewQuotation;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\SalesOrder;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\UserLocationScope;
use App\Modules\Sales\Contracts\SalesDocumentChainContract;
use App\Modules\Sales\DTOs\CreateQuotationDTO;
use App\Modules\Sales\DTOs\CreateQuotationLineDTO;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class QuotationResourceTest extends TestCase
{
    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected Company $companyA;

    protected Branch $branchA;

    protected Customer $customerA;

    protected TaxCode $taxCodeA;

    protected Product $productA;

    protected ProductVariant $variantA;

    protected User $salesMgr;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Setup Tenant A
        $this->tenantA = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Acme Sales Tenant A',
            'subdomain' => 'quote-a-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenantA);
        setPermissionsTeamId($this->tenantA->id);

        $this->companyA = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'legal_name' => 'Acme Commerce Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branchA = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'name' => 'Dhaka Sales Office',
            'code' => 'DSO-01',
            'is_active' => true,
        ]);

        $this->customerA = Customer::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'name' => 'Prime Distribution Ltd',
            'credit_limit' => '100000.0000',
            'is_active' => true,
        ]);

        $this->taxCodeA = TaxCode::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'code' => 'TAX-EX-Q',
            'name_en' => 'Exempt Quote Code',
            'description' => 'Exempt code',
            'is_active' => true,
        ]);

        $this->productA = Product::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'code' => 'PRD-ALUM',
            'name_en' => 'Aluminium Frame',
            'base_uom' => 'PCS',
        ]);

        $this->variantA = ProductVariant::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'product_id' => $this->productA->id,
            'tax_code_id' => $this->taxCodeA->id,
            'sku' => 'SKU-ALUM-01',
            'name_en' => 'Standard Aluminium Frame',
            'cost_price' => '400.0000',
            'mrp' => '650.0000',
        ]);

        Role::firstOrCreate([
            'name' => 'Sales Manager',
            'guard_name' => 'web',
            'tenant_id' => $this->tenantA->id,
        ]);

        $this->salesMgr = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sales Officer',
            'email' => 'sales-officer-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);
        $this->salesMgr->assignRole('Sales Manager');

        $membershipA = TenantMembership::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->salesMgr->id,
            'is_active' => true,
        ]);

        UserLocationScope::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'membership_id' => $membershipA->id,
            'company_id' => $this->companyA->id,
            'branch_id' => null, // company-wide
        ]);

        // 2. Setup Tenant B
        $this->tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Competitor Tenant B',
            'subdomain' => 'quote-b-'.Str::random(6),
            'status' => 'active',
        ]);
    }

    public function test_quotation_resource_binds_correct_model_and_navigation(): void
    {
        $this->assertEquals(Quotation::class, QuotationResource::getModel());
        $this->assertNotEmpty(QuotationResource::getNavigationGroup());
    }

    public function test_quotation_creation_delegates_to_domain_service_and_preserves_stock(): void
    {
        TenantContext::set($this->tenantA);
        setPermissionsTeamId($this->tenantA->id);

        $this->actingAs($this->salesMgr);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->tenantA);

        $initialMovements = StockMovement::count();
        $initialBalances = StockBalance::count();

        Livewire::test(CreateQuotation::class)
            ->fillForm([
                'company_id' => $this->companyA->id,
                'branch_id' => $this->branchA->id,
                'customer_id' => $this->customerA->id,
                'quotation_date' => Carbon::today()->toDateString(),
                'valid_until' => Carbon::today()->addDays(15)->toDateString(),
                'lines' => [
                    [
                        'product_id' => $this->productA->id,
                        'variant_id' => $this->variantA->id,
                        'quantity' => '20.0000',
                        'unit_price' => '650.0000',
                        'tax_amount' => '0.0000',
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $quote = Quotation::where('company_id', $this->companyA->id)->latest()->first();
        $this->assertNotNull($quote);
        $this->assertStringStartsWith('QT-', $quote->quotation_number);
        $this->assertEquals('draft', $quote->status);
        $this->assertEquals('13000.0000', $quote->grand_total);
        $this->assertCount(1, $quote->lines);
        $this->assertEquals('20.0000', $quote->lines->first()->quantity);

        // Invariant: Quotation MUST NEVER alter inventory balances or movements
        $this->assertEquals($initialMovements, StockMovement::count());
        $this->assertEquals($initialBalances, StockBalance::count());
    }

    public function test_quotation_conversion_to_sales_order_action(): void
    {
        TenantContext::set($this->tenantA);
        setPermissionsTeamId($this->tenantA->id);

        $this->actingAs($this->salesMgr);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->tenantA);

        /** @var SalesDocumentChainContract $service */
        $service = app(SalesDocumentChainContract::class);

        $quote = $service->createQuotation(new CreateQuotationDTO(
            companyId: $this->companyA->id,
            branchId: $this->branchA->id,
            customerId: $this->customerA->id,
            quotationDate: Carbon::today()->toDateString(),
            validUntil: Carbon::today()->addDays(10)->toDateString(),
            createdBy: $this->salesMgr->id,
            lines: [
                new CreateQuotationLineDTO(
                    productId: $this->productA->id,
                    variantId: $this->variantA->id,
                    quantity: '10.0000',
                    unitPrice: '650.0000',
                    taxAmount: '0.0000'
                ),
            ]
        ));

        $this->assertEquals('draft', $quote->status);

        Livewire::test(ViewQuotation::class, ['record' => $quote->id])
            ->callAction('convert_to_so')
            ->assertHasNoActionErrors();

        $quote->refresh();
        $this->assertEquals('converted', $quote->status);

        $so = SalesOrder::where('quotation_id', $quote->id)->first();
        $this->assertNotNull($so);
        $this->assertStringStartsWith('SO-', $so->order_number);
        $this->assertEquals('10.0000', $so->lines->first()->ordered_quantity);
        $this->assertEquals($quote->lines->first()->id, $so->lines->first()->quotation_line_id);
    }

    public function test_expired_quotation_cannot_be_converted(): void
    {
        TenantContext::set($this->tenantA);
        setPermissionsTeamId($this->tenantA->id);

        $this->actingAs($this->salesMgr);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->tenantA);

        /** @var SalesDocumentChainContract $service */
        $service = app(SalesDocumentChainContract::class);

        $expiredQuote = $service->createQuotation(new CreateQuotationDTO(
            companyId: $this->companyA->id,
            branchId: $this->branchA->id,
            customerId: $this->customerA->id,
            quotationDate: Carbon::today()->subDays(20)->toDateString(),
            validUntil: Carbon::today()->subDays(5)->toDateString(),
            createdBy: $this->salesMgr->id,
            lines: [
                new CreateQuotationLineDTO(
                    productId: $this->productA->id,
                    variantId: $this->variantA->id,
                    quantity: '5.0000',
                    unitPrice: '650.0000'
                ),
            ]
        ));

        // The conversion action should not be visible for expired quotations
        Livewire::test(ViewQuotation::class, ['record' => $expiredQuote->id])
            ->assertActionHidden('convert_to_so');
    }

    public function test_tenant_isolation_on_quotations(): void
    {
        // Create Quotation in Tenant A
        TenantContext::set($this->tenantA);
        setPermissionsTeamId($this->tenantA->id);

        /** @var SalesDocumentChainContract $service */
        $service = app(SalesDocumentChainContract::class);

        $quoteA = $service->createQuotation(new CreateQuotationDTO(
            companyId: $this->companyA->id,
            branchId: $this->branchA->id,
            customerId: $this->customerA->id,
            quotationDate: Carbon::today()->toDateString(),
            validUntil: Carbon::today()->addDays(7)->toDateString(),
            createdBy: $this->salesMgr->id,
            lines: [
                new CreateQuotationLineDTO(
                    productId: $this->productA->id,
                    variantId: $this->variantA->id,
                    quantity: '1.0000',
                    unitPrice: '650.0000'
                ),
            ]
        ));

        // Setup Tenant B Company, Branch, Customer, User
        TenantContext::set($this->tenantB);
        setPermissionsTeamId($this->tenantB->id);

        $companyB = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'legal_name' => 'Competitor Sales Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $branchB = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'company_id' => $companyB->id,
            'name' => 'Chittagong Office',
            'code' => 'CTG-01',
            'is_active' => true,
        ]);

        $customerB = Customer::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'company_id' => $companyB->id,
            'name' => 'B-Customer Ltd',
            'credit_limit' => '50000.0000',
            'is_active' => true,
        ]);

        Role::firstOrCreate([
            'name' => 'Sales Manager',
            'guard_name' => 'web',
            'tenant_id' => $this->tenantB->id,
        ]);

        $userB = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Competitor Officer',
            'email' => 'comp-sales-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);
        $userB->assignRole('Sales Manager');

        $membershipB = TenantMembership::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'user_id' => $userB->id,
            'is_active' => true,
        ]);

        UserLocationScope::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'membership_id' => $membershipB->id,
            'company_id' => $companyB->id,
            'branch_id' => null,
        ]);

        $this->actingAs($userB);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->tenantB);

        // Assert Tenant B listing cannot see Tenant A quotation
        Livewire::test(ListQuotations::class)
            ->assertDontSee($quoteA->quotation_number);

        // Attempting to view Tenant A record under Tenant B throws 404 / ModelNotFound
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::test(ViewQuotation::class, ['record' => $quoteA->id]);
    }
}
