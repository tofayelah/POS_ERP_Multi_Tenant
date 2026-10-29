<?php

namespace Tests\Database;

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompositeForeignKeyTest extends TestCase
{
    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Company $companyA;
    protected Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the child table under pgsql_owner
        DB::connection('pgsql_owner')->statement("
            CREATE TABLE IF NOT EXISTS test_branches (
                id UUID PRIMARY KEY,
                tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE RESTRICT,
                company_id UUID NOT NULL,
                name VARCHAR(255) NOT NULL,
                CONSTRAINT uq_test_branches_tenant_id UNIQUE (tenant_id, id),
                CONSTRAINT fk_test_branches_company FOREIGN KEY (tenant_id, company_id) 
                    REFERENCES companies(tenant_id, id) ON DELETE RESTRICT
            )
        ");

        DB::connection('pgsql_owner')->statement("ALTER TABLE test_branches ENABLE ROW LEVEL SECURITY");
        DB::connection('pgsql_owner')->statement("ALTER TABLE test_branches FORCE ROW LEVEL SECURITY");
        DB::connection('pgsql_owner')->statement("
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT FROM pg_policy WHERE polname = 'tenant_isolation_policy' AND polrelid = 'test_branches'::regclass) THEN
                    CREATE POLICY tenant_isolation_policy ON test_branches
                        FOR ALL TO app_rw, reporting_ro
                        USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid)
                        WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid);
                END IF;
            END $$;
        ");

        DB::connection('pgsql_owner')->statement("GRANT SELECT, INSERT, UPDATE, DELETE ON test_branches TO app_rw");

        // Seed Tenant A & Tenant B
        $this->tenantA = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant A Ltd',
            'subdomain' => 'ta-' . Str::random(6),
        ]);

        $this->tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant B Ltd',
            'subdomain' => 'tb-' . Str::random(6),
        ]);

        // Seed Company under Tenant A
        TenantContext::set($this->tenantA);
        $this->companyA = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'legal_name' => 'Company A Legal',
        ]);

        // Seed Company under Tenant B
        TenantContext::set($this->tenantB);
        $this->companyB = Company::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'legal_name' => 'Company B Legal',
        ]);

        TenantContext::clear();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        DB::connection('pgsql_owner')->statement("DROP TABLE IF EXISTS test_branches");
        parent::tearDown();
    }

    /**
     * Review TEN-02:
     * A foreign key pointing at another tenant's row MUST be rejected by the composite foreign key constraint.
     */
    public function test_cross_tenant_foreign_key_reference_is_rejected(): void
    {
        TenantContext::set($this->tenantA);

        // Attempting to insert a branch in Tenant A referencing Company B from Tenant B
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/fk_test_branches_company|foreign key constraint/i');

        DB::table('test_branches')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyB->id, // BELONGS TO TENANT B!
            'name' => 'Rogue Cross-Tenant Branch',
        ]);
    }

    /**
     * Valid intra-tenant foreign key reference succeeds.
     */
    public function test_valid_same_tenant_foreign_key_reference_succeeds(): void
    {
        TenantContext::set($this->tenantA);

        $branchId = (string) Str::uuid();
        DB::table('test_branches')->insert([
            'id' => $branchId,
            'tenant_id' => $this->tenantA->id,
            'company_id' => $this->companyA->id,
            'name' => 'Valid Branch A',
        ]);

        $inserted = DB::table('test_branches')->where('id', $branchId)->first();
        $this->assertNotNull($inserted);
        $this->assertEquals('Valid Branch A', $inserted->name);
    }
}
