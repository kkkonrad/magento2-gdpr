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
    await consent.acceptAll();
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
    await consent.customize();
    await expect(consent.dialog).toBeVisible();
    await expect(consent.dialog.getByRole('checkbox').first()).toBeDisabled();
    await page.keyboard.press('Escape');
    await expect(consent.dialog).toBeHidden();
    await consent.rejectOptional();
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
    await new CookieConsentPage(page).acceptAll();
    await expect.poll(() => page.evaluate(() => Boolean(window.__gdprMarketingExecuted))).toBe(true);
  });
});

declare global {
  interface Window {
    kkkonradConsent: {has(group: string): boolean};
    __gdprMarketingExecuted?: boolean;
  }
}
