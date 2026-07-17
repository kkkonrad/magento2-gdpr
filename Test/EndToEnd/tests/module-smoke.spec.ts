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
    await admin.goto(path);
    await expect(page.locator('main, #maincontent, .page-main-actions').first()).toBeVisible();
  }
});
