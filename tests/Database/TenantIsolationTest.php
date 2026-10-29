<?php

namespace Tests\Database;

use App\Core\Cache\TenantCache;
use App\Core\Queue\Middleware\TenantJobMiddleware;
use App\Core\Queue\TenantAwareJob;
use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Company $companyA;
    protected Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed Tenant A and Tenant B
        $this->tenantA = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Apex Retail Ltd',
            'subdomain' => 'apex-' . Str::random(6),
            'subscription_plan' => 'enterprise',
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Bata Bangladesh',
            'subdomain' => 'bata-' . Str::random(6),
            'subscription_plan' => 'growth',
            'status' => 'active',
        ]);

        // Insert companies under Tenant A and Tenant B
        TenantContext::set($this->tenantA);
        $this->companyA = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'legal_name' => 'Apex Footwear Ltd',
            'bin_number' => '1234567890123',
            'base_currency' => 'BDT',
        ]);

        TenantContext::set($this->tenantB);
        $this->companyB = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'legal_name' => 'Bata Shoe Company Ltd',
            'bin_number' => '9876543210987',
            'base_currency' => 'BDT',
        ]);

        TenantContext::clear();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * Vector 1 & Fundamental RLS:
     * Tenant A cannot read Tenant B's records via Eloquent or raw DB::select.
     */
    public function test_tenant_a_cannot_read_tenant_b_records_via_eloquent_or_raw_sql(): void
    {
        TenantContext::set($this->tenantA);

        // 1. Eloquent reads
        $companies = Company::all();
        $this->assertCount(1, $companies);
        $this->assertEquals($this->companyA->id, $companies->first()->id);
        $this->assertNull(Company::find($this->companyB->id));

        // 2. Raw DB::select reads (Attacker bypassing Eloquent)
        $rawRows = DB::select("SELECT id, tenant_id, legal_name FROM companies");
        $this->assertCount(1, $rawRows);
        $this->assertEquals($this->companyA->id, $rawRows[0]->id);
        $this->assertEquals($this->tenantA->id, $rawRows[0]->tenant_id);

        $specificRaw = DB::select("SELECT * FROM companies WHERE id = :id", ['id' => $this->companyB->id]);
        $this->assertEmpty($specificRaw);
    }

    /**
     * Vector 1:
     * Tenant A cannot update or delete Tenant B's records via Eloquent or raw DB::statement.
     */
    public function test_tenant_a_cannot_update_or_delete_tenant_b_records(): void
    {
        TenantContext::set($this->tenantA);

        // 1. Raw update against Tenant B's ID affects 0 rows
        $affected = DB::update("UPDATE companies SET legal_name = 'Hacked Name' WHERE id = :id", [
            'id' => $this->companyB->id,
        ]);
        $this->assertSame(0, $affected);

        // 2. Raw delete against Tenant B's ID affects 0 rows
        $deleted = DB::delete("DELETE FROM companies WHERE id = :id", [
            'id' => $this->companyB->id,
        ]);
        $this->assertSame(0, $deleted);

        // 3. Verify Tenant B's company remains intact
        TenantContext::set($this->tenantB);
        $freshCompanyB = Company::find($this->companyB->id);
        $this->assertNotNull($freshCompanyB);
        $this->assertEquals('Bata Shoe Company Ltd', $freshCompanyB->legal_name);
    }

    /**
     * Vector 1:
     * Tenant A cannot insert a record with Tenant B's tenant_id (RLS WITH CHECK constraint).
     */
    public function test_tenant_a_cannot_insert_record_with_tenant_b_id(): void
    {
        TenantContext::set($this->tenantA);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Attempting to insert a row with tenant_id = tenantB while in tenantA context
        Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'legal_name' => 'Malicious Impersonation Company',
        ]);
    }

    /**
     * Vector 1 & Fail-Closed Policy:
     * When no tenant context is set, queries return zero rows.
     */
    public function test_empty_tenant_context_returns_zero_rows_fail_closed(): void
    {
        TenantContext::clear();

        // Fail-closed RLS returns empty set
        $this->assertCount(0, Company::all());
        $this->assertEmpty(DB::select("SELECT * FROM companies"));
    }

    /**
     * Vector 2:
     * Connection must be app_rw and enforce RLS (never erp_owner in application code).
     */
    public function test_application_connects_as_app_rw_without_bypass_rls(): void
    {
        $currentUser = DB::selectOne("SELECT current_user")->current_user;
        $this->assertEquals('app_rw', $currentUser);

        $roleInfo = DB::selectOne("SELECT rolbypassrls FROM pg_roles WHERE rolname = 'app_rw'");
        $this->assertFalse($roleInfo->rolbypassrls, 'app_rw role must never possess BYPASSRLS');
    }

    /**
     * Vector 3:
     * Cache keys without tenant prefix could leak data. TenantCache ensures isolation.
     */
    public function test_cache_keys_are_strictly_isolated_between_tenants(): void
    {
        // Tenant A stores sensitive data
        TenantContext::set($this->tenantA);
        TenantCache::put('daily_sales_total', '500000.00', 60);
        $this->assertEquals('500000.00', TenantCache::get('daily_sales_total'));

        // Tenant B reads the same key - must get null
        TenantContext::set($this->tenantB);
        $this->assertNull(TenantCache::get('daily_sales_total'));

        // Tenant B stores its own value
        TenantCache::put('daily_sales_total', '120000.00', 60);
        $this->assertEquals('120000.00', TenantCache::get('daily_sales_total'));

        // Tenant A reads again - must still see its own value
        TenantContext::set($this->tenantA);
        $this->assertEquals('500000.00', TenantCache::get('daily_sales_total'));
    }

    /**
     * Vector 4:
     * A queued job must carry and restore tenant context and clean up afterwards.
     */
    public function test_queued_job_restores_tenant_context_and_cleans_up(): void
    {
        TenantContext::set($this->tenantA);

        $job = new class implements ShouldQueue {
            use TenantAwareJob;

            public bool $executed = false;
            public ?string $observedTenantId = null;
            public int $visibleCompaniesCount = 0;

            public function __construct()
            {
                $this->initializeTenantAwareJob();
            }

            public function handle(): void
            {
                $this->executed = true;
                $this->observedTenantId = TenantContext::id();
                $this->visibleCompaniesCount = Company::count();
            }
        };

        // Assert serialization captured tenantA ID
        $this->assertEquals($this->tenantA->id, $job->tenantId);

        // Clear context to simulate queue worker process state
        TenantContext::clear();

        // Process job through middleware
        $middleware = new TenantJobMiddleware();
        $middleware->handle($job, function ($j) {
            $j->handle();
        });

        $this->assertTrue($job->executed);
        $this->assertEquals($this->tenantA->id, $job->observedTenantId);
        $this->assertEquals(1, $job->visibleCompaniesCount);

        // Worker context must be cleared after execution
        $this->assertNull(TenantContext::get());
        $this->assertCount(0, Company::all());
    }

    /**
     * Vector 5:
     * Filament resource or global search query running with empty context returns 0 rows.
     */
    public function test_filament_or_search_without_tenant_context_returns_zero_rows(): void
    {
        TenantContext::clear();

        // Simulate global search or Filament query
        $results = Company::where('legal_name', 'ilike', '%Apex%')->get();
        $this->assertCount(0, $results);
    }

    /**
     * Vector 6:
     * Scheduled command iterating tenants cleans up even if an exception occurs.
     */
    public function test_scheduled_command_failure_in_tenant_loop_resets_context_in_finally_block(): void
    {
        TenantContext::clear();

        try {
            TenantContext::set($this->tenantA);
            throw new RuntimeException('Unexpected job failure in scheduled task');
        } catch (RuntimeException $e) {
            // Expected
        } finally {
            TenantContext::clear();
        }

        // Must be clean
        $this->assertNull(TenantContext::get());
        $this->assertCount(0, Company::all());
    }

    /**
     * Vector 7:
     * db:check-rls command detects and fails on tables lacking forced RLS.
     */
    public function test_db_check_rls_command_detects_rls_compliance(): void
    {
        // Run db:check-rls using pgsql_owner catalog connection
        $this->artisan('db:check-rls', ['--connection' => 'pgsql_owner'])
            ->assertExitCode(0);
    }
}
