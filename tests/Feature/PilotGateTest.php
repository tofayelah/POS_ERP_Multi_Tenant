<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PilotGateTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'service' => 'kcerp-backend',
        ]);
        $this->assertNotNull($response->json('timestamp'));
    }

    public function test_ready_endpoint_verifies_database_and_cache(): void
    {
        $response = $this->getJson('/ready');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ready',
            'checks' => [
                'database' => 'ok',
                'cache' => 'ok',
                'storage' => 'ok',
            ],
        ]);
    }

    public function test_owasp_security_headers_enforced(): void
    {
        $response = $this->get('/health');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotEmpty($response->headers->get('Content-Security-Policy'));
    }
}
