import http from 'k6/http';
import { check, sleep } from 'k6';

// Eid Peak Load Test: 15 sales/second sustained checkout throughput for 10 minutes (Design §13.3, C-03)
export const options = {
    scenarios: {
        eid_peak: {
            executor: 'constant-arrival-rate',
            rate: 15, // 15 checkouts per second
            timeUnit: '1s',
            duration: __ENV.TEST_DURATION || '10m',
            preAllocatedVUs: 20,
            maxVUs: 50,
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<350'], // 95% of checkouts must process within 350ms (Design §13.3)
        http_req_failed: ['rate<0.01'],    // Less than 1% failure rate
    },
};

const BASE_URL = __ENV.TARGET_URL || 'http://127.0.0.1:8080';
const TARGET_HOST = __ENV.TARGET_HOST || 'dev.kcerp.com';
const TERMINAL_ID = __ENV.TERMINAL_ID || '01a10000-0000-7000-0000-000000000001';
const TERMINAL_KEY = __ENV.TERMINAL_KEY || 'kcerp-terminal-secret-key';
const CLIENT_VERSION = __ENV.CLIENT_VERSION || '1.2.0';

export default function () {
    const txnId = `TXN-${Date.now()}-${Math.floor(Math.random() * 1000000)}`;

    const payload = JSON.stringify({
        batch_id: `BATCH-${Date.now()}-${Math.floor(Math.random() * 100000)}`,
        terminal_id: TERMINAL_ID,
        sales: [
            {
                client_txn_id: txnId,
                terminal_id: TERMINAL_ID,
                device_time: new Date().toISOString(),
                lines: [
                    {
                        product_variant_id: '01a10000-0000-7000-0000-000000000002',
                        quantity: '1.0000',
                        unit_price: '150.0000',
                        discount_amount: '0.0000',
                        tax_amount: '22.5000',
                        line_total: '172.5000',
                    },
                ],
                payments: [
                    {
                        method: 'cash',
                        amount: '172.5000',
                    },
                ],
            },
        ],
    });

    const headers = {
        'Host': TARGET_HOST,
        'Content-Type': 'application/json',
        'X-POS-Client-Version': CLIENT_VERSION,
        'X-POS-Terminal-ID': TERMINAL_ID,
        'X-POS-Signature': 'mock-sig',
    };

    const res = http.post(`${BASE_URL}/api/v1/pos/sync-batch`, payload, { headers });

    check(res, {
        'status is 200 or 429': (r) => r.status === 200 || r.status === 429,
        'latency under 350ms': (r) => r.timings.duration < 350,
    });
}
