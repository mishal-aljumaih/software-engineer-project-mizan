import { test, expect } from '@playwright/test';

const FAKE_TOKEN = 'aabbccddeeff00112233445566778899aabbccddeeff00112233445566778899';

test.describe('reset-password.php — no token (invalid state)', () => {
  test('page title contains ميزان', async ({ page }) => {
    await page.goto('/reset-password.php');
    await expect(page).toHaveTitle(/ميزان/);
  });

  test('html has dir=rtl and lang=ar', async ({ page }) => {
    await page.goto('/reset-password.php');
    const html = page.locator('html');
    await expect(html).toHaveAttribute('dir', 'rtl');
    await expect(html).toHaveAttribute('lang', 'ar');
  });

  test('.error-box is visible', async ({ page }) => {
    await page.goto('/reset-password.php');
    await expect(page.locator('.error-box')).toBeVisible();
  });

  test('⏰ icon is present in error-box', async ({ page }) => {
    await page.goto('/reset-password.php');
    const errorBox = page.locator('.error-box');
    await expect(errorBox).toContainText('⏰');
  });

  test('error message about invalid/expired link is visible', async ({ page }) => {
    await page.goto('/reset-password.php');
    const errorBox = page.locator('.error-box');
    // Empty token → 'رابط الاستعادة غير صحيح أو منتهي الصلاحية.'
    await expect(errorBox).toContainText('رابط الاستعادة');
  });

  test('"طلب رابط جديد" link is visible and points to forgot-password.php', async ({ page }) => {
    await page.goto('/reset-password.php');
    const newLinkBtn = page.locator('.error-box a[href*="forgot-password.php"]');
    await expect(newLinkBtn).toBeVisible();
    await expect(newLinkBtn).toContainText('طلب رابط جديد');
  });

  test('back link to login.php is visible', async ({ page }) => {
    await page.goto('/reset-password.php');
    const backLink = page.locator('a.back-link[href*="login.php"]');
    await expect(backLink).toBeVisible();
  });

  test('🔐 auth icon wrap is visible', async ({ page }) => {
    await page.goto('/reset-password.php');
    const iconWrap = page.locator('.auth-icon-wrap');
    await expect(iconWrap).toBeVisible();
    await expect(iconWrap).toContainText('🔐');
  });

  test('themeToggle button is present', async ({ page }) => {
    await page.goto('/reset-password.php');
    await expect(page.locator('#themeToggle')).toBeVisible();
  });

  test('langToggle button is present', async ({ page }) => {
    await page.goto('/reset-password.php');
    await expect(page.locator('#langToggle')).toBeVisible();
  });
});

test.describe('reset-password.php — fake/invalid token', () => {
  test('fake token still shows error-box (not the form)', async ({ page }) => {
    await page.goto(`/reset-password.php?token=${FAKE_TOKEN}`);
    await expect(page.locator('.error-box')).toBeVisible();
    await expect(page.locator('form')).not.toBeVisible();
  });

  test('error message mentions invalid or expired link for fake token', async ({ page }) => {
    await page.goto(`/reset-password.php?token=${FAKE_TOKEN}`);
    const errorBox = page.locator('.error-box');
    // 'رابط الاستعادة منتهي الصلاحية أو غير صحيح. يرجى طلب رابط جديد.'
    await expect(errorBox).toContainText('رابط الاستعادة');
  });

  test('⏰ icon present with fake token', async ({ page }) => {
    await page.goto(`/reset-password.php?token=${FAKE_TOKEN}`);
    await expect(page.locator('.error-box')).toContainText('⏰');
  });

  test('"طلب رابط جديد" link present with fake token', async ({ page }) => {
    await page.goto(`/reset-password.php?token=${FAKE_TOKEN}`);
    const btn = page.locator('.error-box a[href*="forgot-password.php"]');
    await expect(btn).toBeVisible();
    await expect(btn).toContainText('طلب رابط جديد');
  });
});

test.describe('reset-password.php — navigation from error state', () => {
  test('"طلب رابط جديد" navigates to forgot-password.php', async ({ page }) => {
    await page.goto('/reset-password.php');
    const btn = page.locator('.error-box a[href*="forgot-password.php"]');
    await btn.click();
    await expect(page).toHaveURL(/forgot-password\.php/);
  });

  test('back link navigates to login.php', async ({ page }) => {
    await page.goto('/reset-password.php');
    const backLink = page.locator('a.back-link[href*="login.php"]');
    await backLink.click();
    await expect(page).toHaveURL(/login\.php/);
  });
});

test.describe('reset-password.php — password form (requires seeded DB token)', () => {
  // All tests in this block require a valid row in the `password_resets` table.
  // Seed: INSERT INTO password_resets (token, email, created_at) VALUES ('<token>', 'test@example.com', NOW())
  // Replace SEEDED_TOKEN below with the actual seeded token before running.
  const SEEDED_TOKEN = 'REPLACE_WITH_SEEDED_TOKEN';
  const FORM_URL = `/reset-password.php?token=${SEEDED_TOKEN}`;

  test.skip(true, 'Requires a valid seeded password_resets row in DB — set SEEDED_TOKEN before running');

  test('form is displayed with valid token', async ({ page }) => {
    await page.goto(FORM_URL);
    await expect(page.locator('h1[data-i18n="new_password_title"]')).toBeVisible();
    await expect(page.locator('h1[data-i18n="new_password_title"]')).toContainText('كلمة مرور جديدة');
    await expect(page.locator('p[data-i18n="new_password_subtitle"]')).toBeVisible();
    await expect(page.locator('form')).toBeVisible();
    await expect(page.locator('#pw')).toBeVisible();
    await expect(page.locator('input[name="confirm_password"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('password strength meter — score 0 (empty) → no fill', async ({ page }) => {
    await page.goto(FORM_URL);
    const fill = page.locator('#strengthFill');
    // Before any input strength fill should be empty / 0 width
    const width = await fill.evaluate((el) => (el as HTMLElement).style.width);
    expect(width === '' || width === '0%').toBeTruthy();
    const label = await page.locator('#strengthLabel').textContent();
    expect(label?.trim()).toBe('');
  });

  test('password strength meter — score 1 (4 chars) → ضعيفة / red', async ({ page }) => {
    await page.goto(FORM_URL);
    await page.fill('#pw', 'abcd');
    await expect(page.locator('#strengthLabel')).toContainText('ضعيفة');
    const fill = page.locator('#strengthFill');
    const width = await fill.evaluate((el) => (el as HTMLElement).style.width);
    expect(width).toBe('25%');
  });

  test('password strength meter — score 2 (8 lowercase chars) → متوسطة / orange', async ({ page }) => {
    await page.goto(FORM_URL);
    await page.fill('#pw', 'abcdefgh');
    await expect(page.locator('#strengthLabel')).toContainText('متوسطة');
    const fill = page.locator('#strengthFill');
    const width = await fill.evaluate((el) => (el as HTMLElement).style.width);
    expect(width).toBe('50%');
  });

  test('password strength meter — score 3 (8 chars + uppercase) → جيدة / yellow', async ({ page }) => {
    await page.goto(FORM_URL);
    await page.fill('#pw', 'Abcdefgh');
    await expect(page.locator('#strengthLabel')).toContainText('جيدة');
    const fill = page.locator('#strengthFill');
    const width = await fill.evaluate((el) => (el as HTMLElement).style.width);
    expect(width).toBe('75%');
  });

  test('password strength meter — score 4 (8 chars + uppercase + digit) → ممتازة / green', async ({ page }) => {
    await page.goto(FORM_URL);
    await page.fill('#pw', 'Abcdefg1');
    await expect(page.locator('#strengthLabel')).toContainText('ممتازة');
    const fill = page.locator('#strengthFill');
    const width = await fill.evaluate((el) => (el as HTMLElement).style.width);
    expect(width).toBe('100%');
  });

  test('password strength meter — score 5 (12+ chars + upper + digit + symbol) → ممتازة / green', async ({ page }) => {
    await page.goto(FORM_URL);
    await page.fill('#pw', 'Abcdefgh12!@');
    await expect(page.locator('#strengthLabel')).toContainText('ممتازة');
    const fill = page.locator('#strengthFill');
    const width = await fill.evaluate((el) => (el as HTMLElement).style.width);
    expect(width).toBe('100%');
  });

  test('submit with password < 8 chars shows pass_min_8 error', async ({ page }) => {
    await page.goto(FORM_URL);
    const pwInput = page.locator('#pw');
    const confirmInput = page.locator('input[name="confirm_password"]');
    // Remove minlength to bypass HTML5 validation
    await pwInput.evaluate((el) => el.removeAttribute('minlength'));
    await confirmInput.evaluate((el) => el.removeAttribute('minlength'));
    await pwInput.fill('abc');
    await confirmInput.fill('abc');
    await page.locator('button[type="submit"]').click();
    const alertError = page.locator('.alert.alert-error');
    await expect(alertError).toBeVisible();
    await expect(alertError).toContainText('كلمة المرور يجب أن تكون 8 أحرف على الأقل');
  });

  test('submit with mismatched passwords shows pass_mismatch error', async ({ page }) => {
    await page.goto(FORM_URL);
    await page.fill('#pw', 'Password1!');
    await page.fill('input[name="confirm_password"]', 'Password2!');
    await page.locator('button[type="submit"]').click();
    const alertError = page.locator('.alert.alert-error');
    await expect(alertError).toBeVisible();
    await expect(alertError).toContainText('كلمتا المرور غير متطابقتين');
  });

  test('submit with valid matching passwords shows success-box', async ({ page }) => {
    await page.goto(FORM_URL);
    await page.fill('#pw', 'ValidPass1!');
    await page.fill('input[name="confirm_password"]', 'ValidPass1!');
    await page.locator('button[type="submit"]').click();
    const successBox = page.locator('.success-box');
    await expect(successBox).toBeVisible();
    await expect(successBox).toContainText('تم تغيير كلمة المرور!');
    await expect(successBox).toContainText('✅');
    const loginBtn = page.locator('a.btn.btn-primary[href*="login.php"]');
    await expect(loginBtn).toBeVisible();
    await expect(loginBtn).toContainText('تسجيل الدخول');
  });

  test('success-box has link back to login.php', async ({ page }) => {
    await page.goto(FORM_URL);
    await page.fill('#pw', 'ValidPass1!');
    await page.fill('input[name="confirm_password"]', 'ValidPass1!');
    await page.locator('button[type="submit"]').click();
    const loginLink = page.locator('a.btn.btn-primary.btn-full[href*="login.php"]');
    await expect(loginLink).toBeVisible();
  });
});

test.describe('reset-password.php — authenticated user redirect', () => {
  test.skip(true, 'Requires an active authenticated session — needs session cookie setup or test fixture');

  test('authenticated user is redirected away from reset-password.php', async ({ page }) => {
    // Setup: inject a valid session cookie before navigating
    await page.goto('/reset-password.php');
    // Expect redirect to dashboard or index, not reset-password
    await expect(page).not.toHaveURL(/reset-password\.php/);
  });
});
