import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  fullyParallel: false,
  // Magento permits one active backend session for this administrator.
  // Parallel spec files would invalidate one another and produce false 403 failures.
  workers: 1,
  retries: process.env.CI ? 2 : 0,
  use: {
    baseURL: process.env.GDPR_BASE_URL || 'https://m10626.app-on-demand.net/',
    trace: 'retain-on-failure',
    ignoreHTTPSErrors: true,
    launchOptions: {
      executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH
        || '/var/www/.cache/ms-playwright/chromium-1187/chrome-linux/chrome'
    }
  },
  projects: [
    { name: 'chromium-desktop', use: { ...devices['Desktop Chrome'] } },
    { name: 'chromium-mobile', use: { ...devices['Pixel 7'] } }
  ]
});
