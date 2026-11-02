import http from 'k6/http';
import { check, sleep } from 'k6';

// Eid Peak Load Test: 15 sales/second sustained checkout throughput
export const options = {
    scenarios: {
        eid_peak: {
            executor: 'constant-arrival-rate',
            rate: 15, // 15 checkouts per second
            timeUnit: '1s',
            duration: '3m',
            preAllocatedVUs: 20,
            maxVUs: 50,
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<200'], // 95% of checkouts must process within 200ms
        http_req_failed: ['rate<0.01'],    // Less than 1% failure rate
    },
};

const BASE_URL = __ENV.TARGET_URL || 'http://localhost:8080';
const TERMINAL_ID = __ENV.TERMINAL_ID || '01a10000-0000-7000-0000-000000000001';
const TERMINAL_KEY = __ENV.TERMINAL_KEY || 'kcerp-terminal-secret-key';

export default function () {
    const txnId = `TXN-${Date.now()}-${Math.floor(Math.random() * 1000000)}`;

    const payload = JSON.stringify({
        sales: [
            {
                client_txn_id: txnId,
                terminal_id: TERMINAL_ID,
                device_time: new Date().toISOString(),
                lines: [
                    {
                        product_variant_id: '01a10000-0000-7000-0000-000000000002',
                        quantity: '1.000',
                        unit_price: '150.00',
                        discount_amount: '0.00',
                        tax_amount: '22.50',
                        line_total: '172.50',
                    },
                ],
                payments: [
                    {
                        method: 'cash',
                        amount: '172.50',
                    },
                ],
            },
        ],
    });

    const headers = {
        'Content-Type': 'application/json',
        'X-POS-Client-Version': '1.0.0',
        'X-POS-Terminal-ID': TERMINAL_ID,
        'X-POS-Signature': 'mock-sig',
    };

    const res = http.post(`${BASE_URL}/api/v1/pos/sync-batch`, payload, { headers });

    check(res, {
        'status is 200 or 429': (r) => r.status === 200 || r.status === 429,
        'latency under 200ms': (r) => r.timings.duration < 200,
    });
}
