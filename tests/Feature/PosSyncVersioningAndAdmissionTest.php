<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\PosTerminal;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Pos\Adapters\PosSyncPayloadAdapter;
use App\Modules\Pos\DTOs\SyncBatchRequestDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosSyncVersioningAndAdmissionTest extends TestCase
{
    protected Tenant $tenant;

    protected Company $company;

    protected Branch $branch;

    protected PosTerminal $terminal;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sync Versioning Tenant',
            'subdomain' => 'ver-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->company = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'legal_name' => 'Versioning Retail Ltd',
            'base_currency' => 'BDT',
            'fiscal_year_start_month' => 7,
            'tax_regime' => 'standard_vat',
        ]);

        $this->branch = Branch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Mirpur Branch',
            'code' => 'MIR-01',
        ]);

        $this->terminal = PosTerminal::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'terminal_code' => 'TERM-V1',
            'name' => 'Counter 1',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Cashier Version',
            'email' => 'cashier-ver-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        Cache::flush();
        parent::tearDown();
    }

    public function test_missing_version_header_returns_426_upgrade_required(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/pos/sync-batch', [
                'batch_id' => (string) Str::uuid(),
                'terminal_id' => $this->terminal->id,
                'shift_id' => (string) Str::uuid(),
                'sales' => [],
            ]);

        $response->assertStatus(426);
        $response->assertJson([
            'error' => 'UPGRADE_REQUIRED',
            'minimum_version' => '1.1.0',
            'current_version' => '1.2.0',
        ]);
    }

    public function test_older_version_below_minimum_returns_426_upgrade_required(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-POS-Client-Version', '1.0.0')
            ->postJson('/api/v1/pos/sync-batch', [
                'batch_id' => (string) Str::uuid(),
                'terminal_id' => $this->terminal->id,
                'shift_id' => (string) Str::uuid(),
                'sales' => [],
            ]);

        $response->assertStatus(426);
        $response->assertJson([
            'error' => 'UPGRADE_REQUIRED',
            'minimum_version' => '1.1.0',
            'current_version' => '1.2.0',
        ]);
    }

    public function test_previous_supported_version_is_accepted_by_middleware(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-POS-Client-Version', '1.1.0')
            ->postJson('/api/v1/pos/sync-batch', [
                'batch_id' => (string) Str::uuid(),
                'terminal_id' => $this->terminal->id,
                'shift_id' => (string) Str::uuid(),
                'sales' => [],
            ]);

        // Status should not be 426
        $this->assertNotEquals(426, $response->getStatusCode());
    }

    public function test_current_supported_version_is_accepted_by_middleware(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-POS-Client-Version', '1.2.0')
            ->postJson('/api/v1/pos/sync-batch', [
                'batch_id' => (string) Str::uuid(),
                'terminal_id' => $this->terminal->id,
                'shift_id' => (string) Str::uuid(),
                'sales' => [],
            ]);

        $this->assertNotEquals(426, $response->getStatusCode());
    }

    public function test_adapter_successfully_normalizes_v1_legacy_fixture(): void
    {
        $v1Json = json_decode((string) file_get_contents(base_path('tests/Fixtures/pos-sync/v1.json')), true);
        $adapter = new PosSyncPayloadAdapter;

        $batchDto = $adapter->adapt($v1Json, '1.1.0');

        $this->assertInstanceOf(SyncBatchRequestDTO::class, $batchDto);
        $this->assertSame('0192e4a1-1111-7000-8000-000000000001', $batchDto->batchId);
        $this->assertCount(1, $batchDto->sales);

        $sale = $batchDto->sales[0];
        $this->assertSame('TXN-V1-001', $sale->clientTxnId);
        $this->assertSame('cash', strtolower($sale->paymentMethod));
        $this->assertCount(2, $sale->items);
        $this->assertSame('450.0000', $sale->items[0]->unitPrice);
        $this->assertSame('0.00', $sale->items[0]->flatTaxRate);
    }

    public function test_adapter_successfully_normalizes_v2_current_fixture(): void
    {
        $v2Json = json_decode((string) file_get_contents(base_path('tests/Fixtures/pos-sync/v2.json')), true);
        $adapter = new PosSyncPayloadAdapter;

        $batchDto = $adapter->adapt($v2Json, '1.2.0');

        $this->assertInstanceOf(SyncBatchRequestDTO::class, $batchDto);
        $this->assertSame('0192e4a1-2222-7000-8000-000000000001', $batchDto->batchId);
        $this->assertCount(1, $batchDto->sales);

        $sale = $batchDto->sales[0];
        $this->assertSame('TXN-V2-001', $sale->clientTxnId);
        $this->assertSame('SDC-2027-000099', $sale->sdcInvoiceNumber);
        $this->assertSame('SIG-V2-789XYZ', $sale->fiscalSignature);
        $this->assertCount(2, $sale->items);
        $this->assertSame('0192e4a1-0000-7000-8000-000000000004', $sale->items[0]->taxCodeId);
    }

    public function test_admission_control_throttles_burst_requests_with_429_and_retry_after(): void
    {
        // Max concurrent batches is 10. Simulate active slot saturation
        $tenantId = $this->tenant->id;
        Cache::put("pos_sync_concurrency:{$tenantId}", 10, 60);

        $response = $this->actingAs($this->user)
            ->withHeader('X-POS-Client-Version', '1.2.0')
            ->postJson('/api/v1/pos/sync-batch', [
                'batch_id' => (string) Str::uuid(),
                'terminal_id' => $this->terminal->id,
                'shift_id' => (string) Str::uuid(),
                'sales' => [],
            ]);

        $response->assertStatus(429);
        $this->assertTrue($response->headers->has('Retry-After'));
        $response->assertJson([
            'error' => 'TOO_MANY_REQUESTS',
        ]);
    }
}
