import { expect, test } from '@playwright/test';
import { CookieConsentPage } from '../pages/cookie-consent.page';

test.describe('Kkkonrad GDPR cookie consent', () => {
  test.beforeEach(async ({ page }) => {
    const consent = new CookieConsentPage(page);
    await consent.goto();
    test.skip(await page.locator('[data-kkkonrad-gdpr-cmp]').count() === 0,
      'Enable general, cookie and banner feature flags before running CMP E2E tests.');
  });

  test('accepts every group and persists a signed decision', async ({ page, context }) => {
    const consent = new CookieConsentPage(page);
    await expect(consent.banner).toBeVisible();
    const responsePromise = page.waitForResponse(response => response.url().includes('/gdpr/consent/save'));
    await consent.acceptAll();
    const response = await responsePromise;
    expect(response.status(), `${response.status()} ${response.headers().location || ''}`).toBe(200);
    await expect(consent.banner).toBeHidden();
    await expect.poll(async () => {
      const cookies = await context.cookies();
      return cookies.some(cookie => cookie.name === 'kkkonrad_gdpr_consent');
    }).toBe(true);
    await expect.poll(() => page.evaluate(() => window.kkkonradConsent.has('marketing'))).toBe(true);
  });

  test('exposes the active Luma or Hyva adapter for compatibility runs', async ({ page }) => {
    const root = page.locator('[data-kkkonrad-gdpr-cmp]');
    const adapter = await root.getAttribute('data-frontend-adapter');
    expect(['luma', 'hyva']).toContain(adapter);
    if (process.env.GDPR_FRONTEND_ADAPTER) {
      expect(adapter).toBe(process.env.GDPR_FRONTEND_ADAPTER);
    }
  });

  test('offers equivalent reject and customize actions with an accessible dialog', async ({ page }) => {
    const consent = new CookieConsentPage(page);
    await page.evaluate(() => { document.cookie = 'e2e_unknown_cookie=diagnostic; path=/'; });
    await consent.customize();
    await expect(consent.dialog).toBeVisible();
    await expect(consent.dialog.getByRole('checkbox').first()).toBeDisabled();
    await page.keyboard.press('Escape');
    await expect(consent.dialog).toBeHidden();
    const reportPromise = page.waitForResponse(response => response.url().includes('/gdpr/rejected/report'));
    await consent.rejectOptional();
    const report = await reportPromise;
    expect(report.status()).toBe(200);
    await expect.poll(() => page.evaluate(() => window.kkkonradConsent.has('marketing'))).toBe(false);
    await expect.poll(() => page.evaluate(() => window.kkkonradConsent.has('essential'))).toBe(true);
  });

  test('executes a gated script only after consent', async ({ page }) => {
    await page.evaluate(() => {
      const script = document.createElement('script');
      script.type = 'text/plain';
      script.dataset.kkkonradConsent = 'marketing';
      script.text = 'window.__gdprMarketingExecuted = true;';
      document.body.appendChild(script);
    });
    await expect.poll(() => page.evaluate(() => Boolean(window.__gdprMarketingExecuted))).toBe(false);
    const responsePromise = page.waitForResponse(response => response.url().includes('/gdpr/consent/save'));
    await new CookieConsentPage(page).acceptAll();
    const response = await responsePromise;
    expect(response.status(), `${response.status()} ${response.headers().location || ''}`).toBe(200);
    await expect.poll(() => page.evaluate(() => Boolean(window.__gdprMarketingExecuted))).toBe(true);
  });

  test('applies Google Consent Mode defaults and updates them after a decision', async ({ page }) => {
    const before = await page.evaluate(() => (window.dataLayer || []).map(entry => Array.from(entry)));
    expect(before.some(entry => entry[0] === 'consent' && entry[1] === 'default')).toBe(true);
    const responsePromise = page.waitForResponse(response => response.url().includes('/gdpr/consent/save'));
    await new CookieConsentPage(page).acceptAll();
    expect((await responsePromise).status()).toBe(200);
    await expect.poll(async () => page.evaluate(() => (window.dataLayer || [])
      .map(entry => Array.from(entry))
      .some(entry => entry[0] === 'consent' && entry[1] === 'update'))).toBe(true);
  });
});

declare global {
  interface Window {
    kkkonradConsent: {has(group: string): boolean};
    __gdprMarketingExecuted?: boolean;
    dataLayer?: IArguments[];
  }
}
