import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './resources/pos/tests/e2e',
  timeout: 60000,
  expect: {
    timeout: 15000,
  },
  fullyParallel: false,
  retries: 0,
  workers: 1,
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'https://dev.kcerp.com',
    trace: 'on-first-retry',
    viewport: { width: 1366, height: 768 },
    ignoreHTTPSErrors: true,
  },
  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        launchOptions: {
          args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--ignore-certificate-errors',
          ],
        },
      },
    },
  ],
});
