<?php

namespace App\Console\Commands;

use App\Core\Outbox\Services\OutboxDispatcherService;
use Illuminate\Console\Command;

class DispatchOutboxCommand extends Command
{
    protected $signature = 'outbox:dispatch
                            {--tenant= : Filter execution to a single tenant UUID}
                            {--batch=50 : Maximum number of records to process per batch}';

    protected $description = 'Polls transactional_outbox using SKIP LOCKED and dispatches pending events with backoff retries';

    public function handle(OutboxDispatcherService $dispatcher): int
    {
        $tenantId = $this->option('tenant');
        $batchSize = (int) $this->option('batch');

        $this->info('Starting Transactional Outbox event dispatching...');

        $processed = $dispatcher->dispatchPending(
            batchSize: $batchSize > 0 ? $batchSize : 50,
            tenantId: is_string($tenantId) && ! empty($tenantId) ? $tenantId : null
        );

        $this->info("Successfully dispatched {$processed} outbox event(s).");

        return self::SUCCESS;
    }
}
