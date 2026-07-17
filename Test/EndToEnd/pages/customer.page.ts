import { expect, type Page } from '@playwright/test';
import type { TestAccess } from '../support/access';

export class CustomerPage {
  readonly page: Page;
  readonly access: TestAccess;

  constructor(page: Page, access: TestAccess) {
    this.page = page;
    this.access = access;
  }

  async login(): Promise<void> {
    const url = new URL('/customer/account/login', this.access.customerUrl).toString();
    await this.page.goto(url);
    await this.page.waitForLoadState('domcontentloaded');
    const loginForm = this.page.locator('form').filter({
      has: this.page.locator('input[name="login[username]"]')
    });
    const email = loginForm.locator('input[name="login[username]"]');
    if (await email.count()) {
      await email.fill(this.access.customerEmail);
      await loginForm.locator('input[name="login[password]"]').fill(this.access.customerPassword);
      await loginForm.getByRole('button', { name: /Sign In|Zaloguj się/i }).click();
      await this.page.waitForLoadState('domcontentloaded');
    }
    await expect(loginForm).toHaveCount(0);
  }

  async goto(path: string): Promise<void> {
    await this.page.goto(new URL(path, this.access.customerUrl).toString());
    await this.page.waitForLoadState('domcontentloaded');
    await expect(this.page.locator('body')).not.toContainText(/Exception #\d+|There has been an error processing your request/i);
  }
}
