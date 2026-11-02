import { test, expect } from '@playwright/test';
import path from 'path';

test.describe('Master Data Ingestion & Dry-Run Error Reporting (Step 13B, TEN-06)', () => {

  test('uploads Excel with five deliberate errors and verifies all five rows are named in dry-run report', async ({ page }) => {
    // 1. Navigate to Master Data Import web interface
    await page.goto('/import/master-data');
    await expect(page).toHaveTitle(/Master Data Import/);

    // 2. Locate the Excel sample file in .scratch/
    const sampleFilePath = path.resolve('.scratch/sample_products_with_errors.xlsx');

    // 3. Set input file
    const fileChooserPromise = page.waitForEvent('filechooser').catch(() => null);
    await page.getByTestId('import-file-input').setInputFiles(sampleFilePath);

    // 4. Verify Dry-Run checkbox is active (Zero Database Writes)
    const dryRunCheckbox = page.getByTestId('dry-run-checkbox');
    await expect(dryRunCheckbox).toBeChecked();

    // 5. Submit import form
    await page.getByTestId('btn-submit-import').click();

    // 6. Wait for Dry-Run Report container
    const report = page.getByTestId('dry-run-report');
    await expect(report).toBeVisible({ timeout: 15000 });

    // 7. Verify all five deliberate errors are explicitly reported by row
    // Row 1: Missing Product Code
    const row1 = page.getByTestId('error-row-1');
    await expect(row1).toBeVisible();
    await expect(row1).toContainText('Row 1');
    await expect(row1).toContainText('code');
    await expect(row1).toContainText('Product code is required');

    // Row 2: Missing English Name
    const row2 = page.getByTestId('error-row-2');
    await expect(row2).toBeVisible();
    await expect(row2).toContainText('Row 2');
    await expect(row2).toContainText('name_en');
    await expect(row2).toContainText('Product English name is required');

    // Row 3: Missing Variant SKU
    const row3 = page.getByTestId('error-row-3');
    await expect(row3).toBeVisible();
    await expect(row3).toContainText('Row 3');
    await expect(row3).toContainText('sku');
    await expect(row3).toContainText('Variant SKU is required');

    // Row 4: Negative MRP Price
    const row4 = page.getByTestId('error-row-4');
    await expect(row4).toBeVisible();
    await expect(row4).toContainText('Row 4');
    await expect(row4).toContainText('mrp');
    await expect(row4).toContainText('MRP must be a non-negative number');

    // Row 5: Negative Cost Price
    const row5 = page.getByTestId('error-row-5');
    await expect(row5).toBeVisible();
    await expect(row5).toContainText('Row 5');
    await expect(row5).toContainText('cost_price');
    await expect(row5).toContainText('Cost price must be a non-negative number');

    // 8. Capture full-page screenshot of the dry run report
    await page.screenshot({ path: '.scratch/dry_run_report_screenshot.png', fullPage: true });
    console.log('Successfully verified 5/5 deliberate row errors in dry-run report and captured screenshot.');
  });

});
