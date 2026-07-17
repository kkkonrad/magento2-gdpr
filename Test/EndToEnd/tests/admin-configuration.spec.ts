import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';
import { AdminPage } from '../pages/admin.page';
import { loadTestAccess } from '../support/access';

const access = loadTestAccess();

function configuredFieldIds(): string[] {
  const xml = readFileSync('/var/www/html/app/code/Kkkonrad/Gdpr/etc/adminhtml/system.xml', 'utf8');
  const result: string[] = [];
  for (const group of xml.matchAll(/<group id="([^"]+)"[\s\S]*?<\/group>/g)) {
    for (const field of group[0].matchAll(/<field id="([^"]+)"/g)) {
      result.push(`kkkonrad_gdpr_${group[1]}_${field[1]}`);
    }
  }
  return result;
}

test('every GDPR configuration field renders and the complete section saves', async ({ page }) => {
  test.setTimeout(120_000);
  const browserErrors: string[] = [];
  page.on('pageerror', error => browserErrors.push(error.message));
  page.on('console', message => {
    if (message.type() === 'error') browserErrors.push(message.text());
  });

  const admin = new AdminPage(page, access);
  await admin.login();
  await admin.goto('admin/system_config/edit/section/kkkonrad_gdpr');

  const fields = configuredFieldIds();
  expect(fields.length).toBeGreaterThan(50);
  for (const id of fields) {
    const control = page.locator(`#${id}`);
    await expect(control, `Missing system configuration control #${id}`).toHaveCount(1);
    if (await control.evaluate(element => element.tagName === 'SELECT')) {
      expect(await control.locator('option').count(), `No options in #${id}`).toBeGreaterThan(0);
    }
  }

  await page.locator('#save').click();
  await page.waitForLoadState('domcontentloaded');
  await expect(page.locator('body')).not.toContainText(/Exception #\d+|Email template is not defined|Szablon e-mail nie jest zdefiniowany/i);
  await expect(page.locator('#messages .message-success, .message-success').first()).toBeVisible();
  expect(browserErrors).toEqual([]);
});
