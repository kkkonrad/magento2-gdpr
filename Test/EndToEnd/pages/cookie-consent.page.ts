import type { Locator, Page } from '@playwright/test';

export class CookieConsentPage {
  readonly page: Page;

  constructor(page: Page) {
    this.page = page;
  }

  async goto(): Promise<void> {
    await this.page.context().clearCookies();
    await this.page.goto('/');
    await this.page.waitForLoadState('domcontentloaded');
  }

  get banner(): Locator {
    return this.page.locator('[data-role="banner"]');
  }

  get dialog(): Locator {
    return this.page.getByRole('dialog');
  }

  async acceptAll(): Promise<void> {
    await this.banner.getByRole('button', { name: /Accept all|Akceptuj wszystkie/i }).click();
  }

  async rejectOptional(): Promise<void> {
    await this.banner.getByRole('button', { name: /Reject optional|Odrzuć opcjonalne/i }).click();
  }

  async customize(): Promise<void> {
    await this.banner.getByRole('button', { name: /Customize|Dostosuj/i }).click();
  }
}
