import { describe, it, expect, vi } from 'vitest';
import { calculateBackoff, parseRetryAfter, createPosSyncDaemon } from '../src/sync.js';

describe('POS Offline Sync Retry, Admission Control & Shift-Close Version Lock (§10.2, §11.2, POS-09, POS-10, API-03)', () => {
    describe('calculateBackoff', () => {
        it('calculates exponential backoff correctly with zero jitter', () => {
            const deterministicRandom = () => 0.5; // jitterFactor = 1 + (0.5 * 2 - 1) * jitter = 1.0

            const attempt0 = calculateBackoff(0, 1000, 30000, 0.2, deterministicRandom);
            const attempt1 = calculateBackoff(1, 1000, 30000, 0.2, deterministicRandom);
            const attempt2 = calculateBackoff(2, 1000, 30000, 0.2, deterministicRandom);
            const attempt3 = calculateBackoff(3, 1000, 30000, 0.2, deterministicRandom);
            const attempt6 = calculateBackoff(6, 1000, 30000, 0.2, deterministicRandom);

            expect(attempt0).toBe(1000);  // 1000 * 2^0
            expect(attempt1).toBe(2000);  // 1000 * 2^1
            expect(attempt2).toBe(4000);  // 1000 * 2^2
            expect(attempt3).toBe(8000);  // 1000 * 2^3
            expect(attempt6).toBe(30000); // capped at maxMs (30000)
        });

        it('applies jitter within expected ratio bounds', () => {
            const minRandom = () => 0.0; // jitterFactor = 1 - 0.2 = 0.8
            const maxRandom = () => 1.0; // jitterFactor = 1 + 0.2 = 1.2

            const minDelay = calculateBackoff(2, 1000, 30000, 0.2, minRandom);
            const maxDelay = calculateBackoff(2, 1000, 30000, 0.2, maxRandom);

            expect(minDelay).toBe(3200); // 4000 * 0.8
            expect(maxDelay).toBe(4800); // 4000 * 1.2
        });
    });

    describe('parseRetryAfter', () => {
        it('parses seconds into milliseconds', () => {
            expect(parseRetryAfter('15')).toBe(15000);
            expect(parseRetryAfter('0')).toBe(0);
            expect(parseRetryAfter(null)).toBe(0);
        });

        it('parses HTTP date string into future milliseconds', () => {
            const futureDate = new Date(Date.now() + 20000).toUTCString();
            const delay = parseRetryAfter(futureDate);
            expect(delay).toBeGreaterThan(15000);
            expect(delay).toBeLessThanOrEqual(20000);
        });
    });

    describe('HTTP 429 Admission Control & Backoff', () => {
        it('respects 429 Retry-After and pauses sync queue until elapsed', async () => {
            let mockStatus = 429;
            const mockHeaders = new Map([['Retry-After', '10']]);

            const mockFetch = vi.fn().mockImplementation(async () => ({
                status: mockStatus,
                ok: false,
                headers: {
                    get: (name) => mockHeaders.get(name) || null,
                },
            }));

            const daemon = createPosSyncDaemon({
                clientVersion: '1.2.0',
                fetchFn: mockFetch,
            });

            daemon.enqueue({ id: 'sale-1', client_txn_id: 'txn-1', items: [] });
            expect(daemon.getQueue().length).toBe(1);

            // First run: server replies 429 with Retry-After: 10s
            const res1 = await daemon.processQueue();
            expect(res1.status).toBe('throttled');
            expect(res1.retryAfterMs).toBe(10000);
            expect(daemon.getStatusText()).toContain('Throttled (429)');

            // Immediate second run: daemon throttles without sending network request
            mockFetch.mockClear();
            const res2 = await daemon.processQueue();
            expect(res2.status).toBe('throttled');
            expect(mockFetch).not.toHaveBeenCalled();
            expect(daemon.getQueue().length).toBe(1); // Sale still preserved in queue
        });
    });

    describe('HTTP 426 Versioning & Shift-Close Update Lock', () => {
        it('halts sync on 426 and does not apply update while cashier shift is open', async () => {
            const mockFetch = vi.fn().mockResolvedValue({
                status: 426,
                ok: false,
                headers: { get: () => null },
                json: async () => ({ message: 'Upgrade Required' }),
            });

            let updateApplied = false;
            const daemon = createPosSyncDaemon({
                clientVersion: '1.0.0', // obsolete client
                fetchFn: mockFetch,
                onApplyUpdate: () => {
                    updateApplied = true;
                },
            });

            // Cashier opens shift
            daemon.setShiftOpen(true);
            expect(daemon.isShiftOpen()).toBe(true);

            daemon.enqueue({ id: 'sale-old', client_txn_id: 'txn-old', items: [] });

            // Sync attempt receives 426 Upgrade Required
            const res = await daemon.processQueue();
            expect(res.status).toBe('upgrade_required');
            expect(daemon.isUpgradeRequired()).toBe(true);
            expect(daemon.isPendingUpdateAvailable()).toBe(true);
            // CRITICAL: Update must NOT be applied while shift is actively open
            expect(updateApplied).toBe(false);
            expect(daemon.getStatusText()).toContain('426 Upgrade Required');

            // Cashier closes shift at end of day
            const closed = daemon.closeShift();
            expect(closed).toBe(true);
            expect(daemon.isShiftOpen()).toBe(false);
            // NOW update is safely applied!
            expect(updateApplied).toBe(true);
            expect(daemon.isPendingUpdateAvailable()).toBe(false);
        });

        it('notifies user of new version available but defers application until shift close', () => {
            let updateCount = 0;
            const daemon = createPosSyncDaemon({
                clientVersion: '1.2.0',
                onApplyUpdate: () => {
                    updateCount++;
                },
            });

            daemon.setShiftOpen(true);

            // New service worker version detected in background
            const appliedImmediate = daemon.notifyNewVersionAvailable();
            expect(appliedImmediate).toBe(false);
            expect(updateCount).toBe(0);
            expect(daemon.getStatusText()).toBe('Update pending (will apply at shift close)');

            // End of shift
            daemon.closeShift();
            expect(updateCount).toBe(1);
        });
    });

    describe('Batch processing success & idempotency handling', () => {
        it('removes committed and already_synced sales from queue while leaving failed sales', async () => {
            const mockFetch = vi.fn().mockResolvedValue({
                status: 200,
                ok: true,
                headers: { get: () => null },
                json: async () => ({
                    status: 'success',
                    results: [
                        { client_txn_id: 'txn-1', status: 'committed', invoice_id: 'inv-1' },
                        { client_txn_id: 'txn-2', status: 'already_synced', invoice_id: 'inv-2' },
                        { client_txn_id: 'txn-3', status: 'failed', reason: 'Invalid SKU' },
                    ],
                }),
            });

            const daemon = createPosSyncDaemon({
                clientVersion: '1.2.0',
                fetchFn: mockFetch,
            });

            daemon.enqueue({ id: '1', client_txn_id: 'txn-1' });
            daemon.enqueue({ id: '2', client_txn_id: 'txn-2' });
            daemon.enqueue({ id: '3', client_txn_id: 'txn-3' });

            expect(daemon.getQueue().length).toBe(3);

            const res = await daemon.processQueue();
            expect(res.status).toBe('success');
            expect(res.processed).toBe(2);

            // txn-1 and txn-2 drained, txn-3 retained for retry
            expect(daemon.getQueue().length).toBe(1);
            expect(daemon.getQueue()[0].client_txn_id).toBe('txn-3');
            expect(daemon.getStatusText()).toBe('1 sale waiting');
        });
    });
});
