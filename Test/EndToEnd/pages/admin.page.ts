import { expect, type Page } from '@playwright/test';
import type { TestAccess } from '../support/access';

export class AdminPage {
  readonly page: Page;
  readonly access: TestAccess;

  constructor(page: Page, access: TestAccess) {
    this.page = page;
    this.access = access;
  }

  async login(): Promise<void> {
    await this.page.goto(this.access.adminUrl);
    await this.page.waitForLoadState('domcontentloaded');
    const username = this.page.locator('#username');
    if (await username.count()) {
      await username.fill(this.access.adminEmail);
      await this.page.locator('#login').fill(this.access.adminPassword);
      await this.page.getByRole('button', { name: /Sign in|Zaloguj/i }).click();
      await this.page.waitForLoadState('domcontentloaded');
    }
    await expect(this.page.locator('body')).not.toContainText(/Invalid security or form key|Nieprawidłowy klucz/i);
    await expect(this.page.locator('#username')).toHaveCount(0);
  }

  route(path: string): string {
    const loginUrl = new URL(this.access.adminUrl);
    const adminFrontName = loginUrl.pathname.split('/').filter(Boolean)[0];
    return `${loginUrl.origin}/${adminFrontName}/${path.replace(/^\//, '')}`;
  }

  async goto(path: string): Promise<void> {
    const normalizedPath = path.replace(/^\//, '').replace(/\/$/, '');
    const menuLink = this.page.locator(`a[href*="/${normalizedPath}/"]`).first();
    const href = await menuLink.getAttribute('href');
    if (!href) {
      throw new Error(`No Magento-generated admin URL was found for ${normalizedPath}.`);
    }
    await this.page.goto(href);
    await this.page.waitForLoadState('domcontentloaded');
    await expect(this.page.locator('body')).not.toContainText(/Exception #\d+|There has been an error processing your request/i);
  }
}
