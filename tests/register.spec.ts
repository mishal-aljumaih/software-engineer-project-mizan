import { test, expect, Page } from '@playwright/test';

// ─── helpers ──────────────────────────────────────────────────────────────────

interface RegisterPayload {
  name?: string;
  email?: string;
  password?: string;
  confirm?: string;
  bypassValidation?: boolean;
}

/** Fills and submits the registration form.
 *  Set `bypassValidation: true` to strip HTML5 `required`/`minlength`
 *  attributes so the POST reaches the server even with incomplete data. */
async function submitRegister(page: Page, payload: RegisterPayload) {
  const {
    name = '',
    email = '',
    password = '',
    confirm = '',
    bypassValidation = false,
  } = payload;

  if (bypassValidation) {
    await page.evaluate(() => {
      document.querySelectorAll<HTMLInputElement>('input[required]').forEach(el => {
        el.removeAttribute('required');
        el.removeAttribute('minlength');
      });
    });
  }

  await page.locator('input[name="name"]').fill(name);
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('input[name="confirm_password"]').fill(confirm);
  await page.locator('button[type="submit"]').click();
}

/** Generates a unique email address to avoid "email in use" collisions. */
function uniqueEmail() {
  return `playwright-${Date.now()}@example.invalid`;
}

// ─── suite ────────────────────────────────────────────────────────────────────

test.describe('Register page', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto('/register.php');
  });

  // ── UI rendering ─────────────────────────────────────────────────────────────

  test('page title is correct', async ({ page }) => {
    await expect(page).toHaveTitle(/ميزان/);
  });

  test('auth card and logo are visible', async ({ page }) => {
    await expect(page.locator('.auth-card')).toBeVisible();
    await expect(page.locator('.auth-logo-text')).toBeVisible();
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });

  test('all four form fields and submit button are visible', async ({ page }) => {
    await expect(page.locator('input[name="name"]')).toBeVisible();
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    await expect(page.locator('input[name="confirm_password"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('name input has correct type and placeholder', async ({ page }) => {
    const nameInput = page.locator('input[name="name"]');
    await expect(nameInput).toHaveAttribute('type', 'text');
    await expect(nameInput).toHaveAttribute('placeholder', 'محمد العمري');
  });

  test('email input has type="email"', async ({ page }) => {
    await expect(page.locator('input[name="email"]')).toHaveAttribute('type', 'email');
  });

  test('password input has minlength="8"', async ({ page }) => {
    await expect(page.locator('input[name="password"]')).toHaveAttribute('minlength', '8');
  });

  test('confirm_password has type="password"', async ({ page }) => {
    await expect(page.locator('input[name="confirm_password"]')).toHaveAttribute('type', 'password');
  });

  test('CSRF hidden input is present and populated', async ({ page }) => {
    const csrf = page.locator('input[name="csrf_token"]');
    await expect(csrf).toBeAttached();
    const val = await csrf.inputValue();
    expect(val).toMatch(/^[0-9a-f]{64}$/);
  });

  test('link to login page is present', async ({ page }) => {
    await expect(page.locator('a[href="login.php"]')).toBeVisible();
  });

  test('page uses rtl direction (lang=ar dir=rtl)', async ({ page }) => {
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
  });

  test('theme and language toggle buttons are present', async ({ page }) => {
    await expect(page.locator('#themeToggle')).toBeVisible();
    await expect(page.locator('#langToggle')).toBeVisible();
  });

  // ── validation errors — empty / missing fields ────────────────────────────────

  test('shows err_all_fields when all fields are empty (bypass HTML5)', async ({ page }) => {
    await submitRegister(page, { bypassValidation: true });
    const alert = page.locator('.alert-error');
    await expect(alert).toBeVisible();
    // err_all_fields — "الرجاء ملء جميع الحقول"
    await expect(alert).toContainText('الرجاء ملء جميع الحقول');
    await expect(alert.locator('[data-i18n="err_all_fields"]')).toBeAttached();
  });

  test('shows err_all_fields when name is missing (bypass HTML5)', async ({ page }) => {
    await submitRegister(page, {
      email: uniqueEmail(),
      password: 'ValidPass1!',
      confirm: 'ValidPass1!',
      bypassValidation: true,
    });
    await expect(page.locator('.alert-error')).toContainText('الرجاء ملء جميع الحقول');
  });

  test('shows err_all_fields when email is missing (bypass HTML5)', async ({ page }) => {
    await submitRegister(page, {
      name: 'Test User',
      password: 'ValidPass1!',
      confirm: 'ValidPass1!',
      bypassValidation: true,
    });
    await expect(page.locator('.alert-error')).toContainText('الرجاء ملء جميع الحقول');
  });

  test('shows err_all_fields when password is missing (bypass HTML5)', async ({ page }) => {
    await submitRegister(page, {
      name: 'Test User',
      email: uniqueEmail(),
      confirm: 'ValidPass1!',
      bypassValidation: true,
    });
    await expect(page.locator('.alert-error')).toContainText('الرجاء ملء جميع الحقول');
  });

  test('browser blocks submit when required fields are empty (HTML5 native)', async ({ page }) => {
    // Click submit without filling anything — browser should prevent the POST
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/register\.php/);
    await expect(page.locator('.alert-error')).not.toBeVisible();
  });

  // ── password validation ───────────────────────────────────────────────────────

  test('shows pass_mismatch when passwords do not match', async ({ page }) => {
    await submitRegister(page, {
      name: 'Test User',
      email: uniqueEmail(),
      password: 'ValidPass1!',
      confirm: 'DifferentPass!',
    });
    const alert = page.locator('.alert-error');
    await expect(alert).toBeVisible();
    // pass_mismatch — "كلمتا المرور غير متطابقتين"
    await expect(alert).toContainText('كلمتا المرور غير متطابقتين');
    await expect(alert.locator('[data-i18n="pass_mismatch"]')).toBeAttached();
  });

  test('shows pass_min_8 when password is shorter than 8 characters (bypass minlength)', async ({ page }) => {
    await submitRegister(page, {
      name: 'Test User',
      email: uniqueEmail(),
      password: 'abc',
      confirm: 'abc',
      bypassValidation: true,
    });
    const alert = page.locator('.alert-error');
    await expect(alert).toBeVisible();
    // pass_min_8 — "كلمة المرور يجب أن تكون 8 أحرف على الأقل"
    await expect(alert).toContainText('كلمة المرور يجب أن تكون 8 أحرف على الأقل');
    await expect(alert.locator('[data-i18n="pass_min_8"]')).toBeAttached();
  });

  // ── email validation ──────────────────────────────────────────────────────────

  test('shows err_invalid_email_domain for a malformed email (bypass type=email)', async ({ page }) => {
    // Override the type attribute so the browser accepts the bad value
    await page.evaluate(() => {
      const el = document.querySelector<HTMLInputElement>('input[name="email"]');
      if (el) el.type = 'text';
    });
    await submitRegister(page, {
      name: 'Test User',
      email: 'not-an-email',
      password: 'ValidPass1!',
      confirm: 'ValidPass1!',
      bypassValidation: true,
    });
    const alert = page.locator('.alert-error');
    await expect(alert).toBeVisible();
    // err_invalid_email_domain — "صيغة البريد الإلكتروني غير صحيحة أو نطاقها غير موجود"
    await expect(alert).toContainText('صيغة البريد الإلكتروني غير صحيحة');
    await expect(alert.locator('[data-i18n="err_invalid_email_domain"]')).toBeAttached();
  });

  // ── backend errors ────────────────────────────────────────────────────────────

  test('shows err_email_in_use for a duplicate email', async ({ page }) => {
    // This test requires a pre-existing account in the DB.
    // Replace the email below with one that is seeded in your test DB.
    const existingEmail = 'admin@mizan.test';

    await submitRegister(page, {
      name: 'Duplicate User',
      email: existingEmail,
      password: 'ValidPass1!',
      confirm: 'ValidPass1!',
    });

    const alert = page.locator('.alert-error');
    await expect(alert).toBeVisible();
    // err_email_in_use — "هذا الإيميل مستخدم من قبل"
    await expect(alert).toContainText('هذا الإيميل مستخدم من قبل');
    await expect(alert.locator('[data-i18n="err_email_in_use"]')).toBeAttached();
  });

  // ── registration rate-limit ───────────────────────────────────────────────────

  test('shows err_reg_rate_limited after 3 duplicate-email attempts', async ({ page }) => {
    // Reset any existing reg lock for this IP first by hitting the login backdoor
    // (shares the same login_attempts table with a "_reg" suffix key)
    await page.goto('/login.php?reset_lock=1');
    await page.goto('/register.php');

    // Submit 3 times with a duplicate email to exhaust the reg rate limit.
    // Requires a seeded account — replace with real fixture.
    const existingEmail = 'admin@mizan.test';
    for (let i = 0; i < 3; i++) {
      await submitRegister(page, {
        name: 'Attacker',
        email: existingEmail,
        password: 'ValidPass1!',
        confirm: 'ValidPass1!',
      });
      await page.waitForSelector('button[type="submit"]');
    }

    // 4th attempt should now be rate-limited
    await submitRegister(page, {
      name: 'Attacker',
      email: existingEmail,
      password: 'ValidPass1!',
      confirm: 'ValidPass1!',
    });

    const alert = page.locator('.alert-error');
    await expect(alert).toBeVisible();
    // err_reg_rate_limited — "تم تجاوز عدد المحاولات المسموح. حاول مجدداً بعد 15 دقيقة"
    await expect(alert).toContainText('تم تجاوز عدد المحاولات المسموح');
    await expect(alert.locator('[data-i18n="err_reg_rate_limited"]')).toBeAttached();
  });

  // ── happy path ────────────────────────────────────────────────────────────────

  // NOTE: This test creates a real user in the database. Run it against a
  // dedicated test DB only. It is skipped by default.
  test.skip('successful registration redirects to /index.php', async ({ page }) => {
    await submitRegister(page, {
      name: 'Playwright Tester',
      email: uniqueEmail(),
      password: 'ValidPass1!',
      confirm: 'ValidPass1!',
    });
    await expect(page).toHaveURL(/\/index\.php/);
  });

});
