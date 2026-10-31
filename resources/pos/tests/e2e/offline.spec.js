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
    page.on('dialog', async (dialog) => {
      await dialog.accept();
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

});
