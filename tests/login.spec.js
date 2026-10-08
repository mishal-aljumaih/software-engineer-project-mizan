const { test, expect } = require('@playwright/test');

test('login page opens', async ({ page }) => {

  await page.goto('/login.php');

  await expect(page).toHaveTitle(/Mizan/i);

});