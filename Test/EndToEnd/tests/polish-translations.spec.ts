import { expect, test } from '@playwright/test';
import { AdminPage } from '../pages/admin.page';
import { loadTestAccess } from '../support/access';

const access = loadTestAccess();

test('key GDPR administration screens are translated into Polish', async ({ page }) => {
  const admin = new AdminPage(page, access);
  await admin.login();

  const screens: Array<[string, RegExp[]]> = [
    ['admin/system_config/edit/section/kkkonrad_gdpr', [/Ustawienia GDPR/i, /Prawa dotyczące danych osobowych/i]],
    ['kkkonrad_gdpr/request/index', [/Żądania klientów GDPR/i, /Eksportuj żądania GDPR do CSV/i]],
    ['kkkonrad_gdpr/consent/index', [/Definicje zgód GDPR/i, /Utwórz szkic zgody/i, /rejestracja/i]],
    ['kkkonrad_gdpr/cookie/index', [/Katalog cookies GDPR/i, /Utwórz grupę cookies/i, /niezbędne/i]],
    ['kkkonrad_gdpr/health/index', [/Stan automatyzacji GDPR/i]]
  ];

  for (const [path, expectedTexts] of screens) {
    await admin.goto(path);
    for (const expectedText of expectedTexts) {
      await expect(page.locator('body')).toContainText(expectedText);
    }
  }
});
