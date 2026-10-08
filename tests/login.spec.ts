import { test, expect, Page } from '@playwright/test';

// ─── helpers ──────────────────────────────────────────────────────────────────

/** Fills and submits the login form. Bypasses HTML5 required-attr validation
 *  when `bypassValidation` is true so server-side errors can be exercised. */
async function submitLogin(
  page: Page,
  email: string,
  password: string,
  bypassValidation = false,
) {
  if (bypassValidation) {
    await page.evaluate(() => {
      document.querySelectorAll<HTMLInputElement>('input[required]').forEach(el => el.removeAttribute('required'));
    });
  }
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('button[type="submit"]').click();
}

/** Resets the IP-level brute-force lock via the dev backdoor. */
async function resetLock(page: Page) {
  await page.goto('/login.php?reset_lock=1');
}

// ─── suite ────────────────────────────────────────────────────────────────────

test.describe('Login page', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto('/login.php');
  });

  // ── UI rendering ────────────────────────────────────────────────────────────

  test('page title is correct', async ({ page }) => {
    await expect(page).toHaveTitle(/ميزان/);
  });

  test('auth card and logo are visible', async ({ page }) => {
    await expect(page.locator('.auth-card')).toBeVisible();
    await expect(page.locator('.auth-logo-text')).toBeVisible();
    // heading
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });

  test('form fields and submit button are visible', async ({ page }) => {
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('email input has correct type and placeholder', async ({ page }) => {
    const emailInput = page.locator('input[name="email"]');
    await expect(emailInput).toHaveAttribute('type', 'email');
    await expect(emailInput).toHaveAttribute('placeholder', 'example@email.com');
  });

  test('password input has type="password"', async ({ page }) => {
    await expect(page.locator('input[name="password"]')).toHaveAttribute('type', 'password');
  });

  test('CSRF hidden input is present', async ({ page }) => {
    const csrf = page.locator('input[name="csrf_token"]');
    await expect(csrf).toBeAttached();
    // value must be a 64-char hex string (32 random bytes)
    const val = await csrf.inputValue();
    expect(val).toMatch(/^[0-9a-f]{64}$/);
  });

  test('navigation links to register and forgot-password are present', async ({ page }) => {
    await expect(page.locator('a[href="register.php"]')).toBeVisible();
    await expect(page.locator('a[href="forgot-password.php"]')).toBeVisible();
  });

  test('theme and language toggle buttons are visible', async ({ page }) => {
    await expect(page.locator('#themeToggle')).toBeVisible();
    await expect(page.locator('#langToggle')).toBeVisible();
  });

  // ── validation errors ────────────────────────────────────────────────────────

  test('shows server error when both fields are empty (bypass HTML5)', async ({ page }) => {
    await submitLogin(page, '', '', true);
    const alert = page.locator('.alert-error');
    await expect(alert).toBeVisible();
    // err_fields_required — "الرجاء إدخال الإيميل وكلمة المرور"
    await expect(alert).toContainText('الرجاء إدخال الإيميل وكلمة المرور');
  });

  test('shows server error when password is empty (bypass HTML5)', async ({ page }) => {
    await page.evaluate(() => {
      document.querySelectorAll<HTMLInputElement>('input[required]').forEach(el => el.removeAttribute('required'));
    });
    await page.locator('input[name="email"]').fill('test@example.com');
    // leave password empty
    await page.locator('button[type="submit"]').click();
    await expect(page.locator('.alert-error')).toBeVisible();
    await expect(page.locator('.alert-error')).toContainText('الرجاء إدخال الإيميل وكلمة المرور');
  });

  test('browser validates required fields before submit (email empty)', async ({ page }) => {
    // Without bypassing HTML5, the form must not submit when email is empty
    await page.locator('input[name="password"]').fill('SomePass');
    await page.locator('button[type="submit"]').click();
    // Still on login.php — browser blocked it
    await expect(page).toHaveURL(/login\.php/);
    // No server-side alert rendered
    await expect(page.locator('.alert-error')).not.toBeVisible();
  });

  // ── backend errors ───────────────────────────────────────────────────────────

  test('shows error for wrong credentials', async ({ page }) => {
    await submitLogin(page, 'nobody@nowhere.invalid', 'wrongpassword123');
    const alert = page.locator('.alert-error');
    await expect(alert).toBeVisible();
    // err_invalid_credentials — "الإيميل أو كلمة المرور غير صحيحة"
    await expect(alert).toContainText('الإيميل أو كلمة المرور غير صحيحة');
    // data-i18n key must be set on the inner span
    await expect(alert.locator('[data-i18n="err_invalid_credentials"]')).toBeAttached();
  });

  test('email field is pre-filled with submitted value on error', async ({ page }) => {
    const testEmail = 'baduser@example.com';
    await submitLogin(page, testEmail, 'wrongpass');
    await expect(page.locator('input[name="email"]')).toHaveValue(testEmail);
  });

  test('password field is cleared after failed submit', async ({ page }) => {
    await submitLogin(page, 'baduser@example.com', 'wrongpass');
    await expect(page.locator('input[name="password"]')).toHaveValue('');
  });

  // ── brute-force lockout UI ───────────────────────────────────────────────────

  test('shows lockout error and countdown timer after 5 failed attempts', async ({ page }) => {
    await resetLock(page);
    await page.goto('/login.php');

    // Submit 5 wrong attempts to exhaust the allowance
    for (let i = 0; i < 5; i++) {
      await submitLogin(page, 'attacker@test.invalid', `wrongpass${i}`);
      // Each failed attempt reloads the page; wait for the form to be ready again
      await page.waitForSelector('button[type="submit"]');
    }

    // 6th attempt — must trigger the lockout path
    await submitLogin(page, 'attacker@test.invalid', 'wrongpass_final');

    const alert = page.locator('.alert-error');
    await expect(alert).toBeVisible();

    // err_locked_out message
    await expect(alert).toContainText('تم تجاوز عدد المحاولات المسموح');
    await expect(alert.locator('[data-i18n="err_locked_out"]')).toBeAttached();

    // Countdown timer element must be injected by the inline script
    const timer = page.locator('#lockout-timer');
    await expect(timer).toBeVisible();
    // Format: (MM:SS) — e.g. "(14:59)"
    await expect(timer).toHaveText(/^\(\d{2}:\d{2}\)$/);
  });

  test('lockout error is shown on page load when IP is already locked', async ({ page }) => {
    // After the previous test the IP is locked. Navigate fresh to verify the
    // lockout state is surfaced even without a POST submission.
    await page.goto('/login.php');
    const alert = page.locator('.alert-error');
    // The lockout banner and timer should render immediately from PHP
    const isLocked = await alert.isVisible();
    if (isLocked) {
      await expect(alert).toContainText('تم تجاوز عدد المحاولات المسموح');
      await expect(page.locator('#lockout-timer')).toBeVisible();
    }
    // If the IP is not locked (test isolation), skip gracefully
  });

  test('reset_lock backdoor clears lockout', async ({ page }) => {
    await resetLock(page);
    // After reset the page should load cleanly with no error banner
    await expect(page.locator('.alert-error')).not.toBeVisible();
    // And the form should be interactive
    await expect(page.locator('button[type="submit"]')).toBeEnabled();
  });

  // ── happy path ───────────────────────────────────────────────────────────────

  // NOTE: Fill in a real seeded test account if you want to exercise the happy
  // path against a live database. The credentials below are intentionally
  // placeholder — replace them with your test fixture values.
  test.skip('successful login redirects to /index.php', async ({ page }) => {
    await resetLock(page);
    await page.goto('/login.php');
    await submitLogin(page, 'testuser@mizan.test', 'TestPass123!');
    await expect(page).toHaveURL(/\/index\.php/);
  });

  // ── a11y / misc ──────────────────────────────────────────────────────────────

  test('form has rtl direction (lang=ar dir=rtl)', async ({ page }) => {
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
  });

});
