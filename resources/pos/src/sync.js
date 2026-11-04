/**
 * Retail ERP POS Client Offline Sync Engine (§10.2, §11.2, POS-09, POS-10, API-03)
 * Handles exponential backoff with jitter, HTTP 429 Retry-After, HTTP 426 Upgrade Required,
 * and shift-close version lock to prevent disrupting active cashier shifts.
 */

/**
 * Calculate exponential backoff with full/fractional jitter.
 *
 * @param {number} attempt Zero-based retry attempt number (0, 1, 2, ...)
 * @param {number} baseMs Base backoff in milliseconds (default: 1000ms)
 * @param {number} maxMs Maximum backoff cap in milliseconds (default: 30000ms)
 * @param {number} jitterRatio Jitter variance ratio, e.g. 0.2 for +/- 20% (default: 0.2)
 * @param {() => number} randomFn Random generator function (default: Math.random)
 * @returns {number} Delay in milliseconds
 */
export function calculateBackoff(attempt, baseMs = 1000, maxMs = 30000, jitterRatio = 0.2, randomFn = Math.random) {
    const exponential = Math.min(maxMs, baseMs * Math.pow(2, attempt));
    const jitterFactor = 1 + (randomFn() * 2 - 1) * jitterRatio;
    return Math.max(0, Math.round(exponential * jitterFactor));
}

/**
 * Parse Retry-After header which can be either seconds or HTTP date string.
 *
 * @param {string|null|undefined} headerValue
 * @returns {number} Milliseconds to wait
 */
export function parseRetryAfter(headerValue) {
    if (!headerValue) return 0;
    const seconds = Number(headerValue);
    if (!Number.isNaN(seconds) && seconds >= 0) {
        return seconds * 1000;
    }
    const targetDate = Date.parse(headerValue);
    if (!Number.isNaN(targetDate)) {
        return Math.max(0, targetDate - Date.now());
    }
    return 0;
}

/**
 * Creates an offline POS sync daemon.
 *
 * @param {object} options
 * @param {string} options.clientVersion Current client version (e.g. '1.2.0')
 * @param {string} [options.endpoint] Sync API endpoint
 * @param {Function} [options.fetchFn] Custom fetch implementation
 * @param {object} [options.db] Dexie or storage interface
 * @param {Function} [options.onStatusChange] Status update callback
 * @param {Function} [options.onUpgradeRequired] 426 Upgrade required callback
 * @param {Function} [options.onApplyUpdate] Callback invoked when update is applied at shift close
 */
export function createPosSyncDaemon(options = {}) {
    const clientVersion = options.clientVersion || '1.2.0';
    const endpoint = options.endpoint || '/api/v1/pos/sync-batch';
    const fetchFn = options.fetchFn || (typeof fetch !== 'undefined' ? fetch : null);
    const onStatusChange = options.onStatusChange || (() => {});
    const onUpgradeRequired = options.onUpgradeRequired || (() => {});
    const onApplyUpdate = options.onApplyUpdate || (() => {});

    let isShiftOpen = false;
    let isSyncing = false;
    let consecutiveFailures = 0;
    let retryAfterUntil = 0;
    let upgradeRequired = false;
    let pendingUpdateAvailable = false;
    let statusText = 'Synced';

    const queue = [];

    function updateStatus(newStatus) {
        statusText = newStatus;
        onStatusChange(newStatus);
    }

    return {
        getClientVersion() {
            return clientVersion;
        },

        getQueue() {
            return queue;
        },

        getStatusText() {
            return statusText;
        },

        isUpgradeRequired() {
            return upgradeRequired;
        },

        isPendingUpdateAvailable() {
            return pendingUpdateAvailable;
        },

        isShiftOpen() {
            return isShiftOpen;
        },

        setShiftOpen(open) {
            isShiftOpen = Boolean(open);
        },

        notifyNewVersionAvailable() {
            pendingUpdateAvailable = true;
            if (!isShiftOpen) {
                // If shift is already closed, apply immediately
                return this.applyUpdate();
            }
            updateStatus('Update pending (will apply at shift close)');
            return false;
        },

        applyUpdate() {
            pendingUpdateAvailable = false;
            updateStatus('Applying update...');
            onApplyUpdate();
            return true;
        },

        /**
         * Called when a shift is closed by the cashier/supervisor.
         * Applies any pending application/schema updates safely without disrupting open sales.
         */
        closeShift() {
            isShiftOpen = false;
            if (pendingUpdateAvailable || upgradeRequired) {
                return this.applyUpdate();
            }
            return false;
        },

        enqueue(sale) {
            queue.push(sale);
            updateStatus(`${queue.length} sale${queue.length === 1 ? '' : 's'} waiting`);
        },

        /**
         * Process a single sync cycle.
         */
        async processQueue(batchSize = 25) {
            if (isSyncing || queue.length === 0) {
                return { processed: 0, status: 'idle' };
            }

            // Check 426 Upgrade Required
            if (upgradeRequired) {
                updateStatus('Sync halted: Client upgrade required at shift close');
                return { processed: 0, status: 'upgrade_required' };
            }

            // Check 429 Retry-After window
            const now = Date.now();
            if (now < retryAfterUntil) {
                const waitSec = Math.ceil((retryAfterUntil - now) / 1000);
                updateStatus(`Throttled: Retry after ${waitSec}s`);
                return { processed: 0, status: 'throttled', retryAfterMs: retryAfterUntil - now };
            }

            isSyncing = true;
            updateStatus('Syncing...');

            const batch = queue.slice(0, batchSize);
            const payload = {
                batch_id: 'batch-' + Date.now(),
                terminal_id: batch[0]?.terminal_id || 'terminal-default',
                sales: batch.map(item => ({
                    client_txn_id: item.client_txn_id || item.id,
                    device_time: item.device_time || new Date().toISOString(),
                    pricing_basis: item.pricing_basis || 'tax_inclusive',
                    payment_status: item.payment_status || 'paid',
                    fiscal_signature: item.fiscal_signature || null,
                    sdc_invoice_number: item.sdc_invoice_number || null,
                    items: item.items || [],
                })),
            };

            try {
                const response = await fetchFn(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-POS-Client-Version': clientVersion,
                    },
                    body: JSON.stringify(payload),
                });

                // Handle 426 Upgrade Required
                if (response.status === 426) {
                    upgradeRequired = true;
                    pendingUpdateAvailable = true;
                    updateStatus('426 Upgrade Required: App update locked until shift close');
                    onUpgradeRequired();
                    isSyncing = false;
                    return { processed: 0, status: 'upgrade_required' };
                }

                // Handle 429 Rate Limiting / Admission Control
                if (response.status === 429) {
                    consecutiveFailures++;
                    const retryAfterHeader = response.headers.get('Retry-After');
                    const retryMs = parseRetryAfter(retryAfterHeader) || calculateBackoff(consecutiveFailures);
                    retryAfterUntil = Date.now() + retryMs;

                    updateStatus(`Throttled (429): Retrying in ${Math.round(retryMs / 1000)}s`);
                    isSyncing = false;
                    return { processed: 0, status: 'throttled', retryAfterMs: retryMs };
                }

                if (!response.ok) {
                    throw new Error(`Sync server responded with HTTP ${response.status}`);
                }

                const data = await response.json();

                // Remove successfully synced or duplicate sales from queue
                const processedResults = data.results || [];
                const successfulTxnIds = new Set(
                    processedResults
                        .filter(r => r.status === 'committed' || r.status === 'already_synced')
                        .map(r => r.client_txn_id)
                );

                // Drain processed items from in-memory queue
                let i = 0;
                while (i < queue.length) {
                    const itemTxnId = queue[i].client_txn_id || queue[i].id;
                    if (successfulTxnIds.has(itemTxnId)) {
                        queue.splice(i, 1);
                    } else {
                        i++;
                    }
                }

                consecutiveFailures = 0;
                if (queue.length === 0) {
                    updateStatus('Synced');
                } else {
                    updateStatus(`${queue.length} sale${queue.length === 1 ? '' : 's'} waiting`);
                }

                isSyncing = false;
                return { processed: successfulTxnIds.size, status: 'success', data };
            } catch (err) {
                consecutiveFailures++;
                const backoffMs = calculateBackoff(consecutiveFailures);
                retryAfterUntil = Date.now() + backoffMs;

                updateStatus(`Network offline: Retry in ${Math.round(backoffMs / 1000)}s`);
                isSyncing = false;
                return { processed: 0, status: 'error', error: err.message, retryAfterMs: backoffMs };
            }
        },
    };
}
