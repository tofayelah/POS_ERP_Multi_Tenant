import { test, expect } from '@playwright/test';

test.describe('Offline POS Shell & Cashier Flow (Step 8, POS-04, POS-07, POS-08, NEW-32)', () => {

  test('sells while offline, keeps the cart on reload, syncs once', async ({ page, context }) => {
    // 1. Initial page load
    await page.goto('/pos');

    // 2. Clear Dexie database for fresh test run
    await page.evaluate(async () => {
      if (window.indexedDB) {
        const dbs = await window.indexedDB.databases();
        for (const d of dbs) {
          if (d.name) {
            window.indexedDB.deleteDatabase(d.name);
          }
        }
      }
    });
    await page.reload();

    // 3. Cashier PIN sign-in
    await page.getByLabel('Cashier PIN').fill('4821');
    await page.keyboard.press('Enter');

    // 4. Ensure Service Worker is registered and active
    await page.waitForFunction(async () => {
      if (!('serviceWorker' in navigator)) return true;
      const reg = await navigator.serviceWorker.getRegistration();
      return reg && reg.active;
    }, { timeout: 10000 }).catch(() => {});

    // 5. Go offline
    await context.setOffline(true);

    // 6. Scan product (Mustard Oil barcode)
    await page.getByTestId('scan').fill('8941100234567');
    await page.keyboard.press('Enter');

    // 7. Verify line item in cart before reload
    await expect(page.getByTestId('cart-lines')).toContainText('Mustard Oil');

    // 8. Reload mid-sale while offline: cart must survive
    await page.reload();
    await expect(page.getByTestId('cart-lines')).toContainText('Mustard Oil');

    // 9. Complete sale while offline
    await page.getByRole('button', { name: 'Cash' }).click();
    await page.getByRole('button', { name: 'Complete sale' }).click();

    // 10. Assert 1 sale queued in local Dexie buffer
    await expect(page.getByTestId('sync-status')).toHaveText(/1 sale waiting/);

    // 11. Restore network and verify automatic sync to server
    await context.setOffline(false);
    await expect(page.getByTestId('sync-status')).toHaveText('Synced', { timeout: 30000 });
  });

  test('full cashier day flow: float, discount ceilings, mid-sale reload, pin lockout, denomination count', async ({ page, context }) => {
    // Automatically accept any browser alert dialogs
    page.on('dialog', (dialog) => {
      dialog.accept().catch(() => {});
    });

    await page.goto('/pos');

    // Clean start
    await page.evaluate(async () => {
      if (window.indexedDB) {
        const dbs = await window.indexedDB.databases();
        for (const d of dbs) {
          if (d.name) {
            window.indexedDB.deleteDatabase(d.name);
          }
        }
      }
    });
    await page.reload();

    // 1. PIN Authentication
    await page.getByLabel('Cashier PIN').fill('4821');
    await page.keyboard.press('Enter');

    // 2. Open Shift with ৳2,000 starting cash float
    const floatInput = page.locator('#opening-float-input');
    if (await floatInput.isVisible({ timeout: 2000 }).catch(() => false)) {
      await floatInput.fill('2000');
      await page.locator('#btn-confirm-open-shift').click();
    }

    // 3. Scan Three Products
    await page.locator('#btn-quick-rice').click();
    await page.locator('#btn-quick-oil').click();
    await page.locator('#btn-quick-soap').click();

    await expect(page.getByTestId('cart-lines')).toContainText('Miniket Rice 5kg');
    await expect(page.getByTestId('cart-lines')).toContainText('Pure Mustard Oil 1L');
    await expect(page.getByTestId('cart-lines')).toContainText('Arogya Neem Soap 125g');

    // 4. Discount ceiling check (§3.2): 8% refused for Cashier
    await page.locator('#btn-open-discount').click();
    await page.locator('#discount-input').fill('8');
    await page.locator('#btn-confirm-discount').click();
    await expect(page.locator('#discount-error-alert')).toBeVisible();
    await expect(page.locator('#discount-error-alert')).toContainText('exceeds cashier limit and is REFUSED');

    // 5. Discount 5% accepted for Cashier
    await page.locator('#discount-input').fill('5');
    await page.locator('#btn-confirm-discount').click();
    await expect(page.locator('#grand-total-display')).toHaveText('৳755.25');

    // 6. Tender Cash and complete Sale 1
    await page.getByRole('button', { name: 'Cash' }).click();
    await page.locator('#tendered-amount-input').fill('1000');
    await expect(page.locator('#change-due-display')).toHaveText('৳244.75');
    await page.getByRole('button', { name: 'Complete sale' }).click();

    // 7. Go offline
    await context.setOffline(true);

    // 8. Offline Sale 2
    await page.locator('#btn-quick-oil').click();
    await page.getByRole('button', { name: 'Cash' }).click();
    await page.locator('#tendered-amount-input').fill('500');
    await page.getByRole('button', { name: 'Complete sale' }).click();

    // 9. Offline Sale 3: Scan items & reload mid-sale
    await page.locator('#btn-quick-rice').click();
    await page.locator('#btn-quick-soap').click();
    await expect(page.getByTestId('cart-lines')).toContainText('Miniket Rice 5kg');
    await expect(page.getByTestId('cart-lines')).toContainText('Arogya Neem Soap 125g');

    // Reload mid-sale while offline: cart must restore completely
    await page.reload();
    await expect(page.getByTestId('cart-lines')).toContainText('Miniket Rice 5kg');
    await expect(page.getByTestId('cart-lines')).toContainText('Arogya Neem Soap 125g');

    await page.getByRole('button', { name: 'Cash' }).click();
    await page.locator('#tendered-amount-input').fill('600');
    await page.getByRole('button', { name: 'Complete sale' }).click();

    // 10. Security Lockout: 3 wrong PIN attempts
    await page.locator('button:has-text("Lock PIN")').click();
    for (let i = 0; i < 3; i++) {
      await page.getByLabel('Cashier PIN').fill('1111');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(300);
    }
    await expect(page.locator('#lockout-banner')).toBeVisible();
    await expect(page.locator('#lockout-banner')).toContainText('SECURITY LOCKOUT: 3 consecutive failed PIN attempts');

    // Unlock terminal for end-of-shift reconciliation
    await page.evaluate(() => {
      const app = Alpine.$data(document.querySelector('body'));
      if (app) {
        app.isLocked = false;
        app.failedPinCount = 0;
        app.showPinModal = false;
      }
    });

    // 11. End-of-shift Cash Denomination Count
    await page.locator('#btn-open-close-shift').click();
    // Denomination count for ৳3,550 physical cash in drawer: 3x1000 + 1x500 + 1x50
    await page.locator('#denom-1000').fill('3');
    await page.locator('#denom-500').fill('1');
    await page.locator('#denom-50').fill('1');
    await page.locator('#denom-50').dispatchEvent('input');

    await expect(page.locator('#counted-actual-cash')).toHaveText('৳3550');
    await page.locator('#btn-confirm-close-shift').click();

    // 12. Restore network and assert all offline sales synchronize
    await context.setOffline(false);
    await expect(page.getByTestId('sync-status')).toHaveText('Synced', { timeout: 30000 });
  });

  test('no shortcut key opens a browser feature on a terminal (POS-11, NEW-32)', async ({ page }) => {
    await page.goto('/pos');
    await page.getByLabel('Cashier PIN').fill('4821');
    await page.keyboard.press('Enter');

    // 1. Test F10, F11, F12, F5 suppression (prevented via e.preventDefault())
    for (const key of ['F10', 'F11', 'F12', 'F5']) {
      await page.keyboard.press(key);
      const prevented = await page.evaluate(() => window.__preventedShortcut);
      expect(prevented).toBe(key);
    }

    // 2. Test Ctrl+R and Ctrl+P suppression
    await page.keyboard.press('Control+r');
    let prevented = await page.evaluate(() => window.__preventedShortcut);
    expect(['r', 'R']).toContain(prevented);

    await page.keyboard.press('Control+p');
    prevented = await page.evaluate(() => window.__preventedShortcut);
    expect(['p', 'P']).toContain(prevented);

    // 3. Test POS shortcut routing: F1 focuses barcode input
    await page.keyboard.press('F1');
    await expect(page.getByTestId('scan')).toBeFocused();

    // 4. Test POS shortcut routing: F3 opens discount modal
    await page.keyboard.press('F3');
    await expect(page.locator('#discount-input')).toBeVisible();

    // 5. Test Escape closes modals
    await page.keyboard.press('Escape');
    await expect(page.locator('#discount-input')).not.toBeVisible();
  });

  test('returns and refunds: offline return requires supervisor PIN and creates linked credit note (NEW-32)', async ({ page, context }) => {
    page.on('dialog', (dialog) => {
      dialog.accept().catch(() => {});
    });

    await page.goto('/pos');
    await page.getByLabel('Cashier PIN').fill('4821');
    await page.keyboard.press('Enter');

    // Go offline
    await context.setOffline(true);

    // Open Return / Refund modal
    await page.locator('#btn-open-return').click();
    await expect(page.locator('#return-supervisor-pin')).toBeVisible();

    // 1. Cashier attempts return without supervisor PIN -> Refused
    await page.locator('#return-supervisor-pin').fill('4821'); // Cashier PIN
    await page.locator('#return-invoice-ref').fill('INV-DHA01-T01-123456');
    await page.locator('#return-amount-input').fill('280.00');
    await page.locator('#btn-confirm-return').click();

    await expect(page.locator('#return-error-alert')).toBeVisible();
    await expect(page.locator('#return-error-alert')).toContainText('Supervisor PIN required');

    // 2. Authorize with Supervisor PIN (9999) -> Accepted
    await page.locator('#return-supervisor-pin').fill('9999');
    await page.locator('#btn-confirm-return').click();

    // 3. Verify Credit Note saved in Dexie pending_sales
    const creditNote = await page.evaluate(async () => {
      return await window.db.pending_sales.where('type').equals('CREDIT_NOTE').first();
    });

    expect(creditNote).toBeTruthy();
    expect(creditNote.type).toBe('CREDIT_NOTE');
    expect(creditNote.original_invoice).toBe('INV-DHA01-T01-123456');
    expect(creditNote.payable).toBe('-280.00');
    expect(creditNote.reason_code).toBe('DAMAGED_GOODS');
  });

  test('offline MFS rules: bKash/Nagad/Card require manual reference and are marked pending_verification (NEW-32)', async ({ page, context }) => {
    await page.goto('/pos');
    await page.getByLabel('Cashier PIN').fill('4821');
    await page.keyboard.press('Enter');

    // Go offline
    await context.setOffline(true);

    // Add Mustard Oil (৳280) to cart
    await page.locator('#btn-quick-oil').click();

    // Open tender modal and switch to bKash
    await page.getByRole('button', { name: 'Cash' }).click();
    await page.locator('#tender-tab-bkash').click();

    // 1. Try to complete sale offline without manual TrxID -> Refused
    await page.getByRole('button', { name: 'Complete sale' }).click();
    await expect(page.locator('#tender-error-alert')).toBeVisible();
    await expect(page.locator('#tender-error-alert')).toContainText('Manual reference / TrxID is required for offline BKASH payments');

    // 2. Provide manual TrxID -> Accepted
    await page.getByTestId('mfs-reference').fill('TRX-BKASH-778899');
    await page.getByRole('button', { name: 'Complete sale' }).click();

    // 3. Verify sale saved in Dexie with status pending_verification
    const mfsSale = await page.evaluate(async () => {
      return await window.db.pending_sales.where('payment_method').equals('BKASH').first();
    });

    expect(mfsSale).toBeTruthy();
    expect(mfsSale.payment_method).toBe('BKASH');
    expect(mfsSale.mfs_reference).toBe('TRX-BKASH-778899');
    expect(mfsSale.status).toBe('pending_verification');
  });

});
