<?php

use App\Core\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Modules\Accounting\Contracts\NumberSeriesContract;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$tenantId = $argv[1] ?? '';
$companyId = $argv[2] ?? '';
$outputFile = $argv[3] ?? '';
$count = isset($argv[4]) ? (int) $argv[4] : 500;

$tenant = Tenant::find($tenantId);
if (! $tenant) {
    fwrite(STDERR, "Tenant not found: {$tenantId}\n");
    exit(1);
}

TenantContext::set($tenant);

/** @var NumberSeriesContract $service */
$service = app(NumberSeriesContract::class);

$numbers = [];
for ($i = 0; $i < $count; $i++) {
    DB::transaction(function () use ($service, $companyId, &$numbers) {
        $numbers[] = $service->getNextNumber($companyId, null, 'journal', '2026-2027');
    });
}

file_put_contents($outputFile, json_encode($numbers));
exit(0);
