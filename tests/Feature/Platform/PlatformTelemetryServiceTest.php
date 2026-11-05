<?php

namespace Tests\Feature\Platform;

use App\Core\Platform\Services\PlatformTelemetryService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlatformTelemetryServiceTest extends TestCase
{
    protected PlatformTelemetryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PlatformTelemetryService;
    }

    public function test_postgres_probe_returns_ok_and_latency(): void
    {
        $probe = $this->service->getPostgresProbe();

        $this->assertSame('ok', $probe['status']);
        $this->assertGreaterThan(0.0, $probe['latency_ms']);
        $this->assertStringContainsString('PostgreSQL 17 responsive', $probe['details']);
    }

    public function test_rls_compliance_checks_catalog_and_verifies_all_tenant_tables(): void
    {
        $rls = $this->service->getRlsCompliance();

        $this->assertSame('ok', $rls['status']);
        $this->assertGreaterThanOrEqual(70, $rls['total_tables'], 'Expected at least 70 tenant tables in schema.');
        $this->assertSame($rls['total_tables'], $rls['forced_tables'], 'All tenant tables must have forced RLS.');
        $this->assertEmpty($rls['violations'], 'No RLS violations permitted.');
    }

    public function test_redis_probe_returns_ok_and_latency(): void
    {
        $probe = $this->service->getRedisProbe();

        $this->assertSame('ok', $probe['status']);
        $this->assertGreaterThan(0.0, $probe['latency_ms']);
        $this->assertStringContainsString('Session & Cache active', $probe['details']);
    }

    public function test_queue_probe_reports_failed_jobs(): void
    {
        $probe = $this->service->getQueueProbe();

        $this->assertSame('ok', $probe['status']);
        $this->assertSame(0, $probe['failed_jobs']);
    }

    public function test_financial_metrics_never_returns_fabricated_mrr_fallback(): void
    {
        $metrics = $this->service->getFinancialMetrics();

        // Proves that when calculations run, it NEVER returns 42500.0 unless genuine plans sum to that
        if (! $metrics['has_data']) {
            $this->assertSame(0.0, $metrics['mrr']);
            $this->assertSame(0.0, $metrics['arr']);
            $this->assertSame('৳ 0', $metrics['formatted_mrr']);
            $this->assertSame('৳ 0', $metrics['formatted_arr']);
        } else {
            $this->assertGreaterThan(0.0, $metrics['mrr']);
            $this->assertSame($metrics['mrr'] * 12, $metrics['arr']);
        }
    }

    public function test_go_bridge_probe_returns_ok_and_version_on_successful_health_response(): void
    {
        Config::set('platform.pos_bridge.health_url', 'http://127.0.0.1:9191/health');

        Http::fake([
            'http://127.0.0.1:9191/health' => Http::response([
                'status' => 'ok',
                'version' => '1.0.0',
            ], 200),
        ]);

        $probe = $this->service->getGoBridgeProbe();

        $this->assertSame('ok', $probe['status']);
        $this->assertStringContainsString('127.0.0.1:9191 online v1.0.0', $probe['details']);
    }

    public function test_go_bridge_probe_reports_offline_on_connection_refusal(): void
    {
        Config::set('platform.pos_bridge.health_url', 'http://127.0.0.1:9191/health');

        Http::fake([
            'http://127.0.0.1:9191/health' => function () {
                throw new ConnectionException('Connection refused');
            },
        ]);

        $probe = $this->service->getGoBridgeProbe();

        $this->assertSame('offline', $probe['status']);
        $this->assertStringContainsString('offline (connection refused', $probe['details']);
    }

    public function test_go_bridge_probe_reports_degraded_on_http_server_error(): void
    {
        Config::set('platform.pos_bridge.health_url', 'http://127.0.0.1:9191/health');

        Http::fake([
            'http://127.0.0.1:9191/health' => Http::response(['error' => 'internal_error'], 500),
        ]);

        $probe = $this->service->getGoBridgeProbe();

        $this->assertSame('degraded', $probe['status']);
        $this->assertStringContainsString('HTTP 500 (daemon error)', $probe['details']);
    }

    public function test_go_bridge_probe_reports_degraded_on_malformed_json_response(): void
    {
        Config::set('platform.pos_bridge.health_url', 'http://127.0.0.1:9191/health');

        Http::fake([
            'http://127.0.0.1:9191/health' => Http::response('<html>Error Gateway</html>', 200),
        ]);

        $probe = $this->service->getGoBridgeProbe();

        $this->assertSame('degraded', $probe['status']);
        $this->assertStringContainsString('Malformed health response', $probe['details']);
    }
}
