<?php

namespace App\Core\Outbox\Services;

use App\Core\Outbox\Models\TransactionalOutbox;
use App\Core\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OutboxEmitter
{
    /**
     * Emits an integration or domain event into the transactional outbox table.
     * Must be called within the same database transaction as the business document.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $headers
     */
    public static function emit(
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload,
        ?array $headers = null
    ): TransactionalOutbox {
        $tenantId = TenantContext::id();

        if (empty($tenantId)) {
            // Check session setting directly if TenantContext was set via raw SQL
            $sessionTenantId = DB::selectOne("SELECT current_setting('app.current_tenant_id', true) AS tid")?->tid;
            if (! empty($sessionTenantId)) {
                $tenantId = $sessionTenantId;
            }
        }

        if (empty($tenantId)) {
            throw new RuntimeException('Cannot emit transactional outbox event without an active tenant context.');
        }

        return TransactionalOutbox::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'event_type' => $eventType,
            'payload' => $payload,
            'headers' => $headers,
            'status' => 'pending',
            'attempts' => 0,
            'scheduled_at' => Carbon::now(),
        ]);
    }
}
