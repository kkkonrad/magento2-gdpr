import { expect, test } from '@playwright/test';
import { AdminPage } from '../pages/admin.page';
import { loadTestAccess } from '../support/access';

const access = loadTestAccess();

test('administrator enables all interactive GDPR areas for acceptance testing', async ({ page }) => {
  test.setTimeout(120_000);
  const admin = new AdminPage(page, access);
  await admin.login();
  await admin.goto('admin/system_config/edit/section/kkkonrad_gdpr');

  const enabledByGroup: Record<string, string[]> = {
    general: ['enabled'],
    data_rights: ['dashboard_enabled', 'export_request_enabled', 'anonymization_request_enabled', 'erasure_request_enabled'],
    consent: ['enabled', 'registration_enabled', 'newsletter_enabled', 'contact_enabled', 'checkout_enabled', 'link_guest_consents_on_login'],
    cookie: ['enabled', 'banner_enabled', 'rejected_tracking_enabled', 'track_unknown_only'],
    google_consent: ['enabled', 'debug_enabled']
  };
  for (const [group, fields] of Object.entries(enabledByGroup)) {
    const firstControl = page.locator(`#kkkonrad_gdpr_${group}_${fields[0]}`);
    if (!(await firstControl.isVisible())) {
      await page.locator(`a[href="#kkkonrad_gdpr_${group}-link"]`).click();
    }
    await expect(firstControl).toBeVisible();
    for (const field of fields) {
      await page.locator(`#kkkonrad_gdpr_${group}_${field}`).selectOption('1');
    }
    if (group === 'cookie') {
      await page.locator('#kkkonrad_gdpr_cookie_geolocation_enabled').selectOption('0');
      await page.locator('#kkkonrad_gdpr_cookie_banner_region_mode').selectOption('global');
      await page.locator('#kkkonrad_gdpr_cookie_lock_screen_enabled').selectOption('0');
    }
  }

  await page.locator('#save').click();
  await page.waitForLoadState('domcontentloaded');
  await expect(page.locator('#messages .message-success, .message-success').first()).toBeVisible();
  await expect(page.locator('body')).not.toContainText(/Exception #\d+|There has been an error processing your request/i);
});
