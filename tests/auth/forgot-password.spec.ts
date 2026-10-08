import { test, expect, Page } from '@playwright/test';

// ─── helpers ──────────────────────────────────────────────────────────────────

/** Remove HTML5 validation attributes so server-side errors can be exercised. */
async function bypassHtml5Validation(page: Page) {
  await page.evaluate(() => {
    document.querySelectorAll<HTMLInputElement>('input[required]').forEach(el => el.removeAttribute('required'));
    const emailInput = document.querySelector<HTMLInputElement>('#fpEmail');
    if (emailInput) emailInput.type = 'text';
  });
}

/** Fill the email field and submit the forgot-password form. */
async function submitForgotPassword(
  page: Page,
  email: string,
  options: { bypass?: boolean } = {},
) {
  if (options.bypass) {
    await bypassHtml5Validation(page);
  }
  await page.locator('#fpEmail').fill(email);
  await page.locator('button[type="submit"]').click();
}

// ─── suite ────────────────────────────────────────────────────────────────────

test.describe('Forgot Password page', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto('/forgot-password.php');
  });

  // ── UI rendering ──────────────────────────────────────────────────────────

  test('page title contains ميزان', async ({ page }) => {
    await expect(page).toHaveTitle(/ميزان/);
  });

  test('html element has dir=rtl and lang=ar', async ({ page }) => {
    const html = page.locator('html');
    await expect(html).toHaveAttribute('dir', 'rtl');
    await expect(html).toHaveAttribute('lang', 'ar');
  });

  test('auth card icon 🔑 is visible', async ({ page }) => {
    await expect(page.locator('.auth-icon-wrap')).toBeVisible();
    const iconText = await page.locator('.auth-icon-wrap').textContent();
    expect(iconText).toContain('🔑');
  });

  test('h1 is visible with correct Arabic text', async ({ page }) => {
    const heading = page.getByRole('heading', { level: 1 });
    await expect(heading).toBeVisible();
    await expect(heading).toContainText('نسيت كلمة المرور');
  });

  test('subtitle paragraph is visible', async ({ page }) => {
    const subtitle = page.locator('[data-i18n="forgot_password_subtitle"]');
    await expect(subtitle).toBeVisible();
    await expect(subtitle).toContainText('أدخل بريدك وسنرسل لك رابط الاستعادة');
  });

  test('email input has correct attributes', async ({ page }) => {
    const emailInput = page.locator('#fpEmail');
    await expect(emailInput).toBeVisible();
    await expect(emailInput).toHaveAttribute('type', 'email');
    await expect(emailInput).toHaveAttribute('placeholder', 'example@email.com');
    await expect(emailInput).toHaveAttribute('required', '');
    await expect(emailInput).toHaveAttribute('autocomplete', 'email');
    await expect(emailInput).toHaveAttribute('name', 'email');
  });

  test('email input has autofocus', async ({ page }) => {
    const emailInput = page.locator('#fpEmail');
    await expect(emailInput).toHaveAttribute('autofocus', '');
  });

  test('submit button is visible with correct text', async ({ page }) => {
    const btn = page.locator('button[type="submit"]');
    await expect(btn).toBeVisible();
    await expect(btn).toContainText('إرسال رابط الاستعادة');
  });

  test('back link to login.php is visible', async ({ page }) => {
    const backLink = page.locator('a.back-link[href="login.php"]');
    await expect(backLink).toBeVisible();
    await expect(backLink).toContainText('العودة لتسجيل الدخول');
  });

  test('email hint paragraph is visible', async ({ page }) => {
    const hint = page.locator('.email-hint');
    await expect(hint).toBeVisible();
    await expect(hint).toContainText('تأكد من إدخال البريد');
  });

  test('CSRF hidden input is present with 64-char hex value', async ({ page }) => {
    const csrf = page.locator('input[name="csrf_token"]');
    await expect(csrf).toBeAttached();
    const val = await csrf.inputValue();
    expect(val).toMatch(/^[0-9a-f]{64}$/);
  });

  test('themeToggle and langToggle buttons are present', async ({ page }) => {
    await expect(page.locator('#themeToggle')).toBeVisible();
    await expect(page.locator('#langToggle')).toBeVisible();
  });

  // ── Validation errors ─────────────────────────────────────────────────────

  test('browser blocks empty submit via HTML5 required attribute', async ({ page }) => {
    // Do NOT bypass validation — the browser should prevent submission
    await page.locator('button[type="submit"]').click();
    // Page must remain on forgot-password.php (no redirect to success state)
    await expect(page).toHaveURL(/forgot-password\.php/);
    // Success box must NOT appear
    await expect(page.locator('.success-box')).not.toBeVisible();
    // Form must still be present
    await expect(page.locator('form')).toBeVisible();
  });

  test('server returns Arabic error for blank email (bypass HTML5)', async ({ page }) => {
    await bypassHtml5Validation(page);
    await page.locator('#fpEmail').fill('');
    await page.locator('button[type="submit"]').click();

    const alert = page.locator('.alert.alert-error');
    await expect(alert).toBeVisible();
    await expect(alert).toContainText('الرجاء إدخال بريد إلكتروني صحيح');
  });

  test('server returns err_invalid_email_domain for malformed email (bypass HTML5)', async ({ page }) => {
    await bypassHtml5Validation(page);
    await page.locator('#fpEmail').fill('notanemail');
    await page.locator('button[type="submit"]').click();

    const alert = page.locator('.alert.alert-error');
    await expect(alert).toBeVisible();
    await expect(alert).toContainText('الرجاء إدخال بريد إلكتروني صحيح ونطاقه موجود');
  });

  test('server returns err_invalid_email_domain for email with invalid domain (bypass HTML5)', async ({ page }) => {
    await bypassHtml5Validation(page);
    // Valid email format but domain does not exist
    await page.locator('#fpEmail').fill('user@totallyfakedomainthatdoesnotexist12345.xyz');
    await page.locator('button[type="submit"]').click();

    const alert = page.locator('.alert.alert-error');
    await expect(alert).toBeVisible();
    await expect(alert).toContainText('الرجاء إدخال بريد إلكتروني صحيح ونطاقه موجود');
  });

  // ── Privacy-safe success response ─────────────────────────────────────────

  test('non-existent but well-formed email shows success state (privacy behavior)', async ({ page }) => {
    // A real deliverable domain with a made-up local part
    await submitForgotPassword(page, 'noexist_mizan_test_user_xyz@gmail.com');

    // Success heading
    await expect(page.getByRole('heading', { level: 1 })).toContainText('تحقق من بريدك');

    // success-box is visible
    const successBox = page.locator('.success-box');
    await expect(successBox).toBeVisible();

    // icon shows 📬
    const icon = page.locator('.success-box .s-icon');
    await expect(icon).toBeVisible();
    const iconText = await icon.textContent();
    expect(iconText).toContain('📬');

    // Privacy message for unknown user
    await expect(successBox).toContainText('إذا كان البريد مسجلاً لدينا');

    // Back link still visible
    await expect(page.locator('a.back-link[href="login.php"]')).toBeVisible();

    // Form must be gone
    await expect(page.locator('form')).not.toBeVisible();
  });

  // ── Navigation ────────────────────────────────────────────────────────────

  test('back link navigates to login.php', async ({ page }) => {
    await page.locator('a.back-link[href="login.php"]').click();
    await expect(page).toHaveURL(/login\.php/);
  });

  // ── Happy-path (real email delivery) — skipped in automated runs ──────────

  test.skip('registered email shows personalized success message', async ({ page }) => {
    // Requires a real registered account and mailbox inspection.
    // Run manually: submit a known registered email and verify:
    //   success-box contains 'تم إرسال رابط الاستعادة إلى بريدك الإلكتروني'
  });

});
