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

  get acceptAllButton(): Locator {
    return this.banner.getByRole('button', { name: /Accept all|Akceptuj wszystkie/i });
  }

  get rejectOptionalButton(): Locator {
    return this.banner.getByRole('button', { name: /Reject optional|Odrzuć opcjonalne/i });
  }

  get customizeButton(): Locator {
    return this.banner.getByRole('button', { name: /Customize|Dostosuj/i });
  }

  get settingsButton(): Locator {
    return this.page.getByRole('button', { name: /Cookie settings|Ustawienia cookies/i });
  }

  async acceptAll(): Promise<void> {
    await this.acceptAllButton.click();
  }

  async rejectOptional(): Promise<void> {
    await this.rejectOptionalButton.click();
  }

  async customize(): Promise<void> {
    await this.customizeButton.click();
  }
}
