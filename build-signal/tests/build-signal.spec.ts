import { expect, test } from '@playwright/test';

test('loads the walkthrough and opens the narrated tour', async ({ page }) => {
  await page.goto('/');

  await expect(page).toHaveTitle(/Build Signal/);
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Signal.');
  await expect(page.locator('.hero-image')).toBeVisible();

  await page.getByRole('button', { name: /Hear the walkthrough/ }).click();
  await expect(page.locator('#tour')).toHaveClass(/open/);
  await expect(page.locator('#tour-count')).toContainText('1 / 7');
});
