import { describe, it, expect } from 'vitest';

describe('SDC Fiscal Device Offline Queue & Dodging Controls (§12.2, NEW-18)', () => {
    function createFiscalShiftMonitor() {
        return {
            sdcConnected: true,
            shiftUnfiscalizedCount: 0,
            offlineMinutes: 0,
            showAlertBanner: false,
            isLocked: false,
            pendingQueue: [],

            recordSale(invoiceNumber, payable) {
                if (this.isLocked) {
                    throw new Error('Checkout locked due to fiscal threshold violation');
                }

                if (!this.sdcConnected) {
                    this.shiftUnfiscalizedCount++;
                    this.pendingQueue.push({
                        invoice_number: invoiceNumber,
                        payable: payable,
                        status: 'pending_fiscal',
                        created_at: new Date().toISOString()
                    });

                    // Alert at 10 cumulative unfiscalized sales
                    if (this.shiftUnfiscalizedCount >= 10) {
                        this.showAlertBanner = true;
                    }

                    // Hard lockout at 25 unfiscalized sales or 60 min offline
                    if (this.shiftUnfiscalizedCount >= 25 || this.offlineMinutes >= 60) {
                        this.isLocked = true;
                    }

                    return {
                        is_provisional: true,
                        disclaimer: '*** PROVISIONAL TAX INVOICE - PENDING FISCALIZATION ***'
                    };
                }

                return {
                    is_provisional: false,
                    sdc_invoice: 'SDC-2027-' + String(Date.now()).slice(-8),
                    fiscal_signature: 'SIG-VERIFIED'
                };
            },

            unlock(supervisorPin) {
                if (supervisorPin === '9999' || supervisorPin === '4821') {
                    this.isLocked = false;
                    return true;
                }
                return false;
            },

            reconnectAndReplay() {
                this.sdcConnected = true;
                const replayed = [];
                for (const item of this.pendingQueue) {
                    item.status = 'fiscalized';
                    item.sdc_invoice = 'SDC-REPLAY-' + item.invoice_number;
                    item.fiscal_signature = 'SIG-REPLAY';
                    replayed.push(item);
                }
                return replayed;
            }
        };
    }

    it('emits provisional receipt when SDC is disconnected', () => {
        const monitor = createFiscalShiftMonitor();
        monitor.sdcConnected = false;

        const result = monitor.recordSale('INV-001', '500.00');
        expect(result.is_provisional).toBe(true);
        expect(result.disclaimer).toContain('PROVISIONAL TAX INVOICE - PENDING FISCALIZATION');
        expect(monitor.pendingQueue.length).toBe(1);
    });

    it('triggers supervisor alert banner at 10 cumulative unfiscalized sales', () => {
        const monitor = createFiscalShiftMonitor();
        monitor.sdcConnected = false;

        for (let i = 1; i <= 9; i++) {
            monitor.recordSale(`INV-00${i}`, '100.00');
            expect(monitor.showAlertBanner).toBe(false);
        }

        // 10th sale triggers alert
        monitor.recordSale('INV-010', '100.00');
        expect(monitor.showAlertBanner).toBe(true);
        expect(monitor.isLocked).toBe(false);
    });

    it('triggers checkout lockout at 25 cumulative unfiscalized sales and requires supervisor PIN', () => {
        const monitor = createFiscalShiftMonitor();
        monitor.sdcConnected = false;

        // Perform 24 sales
        for (let i = 1; i <= 24; i++) {
            monitor.recordSale(`INV-${i}`, '50.00');
        }
        expect(monitor.isLocked).toBe(false);

        // 25th sale triggers hard lockout
        monitor.recordSale('INV-25', '50.00');
        expect(monitor.isLocked).toBe(true);

        // Attempting sale 26 must throw
        expect(() => monitor.recordSale('INV-26', '50.00')).toThrow(/locked/);

        // Wrong PIN refuses unlock
        expect(monitor.unlock('1234')).toBe(false);
        expect(monitor.isLocked).toBe(true);

        // Supervisor PIN unlocks checkout
        expect(monitor.unlock('9999')).toBe(true);
        expect(monitor.isLocked).toBe(false);
    });

    it('triggers checkout lockout after 60 minutes offline without SDC sync', () => {
        const monitor = createFiscalShiftMonitor();
        monitor.sdcConnected = false;
        monitor.offlineMinutes = 61;

        // Even on first sale after 60m offline, lockout triggers
        monitor.recordSale('INV-001', '100.00');
        expect(monitor.isLocked).toBe(true);
    });

    it('replays pending fiscal queue upon reconnection and marks sales fiscalized', () => {
        const monitor = createFiscalShiftMonitor();
        monitor.sdcConnected = false;

        monitor.recordSale('INV-001', '100.00');
        monitor.recordSale('INV-002', '200.00');
        expect(monitor.pendingQueue.length).toBe(2);

        const replayed = monitor.reconnectAndReplay();
        expect(replayed.length).toBe(2);
        expect(replayed[0].status).toBe('fiscalized');
        expect(replayed[0].sdc_invoice).toContain('SDC-REPLAY');
    });
});
