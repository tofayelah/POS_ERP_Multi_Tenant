<?php

namespace Tests\Feature\Outbox;

use App\Core\Outbox\Exceptions\SsrfBlockedException;
use App\Core\Outbox\Models\TransactionalOutbox;
use App\Core\Outbox\Services\OutboxDispatcherService;
use App\Core\Outbox\Services\OutboxEmitter;
use App\Core\Tenancy\TenantContext;
use App\Core\Webhooks\Services\SsrfProtectionService;
use App\Core\Webhooks\Services\WebhookDispatcher;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionalOutboxTest extends TestCase
{
    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Outbox Test Tenant',
            'subdomain' => 'outbox-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($this->tenant);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Outbox User',
            'email' => 'outbox-'.Str::random(6).'@example.com',
            'password' => 'secret123',
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_outbox_table_structure_and_forced_rls(): void
    {
        // 1. Verify RLS is forced on transactional_outbox
        $rlsStatus = DB::connection('pgsql_owner')->selectOne("
            SELECT relrowsecurity, relforcerowsecurity 
            FROM pg_class 
            WHERE relname = 'transactional_outbox'
        ");

        $this->assertNotNull($rlsStatus, 'transactional_outbox table must exist');
        $this->assertTrue((bool) $rlsStatus->relrowsecurity, 'RLS must be enabled on transactional_outbox');
        $this->assertTrue((bool) $rlsStatus->relforcerowsecurity, 'RLS must be FORCED on transactional_outbox');

        // 2. Create another tenant and emit an outbox record for tenant 2
        $otherTenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Other Tenant',
            'subdomain' => 'other-'.Str::random(6),
            'status' => 'active',
        ]);

        TenantContext::set($otherTenant);
        $otherRecord = OutboxEmitter::emit(
            aggregateType: 'Order',
            aggregateId: (string) Str::uuid(),
            eventType: 'order.created',
            payload: ['amount' => '500.00']
        );

        // 3. Switch back to first tenant, confirm other tenant's outbox event is invisible under app_rw
        TenantContext::set($this->tenant);
        $visibleRecords = TransactionalOutbox::all();
        $this->assertFalse($visibleRecords->contains('id', $otherRecord->id));
    }

    public function test_outbox_event_emitted_inside_business_transaction(): void
    {
        $aggregateId = (string) Str::uuid();

        // Commit case
        DB::transaction(function () use ($aggregateId) {
            OutboxEmitter::emit(
                aggregateType: 'SalesInvoice',
                aggregateId: $aggregateId,
                eventType: 'invoice.issued',
                payload: ['invoice_number' => 'INV-2027-001', 'total' => '1500.00']
            );
        });

        $record = TransactionalOutbox::where('aggregate_id', $aggregateId)->first();
        $this->assertNotNull($record);
        $this->assertEquals($this->tenant->id, $record->tenant_id);
        $this->assertEquals('SalesInvoice', $record->aggregate_type);
        $this->assertEquals('invoice.issued', $record->event_type);
        $this->assertEquals('pending', $record->status);
        $this->assertEquals(0, $record->attempts);
        $this->assertEquals('INV-2027-001', $record->payload['invoice_number']);

        // Rollback case
        $abortedAggregateId = (string) Str::uuid();
        try {
            DB::transaction(function () use ($abortedAggregateId) {
                OutboxEmitter::emit(
                    aggregateType: 'SalesInvoice',
                    aggregateId: $abortedAggregateId,
                    eventType: 'invoice.issued',
                    payload: ['aborted' => true]
                );
                throw new \RuntimeException('Business transaction failure');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        $abortedRecord = TransactionalOutbox::where('aggregate_id', $abortedAggregateId)->first();
        $this->assertNull($abortedRecord, 'Outbox event must rollback atomically with the business transaction');
    }

    public function test_outbox_dispatcher_processes_pending_events_with_skip_locked(): void
    {
        $delivered = [];

        OutboxDispatcherService::registerHandler('inventory.movement.created', function (TransactionalOutbox $event) use (&$delivered) {
            $delivered[] = $event->payload['movement_code'];
        });

        OutboxEmitter::emit(
            aggregateType: 'StockMovement',
            aggregateId: (string) Str::uuid(),
            eventType: 'inventory.movement.created',
            payload: ['movement_code' => 'MOV-001']
        );

        OutboxEmitter::emit(
            aggregateType: 'StockMovement',
            aggregateId: (string) Str::uuid(),
            eventType: 'inventory.movement.created',
            payload: ['movement_code' => 'MOV-002']
        );

        $dispatcher = app(OutboxDispatcherService::class);
        $processedCount = $dispatcher->dispatchPending(batchSize: 10, tenantId: $this->tenant->id);

        $this->assertEquals(2, $processedCount);
        $this->assertContains('MOV-001', $delivered);
        $this->assertContains('MOV-002', $delivered);

        $records = TransactionalOutbox::all();
        foreach ($records as $rec) {
            $this->assertEquals('published', $rec->status);
            $this->assertEquals(1, $rec->attempts);
            $this->assertNotNull($rec->processed_at);
        }
    }

    public function test_outbox_dispatcher_retries_with_exponential_backoff_and_dead_letter(): void
    {
        OutboxDispatcherService::registerHandler('failing.event', function (TransactionalOutbox $event) {
            throw new \RuntimeException('External system timeout');
        });

        $record = OutboxEmitter::emit(
            aggregateType: 'Payment',
            aggregateId: (string) Str::uuid(),
            eventType: 'failing.event',
            payload: ['payment_id' => 'PAY-999']
        );

        $dispatcher = app(OutboxDispatcherService::class);

        // First attempt -> failure, scheduled into future
        $processed = $dispatcher->dispatchPending(batchSize: 10, tenantId: $this->tenant->id);
        $this->assertEquals(0, $processed, 'Failed event does not count as processed');

        $record->refresh();
        $this->assertEquals('pending', $record->status);
        $this->assertEquals(1, $record->attempts);
        $this->assertStringContainsString('External system timeout', (string) $record->last_error);
        $this->assertTrue($record->scheduled_at->isFuture());

        // Fast forward 4 more attempts to simulate dead-letter after 5 attempts
        $record->attempts = 4;
        $record->scheduled_at = Carbon::now()->subMinute();
        $record->save();

        $dispatcher->dispatchPending(batchSize: 10, tenantId: $this->tenant->id);

        $record->refresh();
        $this->assertEquals('failed', $record->status, 'Event must transition to failed (dead-letter) after 5 attempts');
        $this->assertEquals(5, $record->attempts);
    }

    public function test_webhook_dispatcher_attaches_hmac_signature_and_timestamp(): void
    {
        $webhook = app(WebhookDispatcher::class);
        $payload = json_encode(['event' => 'invoice.issued', 'total' => '250.00'], JSON_THROW_ON_ERROR);
        $secret = 'super-secret-webhook-key-12345';

        $timestamp = time();
        $headers = $webhook->buildHeaders($payload, $secret, $timestamp);

        $this->assertArrayHasKey('X-Signature', $headers);
        $this->assertArrayHasKey('X-Timestamp', $headers);
        $this->assertEquals((string) $timestamp, $headers['X-Timestamp']);

        $expectedSig = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        $this->assertEquals($expectedSig, $headers['X-Signature']);

        // Verify valid signature
        $isValid = $webhook->verifySignature(
            payload: $payload,
            signatureHeader: $headers['X-Signature'],
            timestampHeader: $headers['X-Timestamp'],
            secret: $secret
        );
        $this->assertTrue($isValid, 'Valid signature and fresh timestamp must pass verification');
    }

    public function test_webhook_signature_verifier_rejects_replays_older_than_300s(): void
    {
        $webhook = app(WebhookDispatcher::class);
        $payload = json_encode(['event' => 'ping'], JSON_THROW_ON_ERROR);
        $secret = 'webhook-secret-999';

        // 301 seconds ago
        $staleTimestamp = time() - 301;
        $staleSig = 'sha256='.hash_hmac('sha256', $staleTimestamp.'.'.$payload, $secret);

        $isValid = $webhook->verifySignature(
            payload: $payload,
            signatureHeader: $staleSig,
            timestampHeader: (string) $staleTimestamp,
            secret: $secret,
            toleranceSeconds: 300
        );

        $this->assertFalse($isValid, 'Webhook with timestamp older than 300 seconds must be rejected as replay attack');

        // Tampered payload
        $freshTimestamp = time();
        $tamperedSig = 'sha256='.hash_hmac('sha256', $freshTimestamp.'.'.$payload, $secret);
        $isTamperedValid = $webhook->verifySignature(
            payload: '{"event":"tampered"}',
            signatureHeader: $tamperedSig,
            timestampHeader: (string) $freshTimestamp,
            secret: $secret
        );

        $this->assertFalse($isTamperedValid, 'Tampered payload must fail signature verification');
    }

    public function test_ssrf_protection_blocks_private_and_loopback_destinations_after_dns_resolution(): void
    {
        $ssrf = app(SsrfProtectionService::class);

        $blockedUrls = [
            'http://127.0.0.1:8080/webhook',
            'http://127.0.1.1/hook',
            'http://localhost/webhook',
            'http://10.0.0.1/webhook',
            'http://10.255.255.254/hook',
            'http://172.16.0.1/hook',
            'http://172.31.255.255/hook',
            'http://192.168.1.1/webhook',
            'http://169.254.169.254/latest/meta-data', // AWS/GCP metadata service
            'http://0.0.0.0/hook',
            'http://[::1]/webhook',
            'http://[fe80::1]/webhook',
            'ftp://malicious.com/file', // Non-http/https
        ];

        foreach ($blockedUrls as $url) {
            $blocked = false;
            try {
                $ssrf->assertSafeDestinationUrl($url);
            } catch (SsrfBlockedException $e) {
                $blocked = true;
            }
            $this->assertTrue($blocked, "URL {$url} should have been blocked by SSRF protection");
        }
    }

    public function test_ssrf_protection_allows_valid_public_destinations(): void
    {
        $ssrf = app(SsrfProtectionService::class);

        // 8.8.8.8 and 1.1.1.1 are known public IPs
        $safeUrls = [
            'https://8.8.8.8/webhook',
            'https://1.1.1.1/webhook',
        ];

        foreach ($safeUrls as $url) {
            $isSafe = true;
            try {
                $ssrf->assertSafeDestinationUrl($url);
            } catch (SsrfBlockedException $e) {
                $isSafe = false;
            }
            $this->assertTrue($isSafe, "Public destination {$url} should be allowed");
        }
    }
}
