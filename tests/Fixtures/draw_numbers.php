<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tenantId = $argv[1] ?? '';
$companyId = $argv[2] ?? '';
$outputFile = $argv[3] ?? '';
$count = isset($argv[4]) ? (int) $argv[4] : 500;

$tenant = App\Models\Tenant::find($tenantId);
if (! $tenant) {
    fwrite(STDERR, "Tenant not found: {$tenantId}\n");
    exit(1);
}

App\Core\Tenancy\TenantContext::set($tenant);

/** @var App\Modules\Accounting\Contracts\NumberSeriesContract $service */
$service = app(App\Modules\Accounting\Contracts\NumberSeriesContract::class);

$numbers = [];
for ($i = 0; $i < $count; $i++) {
    Illuminate\Support\Facades\DB::transaction(function () use ($service, $companyId, &$numbers) {
        $numbers[] = $service->getNextNumber($companyId, null, 'journal', '2026-2027');
    });
}

file_put_contents($outputFile, json_encode($numbers));
exit(0);
