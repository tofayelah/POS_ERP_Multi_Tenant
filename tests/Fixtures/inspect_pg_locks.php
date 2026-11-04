<?php

use App\Core\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Modules\Inventory\Contracts\StockMovementServiceContract;
use App\Modules\Inventory\DTOs\StockMovementDTO;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$mode = $argv[1] ?? 'setup';

if ($mode === 'setup') {
    $tenant = Tenant::create([
        'id' => (string) Str::uuid(),
        'name' => 'Lock Inspection Tenant',
        'subdomain' => 'lock-'.Str::random(6),
        'status' => 'active',
    ]);

    TenantContext::set($tenant);

    $company = Company::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'legal_name' => 'Lock Inspection Co',
        'base_currency' => 'BDT',
        'fiscal_year_start_month' => 7,
        'tax_regime' => 'standard_vat',
    ]);

    $warehouse = Warehouse::create([
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'code' => 'WH-LOCK-01',
        'name' => 'Lock Inspection Warehouse',
        'is_active' => true,
    ]);

    $taxCode = TaxCode::create([
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'code' => 'VAT-15',
        'name_en' => 'Standard VAT 15%',
        'tax_type' => 'standard_vat',
        'is_active' => true,
    ]);

    $product = Product::create([
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'code' => 'PROD-LOCK-01',
        'name_en' => 'Lock Product',
        'base_uom' => 'Pcs',
        'is_active' => true,
    ]);

    $u1 = (string) Str::uuid();
    $u2 = (string) Str::uuid();
    $firstId = min($u1, $u2);
    $secondId = max($u1, $u2);

    $v1 = ProductVariant::create([
        'id' => $firstId,
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'product_id' => $product->id,
        'tax_code_id' => $taxCode->id,
        'sku' => 'SKU-LOWER-001',
        'name_en' => 'Variant 1 (Lower UUID)',
        'base_uom' => 'Pcs',
        'cost_price' => '100.0000',
        'mrp' => '150.0000',
        'is_active' => true,
    ]);

    $v2 = ProductVariant::create([
        'id' => $secondId,
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'product_id' => $product->id,
        'tax_code_id' => $taxCode->id,
        'sku' => 'SKU-HIGHER-002',
        'name_en' => 'Variant 2 (Higher UUID)',
        'base_uom' => 'Pcs',
        'cost_price' => '100.0000',
        'mrp' => '150.0000',
        'is_active' => true,
    ]);

    /** @var StockMovementServiceContract $service */
    $service = app(StockMovementServiceContract::class);
    DB::transaction(function () use ($service, $company, $warehouse, $v1, $v2) {
        $service->recordMovement(new StockMovementDTO(
            companyId: $company->id,
            warehouseId: $warehouse->id,
            stockLocationId: null,
            productVariantId: $v1->id,
            movementType: 'PURCHASE_RECEIPT',
            quantity: '50.0000',
            unitCost: '100.0000',
            referenceType: 'initial_stock',
            referenceId: (string) Str::uuid()
        ));

        $service->recordMovement(new StockMovementDTO(
            companyId: $company->id,
            warehouseId: $warehouse->id,
            stockLocationId: null,
            productVariantId: $v2->id,
            movementType: 'PURCHASE_RECEIPT',
            quantity: '50.0000',
            unitCost: '100.0000',
            referenceType: 'initial_stock',
            referenceId: (string) Str::uuid()
        ));
    });

    echo json_encode([
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'warehouse_id' => $warehouse->id,
        'variant_1' => $v1->id,
        'variant_2' => $v2->id,
    ]);
    exit(0);
}

if ($mode === 'query_locks') {
    $targetPid = (int) ($argv[2] ?? 0);

    $locks = DB::select('
        SELECT
            l.pid,
            l.locktype,
            c.relname AS relation,
            l.page,
            l.tuple,
            l.mode,
            l.granted
        FROM pg_locks l
        LEFT JOIN pg_class c ON l.relation = c.oid
        WHERE l.pid = ?
        ORDER BY l.locktype, c.relname, l.mode
    ', [$targetPid]);

    echo json_encode($locks, JSON_PRETTY_PRINT)."\n";
    exit(0);
}

if ($mode === 'hold_locks') {
    $tenantId = $argv[2];
    $companyId = $argv[3];
    $warehouseId = $argv[4];
    $v1 = $argv[5];
    $v2 = $argv[6];

    $tenant = Tenant::findOrFail($tenantId);
    TenantContext::set($tenant);

    /** @var StockMovementServiceContract $service */
    $service = app(StockMovementServiceContract::class);

    // Pass items in REVERSE order (Higher UUID first) to prove deterministic ascending sort
    $reverseDtos = [
        new StockMovementDTO(
            companyId: $companyId,
            warehouseId: $warehouseId,
            stockLocationId: null,
            productVariantId: $v2, // HIGHER UUID passed FIRST
            movementType: 'SALE',
            quantity: '-1.0000',
            unitCost: '100.0000',
            referenceType: 'sales_invoice',
            referenceId: (string) Str::uuid()
        ),
        new StockMovementDTO(
            companyId: $companyId,
            warehouseId: $warehouseId,
            stockLocationId: null,
            productVariantId: $v1, // LOWER UUID passed SECOND
            movementType: 'SALE',
            quantity: '-1.0000',
            unitCost: '100.0000',
            referenceType: 'sales_invoice',
            referenceId: (string) Str::uuid()
        ),
    ];

    DB::transaction(function () use ($reverseDtos) {
        // Enforce Section 13.1 ascending sort
        usort($reverseDtos, fn ($a, $b) => strcmp($a->productVariantId, $b->productVariantId));

        echo "LOCKING_ORDER_ENFORCED: 1st={$reverseDtos[0]->productVariantId}, 2nd={$reverseDtos[1]->productVariantId}\n";

        foreach ($reverseDtos as $idx => $dto) {
            $balance = StockBalance::where('warehouse_id', $dto->warehouseId)
                ->where('product_variant_id', $dto->productVariantId)
                ->lockForUpdate()
                ->first();

            echo 'LOCKED_STEP_'.($idx + 1).": Variant={$dto->productVariantId}, BalanceID={$balance?->id}\n";
        }

        $pid = DB::selectOne('SELECT pg_backend_pid() AS pid')->pid;
        echo "PAUSED_HOLDING_LOCKS: PID={$pid}\n";
        flush();

        // Pause for 10 seconds while holding exclusive locks
        DB::statement('SELECT pg_sleep(10)');

        echo "RESUMED_COMMITTING\n";
    });

    exit(0);
}
