import { expect, test } from '@playwright/test';
import { AdminPage } from '../pages/admin.page';
import { loadTestAccess } from '../support/access';

const access = loadTestAccess();

test('restore the original safe GDPR feature switches', async ({ page }) => {
  test.skip(process.env.GDPR_RESTORE_CONFIG !== '1', 'Explicit final cleanup only.');
  const admin = new AdminPage(page, access);
  await admin.login();
  await admin.goto('admin/system_config/edit/section/kkkonrad_gdpr');

  const values: Record<string, Record<string, string>> = {
    general: { enabled: '0' },
    data_rights: {
      dashboard_enabled: '1',
      export_request_enabled: '0',
      anonymization_request_enabled: '0',
      erasure_request_enabled: '0'
    },
    consent: {
      enabled: '0', registration_enabled: '0', newsletter_enabled: '0',
      contact_enabled: '0', checkout_enabled: '0', link_guest_consents_on_login: '0'
    },
    cookie: {
      enabled: '0', banner_enabled: '1', rejected_tracking_enabled: '0', track_unknown_only: '1',
      geolocation_enabled: '0', banner_region_mode: 'global', lock_screen_enabled: '0'
    },
    google_consent: { enabled: '0', debug_enabled: '0' }
  };

  for (const [group, fields] of Object.entries(values)) {
    const first = page.locator(`#kkkonrad_gdpr_${group}_${Object.keys(fields)[0]}`);
    if (!(await first.isVisible())) await page.locator(`a[href="#kkkonrad_gdpr_${group}-link"]`).click();
    for (const [field, value] of Object.entries(fields)) {
      await page.locator(`#kkkonrad_gdpr_${group}_${field}`).selectOption(value);
    }
  }

  await page.locator('#save').click();
  await page.waitForLoadState('domcontentloaded');
  await expect(page.locator('#messages .message-success, .message-success').first()).toBeVisible();

  await page.goto(access.customerUrl);
  await expect(page.locator('[data-kkkonrad-gdpr-cmp]')).toHaveCount(0);
});
