import { expect, test } from '@playwright/test';
import { AdminPage } from '../pages/admin.page';
import { CustomerPage } from '../pages/customer.page';
import { loadTestAccess } from '../support/access';

const access = loadTestAccess();

test.describe.configure({ mode: 'serial' });

test('customer submits export, anonymization and erasure requests', async ({ page }) => {
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('response', response => {
    if (response.status() >= 500) errors.push(`${response.status()} ${response.url()}`);
  });

  const customer = new CustomerPage(page, access);
  await customer.login();
  await customer.goto('/gdpr/privacy/index');

  for (const type of ['export', 'anonymize', 'erase']) {
    const existing = page.locator('.kkkonrad-gdpr-dashboard table').nth(1).locator('tbody tr')
      .filter({ hasText: type });
    if (await existing.count()) continue;
    const form = page.locator(`form:has(input[name="type"][value="${type}"])`);
    await expect(form).toBeVisible();
    await form.locator('input[name="current_password"]').fill(access.customerPassword);
    if (type !== 'export') {
      await form.locator('input[name="confirm_irreversible"]').check();
    }
    await Promise.all([
      page.waitForURL(/\/gdpr\/privacy\/index/),
      form.locator('button[type="submit"]').click()
    ]);
    await expect(page.locator('[data-ui-id="message-success"]')).toContainText(/submitted|wysłan|złożon/i);
  }

  const requestTable = page.locator('.kkkonrad-gdpr-dashboard table').nth(1);
  await expect(requestTable).toContainText('export');
  await expect(requestTable).toContainText('anonymize');
  await expect(requestTable).toContainText('erase');
  expect(errors).toEqual([]);
});

test('administrator reviews destructive requests without authorizing them', async ({ page }) => {
  const admin = new AdminPage(page, access);
  await admin.login();
  await admin.goto('kkkonrad_gdpr/request/index');
  await expect(page.locator('main table[data-role="grid"]')).toBeVisible();

  for (const type of ['anonymize', 'erase']) {
    const row = page.locator('main table[data-role="grid"] tbody tr').filter({ hasText: type }).first();
    await expect(row).toContainText(/pending_approval|blocked/);
    await row.getByRole('link', { name: /View|Zobacz|Widok/i }).click();
    await expect(page.locator('main')).toContainText(type);

    const rejectForm = page.locator('form').filter({ has: page.locator('button', { hasText: /Reject|Odrzuć/i }) });
    if (await rejectForm.count() === 0) {
      await expect(page.locator('main')).toContainText('blocked');
      await admin.goto('kkkonrad_gdpr/request/index');
      continue;
    }
    await rejectForm.locator('input[name="public_reason"]').fill('Request rejected during non-destructive end-to-end verification.');
    await rejectForm.locator('input[name="admin_reason"]').fill('Automated browser verification; no destructive customer operation was authorized.');
    await rejectForm.locator('button[type="submit"]').click();
    const confirm = page.getByRole('button', { name: /OK|Potwierdź/i });
    if (await confirm.isVisible()) await confirm.click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('[data-ui-id="message-success"]')).toContainText(/rejected|odrzucon/i);

    await admin.goto('kkkonrad_gdpr/request/index');
    await expect(page.locator('main table[data-role="grid"]')).toBeVisible();
  }
});

test('administrator exports the request register and queues an export on behalf of a customer', async ({ page }) => {
  const admin = new AdminPage(page, access);
  await admin.login();
  await admin.goto('kkkonrad_gdpr/request/index');
  const grid = page.locator('main table[data-role="grid"]');
  await expect(grid).toBeVisible();
  const exportRow = grid.locator('tbody tr').filter({ hasText: 'export' }).first();
  const customerId = Number((await exportRow.locator('td').nth(2).innerText()).trim());
  expect(customerId).toBeGreaterThan(0);

  const csvPromise = page.waitForEvent('download');
  await page.getByRole('link', { name: /Export GDPR requests CSV|Eksport.*CSV/i }).click();
  const csv = await csvPromise;
  expect(await csv.failure()).toBeNull();
  expect(csv.suggestedFilename()).toMatch(/\.csv$/i);

  await page.locator('#gdpr-export-customer-id').fill(String(customerId));
  await page.locator('#gdpr-export-admin-reason').fill('End-to-end verification of administrator export on behalf workflow.');
  await page.getByRole('button', { name: /Queue data export|Dodaj eksport danych do kolejki/i }).click();
  await page.waitForLoadState('domcontentloaded');
  await expect(page.locator('main')).toContainText(/customer data export was queued|eksport.*kolejki/i);
});

test('completed customer export is available for download', async ({ page }) => {
  const customer = new CustomerPage(page, access);
  await customer.login();
  await customer.goto('/gdpr/privacy/index');
  const exportRow = page.locator('.kkkonrad-gdpr-dashboard table').nth(1).locator('tbody tr')
    .filter({ hasText: 'export' }).filter({ hasText: 'completed' }).first();
  await expect(exportRow).toContainText('completed');
  const downloadPromise = page.waitForEvent('download');
  await exportRow.getByRole('link', { name: /Download|Pobierz/i }).click();
  const download = await downloadPromise;
  expect(await download.failure()).toBeNull();
  expect(download.suggestedFilename()).toMatch(/\.zip$/i);
});
