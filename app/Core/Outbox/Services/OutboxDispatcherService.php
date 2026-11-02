<?php

namespace App\Core\Outbox\Services;

use App\Core\Outbox\Models\TransactionalOutbox;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OutboxDispatcherService
{
    /**
     * @var array<string, array<int, callable>>
     */
    protected static array $handlers = [];

    /**
     * Register a callback handler for a specific event type.
     */
    public static function registerHandler(string $eventType, callable $handler): void
    {
        self::$handlers[$eventType][] = $handler;
    }

    /**
     * Clear all registered event handlers.
     */
    public static function clearHandlers(): void
    {
        self::$handlers = [];
    }

    /**
     * Dispatches pending outbox events across active tenants or for a specified tenant.
     */
    public function dispatchPending(int $batchSize = 50, ?string $tenantId = null): int
    {
        if ($tenantId !== null) {
            return $this->dispatchForTenant($tenantId, $batchSize);
        }

        $totalProcessed = 0;
        $tenants = Tenant::where('status', 'active')->get();

        foreach ($tenants as $tenant) {
            $totalProcessed += $this->dispatchForTenant($tenant->id, $batchSize);
        }

        return $totalProcessed;
    }

    /**
     * Dispatches pending events for a specific tenant in an isolated transaction.
     */
    protected function dispatchForTenant(string $tenantId, int $batchSize): int
    {
        return DB::transaction(function () use ($tenantId, $batchSize) {
            // Set tenant context for this transaction (fail-closed, Rule 4)
            DB::statement("SELECT set_config('app.current_tenant_id', :id, true)", ['id' => $tenantId]);

            /** @var \Illuminate\Database\Eloquent\Collection<int, TransactionalOutbox> $events */
            $events = TransactionalOutbox::where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->where('scheduled_at', '<=', Carbon::now())
                ->orderBy('scheduled_at', 'asc')
                ->limit($batchSize)
                ->lock('FOR UPDATE SKIP LOCKED')
                ->get();

            $processedInTenant = 0;

            foreach ($events as $event) {
                try {
                    $this->deliverEvent($event);

                    $event->status = 'published';
                    $event->attempts += 1;
                    $event->processed_at = Carbon::now();
                    $event->last_error = null;
                    $event->save();

                    $processedInTenant++;
                } catch (Throwable $e) {
                    $event->attempts += 1;
                    $event->last_error = substr($e->getMessage(), 0, 1000);

                    if ($event->attempts >= 5) {
                        $event->status = 'failed';
                        Log::channel('daily')->error("Outbox event {$event->id} permanently failed (dead-letter): {$e->getMessage()}", [
                            'tenant_id' => $tenantId,
                            'aggregate_type' => $event->aggregate_type,
                            'event_type' => $event->event_type,
                        ]);
                    } else {
                        // Exponential backoff: 2^attempts * 5 seconds
                        $backoffSeconds = (int) (pow(2, $event->attempts) * 5);
                        $event->scheduled_at = Carbon::now()->addSeconds($backoffSeconds);
                    }

                    $event->save();
                }
            }

            return $processedInTenant;
        });
    }

    /**
     * Executes handlers for the event.
     */
    protected function deliverEvent(TransactionalOutbox $event): void
    {
        $eventType = $event->event_type;

        if (isset(self::$handlers[$eventType])) {
            foreach (self::$handlers[$eventType] as $handler) {
                $handler($event);
            }
        }
    }
}
