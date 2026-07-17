import { expect, test } from '@playwright/test';
import { AdminPage } from '../pages/admin.page';
import { loadTestAccess } from '../support/access';

const access = loadTestAccess();

test('administrator manages the cookie catalog and rejected-cookie diagnostics', async ({ page }) => {
  const suffix = Date.now().toString(36);
  const groupCode = `e2e_group_${suffix}`;
  const groupName = `E2E cookie group ${suffix}`;
  const cookiePattern = `e2e_storage_${suffix}*`;
  const admin = new AdminPage(page, access);
  await admin.login();
  await admin.goto('kkkonrad_gdpr/cookie/index');

  const groupForm = page.locator('form[action*="/cookie/saveGroup/"]');
  await groupForm.locator('input[name="code"]').fill(groupCode);
  await groupForm.locator('select[name="type"]').selectOption('custom');
  await groupForm.locator('input[name="name"]').fill(groupName);
  await groupForm.locator('input[name="description"]').fill('Temporary browser verification group');
  await groupForm.locator('button[type="submit"]').click();
  await page.waitForLoadState('domcontentloaded');
  await expect(page.locator('main')).toContainText(/cookie group was saved|grupa.*zapis/i);

  const cookieForm = page.locator('form[action*="/cookie/saveCookie/"]');
  const groupOption = cookieForm.locator('select[name="group_id"] option').filter({ hasText: groupName });
  await expect(groupOption).toHaveCount(1);
  await cookieForm.locator('select[name="group_id"]').selectOption(await groupOption.getAttribute('value') || '');
  await cookieForm.locator('input[name="name"]').fill(`E2E storage ${suffix}`);
  await cookieForm.locator('input[name="code_pattern"]').fill(cookiePattern);
  await cookieForm.locator('select[name="storage_type"]').selectOption('local_storage');
  await cookieForm.locator('input[name="lifetime"]').fill('3600');
  await cookieForm.locator('button[type="submit"]').click();
  await page.waitForLoadState('domcontentloaded');
  await expect(page.locator('main')).toContainText(/cookie definition was saved|definicja.*zapis/i);
  await expect(page.locator('.admin__data-grid-wrap').filter({ hasText: /Active catalog|Aktywny katalog/i })
    .locator('table.data-grid'))
    .toContainText(cookiePattern);

  const diagnosticRow = page.locator('table.data-grid tbody tr').filter({ hasText: 'e2e_unknown_cookie' }).first();
  await expect(diagnosticRow).toContainText(/Yes|Tak/i);
  const draftForm = diagnosticRow.locator('form[action*="/cookie/createFromRejected/"]');
  await draftForm.locator('select[name="group_id"]').selectOption(await groupOption.getAttribute('value') || '');
  await draftForm.locator('button[type="submit"]').click();
  await page.waitForLoadState('domcontentloaded');
  await expect(page.locator('main')).toContainText(/inactive cookie draft (was created|already exists)|nieaktywn.*szkic/i);
});
