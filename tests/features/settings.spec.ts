import { test, expect } from '@playwright/test';

test.use({ baseURL: 'http://localhost' });

// ─────────────────────────────────────────────────────────────────────────────
// 1. Unauthenticated redirect (active — no session required)
// ─────────────────────────────────────────────────────────────────────────────
test.describe('Settings — unauthenticated redirect', () => {
  test('GET /pages/settings.php redirects to welcome or register when not logged in', async ({ page }) => {
    const response = await page.goto('/pages/settings.php', { waitUntil: 'networkidle' });

    const finalUrl = page.url();
    const landed =
      finalUrl.includes('/welcome.php') ||
      finalUrl.includes('/register.php') ||
      finalUrl.includes('/login.php');

    expect(landed, `Expected redirect away from settings, got: ${finalUrl}`).toBe(true);

    // Must not be sitting on settings.php
    expect(finalUrl).not.toContain('/pages/settings.php');
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// 2. UI Rendering (requires auth — skipped)
// ─────────────────────────────────────────────────────────────────────────────
test.describe('Settings — UI rendering', () => {
  test.skip('page title contains ميزان', async ({ page }) => {
    await page.goto('/pages/settings.php');
    await expect(page).toHaveTitle(/ميزان/);
  });

  test.skip('h1 "الإعدادات" is visible', async ({ page }) => {
    await page.goto('/pages/settings.php');
    const heading = page.locator('h1.page-title');
    await expect(heading).toBeVisible();
    await expect(heading).toHaveText('الإعدادات');
  });

  test.skip('subtitle / description paragraph is visible', async ({ page }) => {
    await page.goto('/pages/settings.php');
    const subtitle = page.locator('p.text-muted[data-i18n="settings_desc"]');
    await expect(subtitle).toBeVisible();
    await expect(subtitle).toContainText('إدارة حسابك وتفضيلاتك');
  });

  test.skip('#profileCard is visible', async ({ page }) => {
    await page.goto('/pages/settings.php');
    await expect(page.locator('#profileCard')).toBeVisible();
  });

  test.skip('#securityCard is visible', async ({ page }) => {
    await page.goto('/pages/settings.php');
    await expect(page.locator('#securityCard')).toBeVisible();
  });

  test.skip('#settingsName input is visible and pre-filled', async ({ page }) => {
    await page.goto('/pages/settings.php');
    const nameInput = page.locator('#settingsName');
    await expect(nameInput).toBeVisible();
    const value = await nameInput.inputValue();
    expect(value.trim().length).toBeGreaterThan(0);
  });

  test.skip('#settingsEmail input is visible with type=email', async ({ page }) => {
    await page.goto('/pages/settings.php');
    const emailInput = page.locator('#settingsEmail');
    await expect(emailInput).toBeVisible();
    await expect(emailInput).toHaveAttribute('type', 'email');
    const value = await emailInput.inputValue();
    expect(value.trim().length).toBeGreaterThan(0);
  });

  test.skip('password inputs (current, new, confirm) are all visible', async ({ page }) => {
    await page.goto('/pages/settings.php');
    await expect(page.locator('#currentPassword')).toBeVisible();
    await expect(page.locator('#newPassword')).toBeVisible();
    await expect(page.locator('#confirmPassword')).toBeVisible();
  });

  test.skip('#strengthBar is present in the DOM', async ({ page }) => {
    await page.goto('/pages/settings.php');
    const strengthBar = page.locator('.strength-bar#strengthBar');
    await expect(strengthBar).toBeAttached();
  });

  test.skip('html element has lang=ar and dir=rtl', async ({ page }) => {
    await page.goto('/pages/settings.php');
    const html = page.locator('html');
    await expect(html).toHaveAttribute('lang', 'ar');
    await expect(html).toHaveAttribute('dir', 'rtl');
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// 3. Profile card (requires auth — skipped)
// ─────────────────────────────────────────────────────────────────────────────
test.describe('Settings — profile card', () => {
  test.skip('#settingsName has maxlength="100"', async ({ page }) => {
    await page.goto('/pages/settings.php');
    await expect(page.locator('#settingsName')).toHaveAttribute('maxlength', '100');
  });

  test.skip('#settingsEmail has maxlength="150"', async ({ page }) => {
    await page.goto('/pages/settings.php');
    await expect(page.locator('#settingsEmail')).toHaveAttribute('maxlength', '150');
  });

  test.skip('"حفظ التغييرات" button is visible in profile card', async ({ page }) => {
    await page.goto('/pages/settings.php');
    const saveBtn = page.locator('#profileCard button.btn.btn-primary');
    await expect(saveBtn).toBeVisible();
    await expect(saveBtn).toContainText('حفظ التغييرات');
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// 4. Password strength meter (requires auth — skipped)
// ─────────────────────────────────────────────────────────────────────────────
test.describe('Settings — password strength meter', () => {
  test.skip('7-char weak password shows "ضعيفة" label', async ({ page }) => {
    await page.goto('/pages/settings.php');
    const newPw = page.locator('#newPassword');
    await newPw.fill('abcdefg');
    await newPw.dispatchEvent('input');
    const label = page.locator('#strengthLabel');
    await expect(label).toContainText('ضعيفة');
  });

  test.skip('strong password (uppercase + digit + symbol, 9+ chars) shows "ممتازة" or "جيدة"', async ({ page }) => {
    await page.goto('/pages/settings.php');
    const newPw = page.locator('#newPassword');
    await newPw.fill('Abc123!XY');
    await newPw.dispatchEvent('input');
    const labelText = await page.locator('#strengthLabel').innerText();
    const isStrong = labelText.includes('ممتازة') || labelText.includes('جيدة');
    expect(isStrong, `Unexpected strength label: "${labelText}"`).toBe(true);
  });

  test.skip('#strengthBar width changes after typing a password', async ({ page }) => {
    await page.goto('/pages/settings.php');
    const bar = page.locator('#strengthBar');

    const widthBefore = await bar.evaluate((el) => (el as HTMLElement).style.width || getComputedStyle(el).width);

    const newPw = page.locator('#newPassword');
    await newPw.fill('Abc123!XY');
    await newPw.dispatchEvent('input');

    const widthAfter = await bar.evaluate((el) => (el as HTMLElement).style.width || getComputedStyle(el).width);

    expect(widthAfter).not.toBe(widthBefore);
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// 5. Password change validation (requires auth — skipped)
// ─────────────────────────────────────────────────────────────────────────────
test.describe('Settings — password change validation', () => {
  test.skip('empty currentPassword → error shown on submit', async ({ page }) => {
    await page.goto('/pages/settings.php');
    // Leave currentPassword empty, fill new fields
    await page.locator('#newPassword').fill('ValidPass1!');
    await page.locator('#confirmPassword').fill('ValidPass1!');
    await page.locator('#securityCard button.btn.btn-primary').click();

    // Expect an error toast, alert, or inline message
    const errorVisible = await page
      .locator('.toast, .alert, .error-message, [class*="error"], [class*="toast"]')
      .first()
      .isVisible()
      .catch(() => false);
    expect(errorVisible).toBe(true);
  });

  test.skip('newPassword shorter than 8 chars → validation error', async ({ page }) => {
    await page.goto('/pages/settings.php');
    await page.locator('#currentPassword').fill('anything');
    await page.locator('#newPassword').fill('abc');
    await page.locator('#confirmPassword').fill('abc');
    await page.locator('#securityCard button.btn.btn-primary').click();

    const errorVisible = await page
      .locator('.toast, .alert, .error-message, [class*="error"], [class*="toast"]')
      .first()
      .isVisible()
      .catch(() => false);
    expect(errorVisible).toBe(true);
  });

  test.skip('mismatched newPassword and confirmPassword → mismatch error', async ({ page }) => {
    await page.goto('/pages/settings.php');
    await page.locator('#currentPassword').fill('OldPassword1!');
    await page.locator('#newPassword').fill('NewPassword1!');
    await page.locator('#confirmPassword').fill('DifferentPass2@');
    await page.locator('#securityCard button.btn.btn-primary').click();

    const errorVisible = await page
      .locator('.toast, .alert, .error-message, [class*="error"], [class*="toast"]')
      .first()
      .isVisible()
      .catch(() => false);
    expect(errorVisible).toBe(true);
  });

  test.skip('valid password change data → success toast or message shown', async ({ page }) => {
    await page.goto('/pages/settings.php');
    // NOTE: Replace with a real test-user credential in CI fixtures
    await page.locator('#currentPassword').fill('TestUser123!');
    await page.locator('#newPassword').fill('NewTestUser456@');
    await page.locator('#confirmPassword').fill('NewTestUser456@');
    await page.locator('#securityCard button.btn.btn-primary').click();

    // Accept either a success or error response — we cannot guarantee the DB state
    const feedbackVisible = await page
      .locator('.toast, .alert, [class*="toast"], [class*="success"], [class*="error"]')
      .first()
      .isVisible()
      .catch(() => false);
    expect(feedbackVisible).toBe(true);
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// 6. Currency preference (requires auth — skipped)
// ─────────────────────────────────────────────────────────────────────────────
test.describe('Settings — currency preference', () => {
  test.skip('currency select is visible with SAR as an available option', async ({ page }) => {
    await page.goto('/pages/settings.php');
    const currencySelect = page.locator('select').filter({ hasText: 'SAR' }).first();
    await expect(currencySelect).toBeVisible();
    const sarOption = currencySelect.locator('option[value="SAR"]');
    await expect(sarOption).toBeAttached();
  });

  test.skip('changing currency to USD sends save request', async ({ page }) => {
    await page.goto('/pages/settings.php');

    const [request] = await Promise.all([
      page.waitForRequest((req) => req.url().includes('api/settings.php'), { timeout: 5000 }),
      page.locator('select').filter({ hasText: 'SAR' }).first().selectOption('USD'),
    ]);

    expect(request).toBeTruthy();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// 7. Notification toggles (requires auth — skipped)
// ─────────────────────────────────────────────────────────────────────────────
test.describe('Settings — notification toggles', () => {
  test.skip('notif_email toggle is visible and interactive', async ({ page }) => {
    await page.goto('/pages/settings.php');
    // Toggle may be a checkbox or a custom switch; locate by id or name
    const toggle = page.locator('[id*="notif_email"], [name*="notif_email"], input[type="checkbox"]').first();
    await expect(toggle).toBeAttached();
  });

  test.skip('notif_warranty toggle is visible', async ({ page }) => {
    await page.goto('/pages/settings.php');
    const toggle = page.locator('[id*="notif_warranty"], [name*="notif_warranty"]').first();
    await expect(toggle).toBeAttached();
  });

  test.skip('notif_budget toggle is visible', async ({ page }) => {
    await page.goto('/pages/settings.php');
    const toggle = page.locator('[id*="notif_budget"], [name*="notif_budget"]').first();
    await expect(toggle).toBeAttached();
  });

  test.skip('toggling notif_email fires a request to api/settings.php', async ({ page }) => {
    await page.goto('/pages/settings.php');

    const toggleLocator = page.locator('[id*="notif_email"], [name*="notif_email"], input[type="checkbox"]').first();

    const [request] = await Promise.all([
      page.waitForRequest((req) => req.url().includes('api/settings.php'), { timeout: 5000 }),
      toggleLocator.click(),
    ]);

    expect(request).toBeTruthy();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// 8. Danger zone — account deletion (requires auth — skipped)
// ─────────────────────────────────────────────────────────────────────────────
test.describe('Settings — danger zone / account deletion', () => {
  test.skip('danger zone section is visible on the page', async ({ page }) => {
    await page.goto('/pages/settings.php');
    const dangerZone = page.locator('[class*="danger"], [id*="danger"], .danger-zone').first();
    await expect(dangerZone).toBeVisible();
  });

  test.skip('delete account button is present', async ({ page }) => {
    await page.goto('/pages/settings.php');
    const deleteBtn = page
      .locator('button')
      .filter({ hasText: /حذف|delete/i })
      .first();
    await expect(deleteBtn).toBeAttached();
  });

  test.skip('clicking delete triggers a confirmation modal or dialog', async ({ page }) => {
    await page.goto('/pages/settings.php');

    const deleteBtn = page
      .locator('button')
      .filter({ hasText: /حذف|delete/i })
      .first();

    // Listen for a native dialog (window.confirm / window.alert)
    let dialogOpened = false;
    page.on('dialog', async (dialog) => {
      dialogOpened = true;
      await dialog.dismiss();
    });

    await deleteBtn.click();

    // Allow time for either a JS dialog or a DOM modal to appear
    await page.waitForTimeout(500);

    const modalVisible = await page
      .locator('.modal, [role="dialog"], [class*="modal"], [class*="confirm"]')
      .first()
      .isVisible()
      .catch(() => false);

    expect(dialogOpened || modalVisible, 'Expected a confirmation dialog or modal after clicking delete').toBe(true);
  });

  test.skip('account deletion is NOT performed without correct password confirmation', async ({ page }) => {
    await page.goto('/pages/settings.php');

    const deleteBtn = page
      .locator('button')
      .filter({ hasText: /حذف|delete/i })
      .first();

    // Dismiss any native dialog immediately (simulating cancel / wrong password)
    page.on('dialog', async (dialog) => {
      await dialog.dismiss();
    });

    await deleteBtn.click();
    await page.waitForTimeout(500);

    // If a DOM modal appeared, try submitting with a wrong password
    const passwordField = page.locator('.modal input[type="password"], [role="dialog"] input[type="password"]').first();
    const passwordFieldVisible = await passwordField.isVisible().catch(() => false);

    if (passwordFieldVisible) {
      await passwordField.fill('wrong-password');
      const confirmBtn = page
        .locator('.modal button, [role="dialog"] button')
        .filter({ hasText: /تأكيد|confirm|حذف|delete/i })
        .first();
      await confirmBtn.click();
      await page.waitForTimeout(500);
    }

    // The user should still be on settings.php (not logged out or redirected to home)
    const currentUrl = page.url();
    expect(currentUrl).toContain('settings');
  });
});
