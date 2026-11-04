<?php

use App\Core\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Modules\Inventory\Contracts\StockMovementServiceContract;
use App\Modules\Inventory\DTOs\StockMovementDTO;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$tenantId = $argv[1] ?? '';
$companyId = $argv[2] ?? '';
$warehouseId = $argv[3] ?? '';
$variantId = $argv[4] ?? '';
$quantity = $argv[5] ?? '1.0000';

$tenant = Tenant::find($tenantId);
if (! $tenant) {
    fwrite(STDERR, "Tenant not found: {$tenantId}\n");
    exit(2);
}

TenantContext::set($tenant);

/** @var StockMovementServiceContract $service */
$service = app(StockMovementServiceContract::class);

try {
    $dto = new StockMovementDTO(
        companyId: $companyId,
        warehouseId: $warehouseId,
        stockLocationId: null,
        productVariantId: $variantId,
        movementType: 'SALE',
        quantity: "-{$quantity}",
        unitCost: '100.0000',
        referenceType: 'sales_invoice',
        referenceId: (string) Str::uuid()
    );

    DB::transaction(fn () => $service->recordMovement($dto), attempts: 3);

    exit(0);
} catch (InsufficientStockException $e) {
    fwrite(STDERR, "Insufficient stock: {$e->getMessage()}\n");
    exit(1);
} catch (QueryException $e) {
    if (str_contains($e->getMessage(), 'Inventory Balance Violation') || str_contains($e->getMessage(), 'insufficient')) {
        fwrite(STDERR, "Constraint violation: {$e->getMessage()}\n");
        exit(1);
    }
    fwrite(STDERR, "Query error: {$e->getMessage()}\n");
    exit(2);
} catch (Throwable $e) {
    fwrite(STDERR, "Unexpected error: {$e->getMessage()}\n");
    exit(2);
}
