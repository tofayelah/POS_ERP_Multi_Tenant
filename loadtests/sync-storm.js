import http from 'k6/http';
import { check } from 'k6';

// Sync Storm Load Test: 50 reconnecting terminals sync batches concurrently (Design §10.2, §13.3)
export const options = {
    scenarios: {
        sync_storm: {
            executor: 'per-vu-iterations',
            vus: 50, // 50 terminals reconnecting simultaneously
            iterations: 2,
            maxDuration: '2m',
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<500'], // 95% of burst syncs under 500ms
        http_req_failed: ['rate<0.05'],    // Less than 5% failure rate (admits 429 backoff)
    },
};

const BASE_URL = __ENV.TARGET_URL || 'http://127.0.0.1:8080';
const TARGET_HOST = __ENV.TARGET_HOST || 'dev.kcerp.com';
const CLIENT_VERSION = __ENV.CLIENT_VERSION || '1.2.0';

export default function () {
    const vuId = __VU;
    const terminalId = `01a10000-0000-7000-0000-${String(vuId).padStart(12, '0')}`;
    const batch = [];

    // Each terminal sends 5 queued offline sales
    for (let i = 0; i < 5; i++) {
        batch.push({
            client_txn_id: `OFFLINE-${vuId}-${Date.now()}-${i}-${Math.floor(Math.random() * 10000)}`,
            terminal_id: terminalId,
            device_time: new Date(Date.now() - (60000 * (5 - i))).toISOString(),
            lines: [
                {
                    product_variant_id: '01a10000-0000-7000-0000-000000000002',
                    quantity: '2.0000',
                    unit_price: '50.0000',
                    discount_amount: '0.0000',
                    tax_amount: '15.0000',
                    line_total: '115.0000',
                },
            ],
            payments: [
                {
                    method: 'cash',
                    amount: '115.0000',
                },
            ],
        });
    }

    const payload = JSON.stringify({
        batch_id: `BATCH-STORM-${vuId}-${Date.now()}`,
        terminal_id: terminalId,
        sales: batch,
    });

    const headers = {
        'Host': TARGET_HOST,
        'Content-Type': 'application/json',
        'X-POS-Client-Version': CLIENT_VERSION,
        'X-POS-Terminal-ID': terminalId,
        'X-POS-Signature': 'mock-sig',
    };

    const res = http.post(`${BASE_URL}/api/v1/pos/sync-batch`, payload, { headers });

    check(res, {
        'status is 200 or 429': (r) => r.status === 200 || r.status === 429,
        'retry-after present on 429': (r) => r.status !== 429 || r.headers['Retry-After'] !== undefined,
    });
}
