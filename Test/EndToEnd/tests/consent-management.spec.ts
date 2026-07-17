import { expect, test } from '@playwright/test';
import { AdminPage } from '../pages/admin.page';
import { loadTestAccess } from '../support/access';

const access = loadTestAccess();

test('administrator publishes all consent locations and storefront adapters render them', async ({ page, browser }) => {
  test.setTimeout(90_000);
  const suffix = Date.now().toString(36);
  const locations = ['registration', 'newsletter', 'contact', 'checkout'];
  const codes = Object.fromEntries(locations.map(location => [location, `e2e_${location}_${suffix}`]));

  const admin = new AdminPage(page, access);
  await admin.login();
  await admin.goto('kkkonrad_gdpr/consent/index');

  for (const location of locations) {
    const createForm = page.locator('form').filter({
      has: page.locator('input[name="code"]:not([type="hidden"])')
    }).first();
    await createForm.locator('input[name="code"]').fill(codes[location]);
    await createForm.locator('input[name="name"]').fill(`E2E ${location}`);
    await createForm.locator('select[name="location"]').selectOption(location);
    await createForm.locator('input[name="purpose"]').fill(`E2E ${location} purpose`);
    await createForm.locator('input[name="is_required"]').check();
    await createForm.locator('textarea[name="content"]').fill(`E2E required ${location} consent ${suffix}`);
    await createForm.locator('button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('main')).toContainText(/consent draft was saved|szkic.*zapis/i);

    const row = page.locator('table.data-grid tbody tr').filter({ hasText: codes[location] }).first();
    await expect(row).toBeVisible();
    await row.getByRole('button', { name: /Publish new version|Opublikuj/i }).click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('table.data-grid tbody tr').filter({ hasText: codes[location] }).first())
      .toContainText('1');
  }

  const storefront = await browser.newContext({ ignoreHTTPSErrors: true });
  const guest = await storefront.newPage();
  await guest.goto(access.customerUrl);
  const configRoot = guest.locator('[data-kkkonrad-gdpr-form-consents]');
  await expect(configRoot).toHaveCount(1);
  const config = JSON.parse((await configRoot.getAttribute('data-config')) || '{}');
  for (const location of locations) {
    expect(config.locations[location].some((definition: { code: string }) => definition.code === codes[location]))
      .toBe(true);
  }

  await guest.goto(new URL('/customer/account/create', access.customerUrl).toString());
  await expect(guest.locator('form.form-create-account .kkkonrad-gdpr-form-consents'))
    .toContainText(`E2E required registration consent ${suffix}`);

  await guest.goto(new URL('/contact', access.customerUrl).toString());
  await expect(guest.locator('form#contact-form .kkkonrad-gdpr-form-consents, form#contact .kkkonrad-gdpr-form-consents'))
    .toContainText(`E2E required contact consent ${suffix}`);
  await expect(guest.locator('form#newsletter-validate-detail .kkkonrad-gdpr-form-consents'))
    .toContainText(`E2E required newsletter consent ${suffix}`);
  await storefront.close();

  await admin.goto('kkkonrad_gdpr/consent/index');
  for (const location of locations) {
    const row = page.locator('table.data-grid tbody tr').filter({ hasText: codes[location] }).first();
    await row.getByRole('button', { name: /Archive|Archiwizuj/i }).click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('main')).toContainText(/archived|zarchiwizowan/i);
  }

  const afterArchive = await browser.newContext({ ignoreHTTPSErrors: true });
  const refreshedGuest = await afterArchive.newPage();
  await refreshedGuest.goto(access.customerUrl);
  const refreshedRoot = refreshedGuest.locator('[data-kkkonrad-gdpr-form-consents]');
  const refreshedConfig = JSON.parse((await refreshedRoot.getAttribute('data-config')) || '{}');
  for (const location of locations) {
    expect((refreshedConfig.locations[location] || [])
      .some((definition: { code: string }) => definition.code === codes[location])).toBe(false);
  }
  await afterArchive.close();
});
