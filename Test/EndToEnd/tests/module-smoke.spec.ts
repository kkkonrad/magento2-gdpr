import { expect, test } from '@playwright/test';
import { AdminPage } from '../pages/admin.page';
import { CustomerPage } from '../pages/customer.page';
import { loadTestAccess } from '../support/access';

const access = loadTestAccess();

test('customer can authenticate and open the privacy dashboard', async ({ page }) => {
  const customer = new CustomerPage(page, access);
  await customer.login();
  await customer.goto('/gdpr/privacy/index');
  await expect(page.locator('#maincontent')).toBeVisible();
});

test('administrator can open every GDPR module page', async ({ page }) => {
  const browserErrors: string[] = [];
  const failedResponses: string[] = [];
  page.on('pageerror', error => browserErrors.push(error.message));
  page.on('console', message => {
    if (message.type() === 'error') browserErrors.push(message.text());
  });
  page.on('response', response => {
    if (response.status() >= 500) failedResponses.push(`${response.status()} ${response.url()}`);
  });

  const admin = new AdminPage(page, access);
  await admin.login();
  const paths = [
    'kkkonrad_gdpr/request/index',
    'kkkonrad_gdpr/consent/index',
    'kkkonrad_gdpr/cookie/index',
    'kkkonrad_gdpr/health/index',
    'admin/system_config/edit/section/kkkonrad_gdpr'
  ];
  for (const path of paths) {
    const gridResponsePromise = path === 'kkkonrad_gdpr/request/index'
      ? page.waitForResponse(
        response => response.url().includes('/mui/index/render') && response.request().method() === 'GET',
        { timeout: 20_000 }
      )
      : null;
    await admin.goto(path);
    await expect(page.locator('main, #maincontent, .page-main-actions').first()).toBeVisible();

    if (gridResponsePromise) {
      const gridResponse = await gridResponsePromise;
      expect(gridResponse.status(), await gridResponse.text()).toBe(200);
      await expect(page.locator('[data-role="spinner"][data-component*="kkkonrad_gdpr_request_listing"]'))
        .toBeHidden({ timeout: 20_000 });
      await expect(page.locator('main table[data-role="grid"]')).toBeVisible();
    }
  }

  expect(failedResponses, `Server errors: ${failedResponses.join('\n')}`).toEqual([]);
  expect(browserErrors, `Browser errors: ${browserErrors.join('\n')}`).toEqual([]);
});
