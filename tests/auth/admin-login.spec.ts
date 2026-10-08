import { test, expect } from '@playwright/test';

test.use({ baseURL: 'http://localhost' });

test.describe('Admin Login Page', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto('/admin_login.php');
  });

  // ── 1. UI Rendering ──────────────────────────────────────────────────────────

  test('title contains ميزان', async ({ page }) => {
    await expect(page).toHaveTitle(/ميزان/);
  });

  test('.admin-card is visible', async ({ page }) => {
    await expect(page.locator('.admin-card')).toBeVisible();
  });

  test('.admin-badge contains وصول المدير فقط', async ({ page }) => {
    await expect(page.locator('.admin-badge')).toContainText('وصول المدير فقط');
  });

  test('admin-logo-text shows ميزان', async ({ page }) => {
    await expect(page.locator('.admin-logo-text')).toBeVisible();
    await expect(page.locator('.admin-logo-text')).toHaveText('ميزان');
  });

  test('admin-subtitle is visible', async ({ page }) => {
    await expect(page.locator('.admin-subtitle')).toBeVisible();
  });

  test('h1 heading is visible', async ({ page }) => {
    await expect(page.locator('h1')).toBeVisible();
  });

  test('#admin_email has correct attributes', async ({ page }) => {
    const emailInput = page.locator('#admin_email');
    await expect(emailInput).toBeVisible();
    await expect(emailInput).toHaveAttribute('type', 'email');
    await expect(emailInput).toHaveAttribute('placeholder', 'admin@example.com');
    await expect(emailInput).toHaveAttribute('required', '');
  });

  test('#admin_pass has correct attributes', async ({ page }) => {
    const passInput = page.locator('#admin_pass');
    await expect(passInput).toBeVisible();
    await expect(passInput).toHaveAttribute('type', 'password');
    await expect(passInput).toHaveAttribute('required', '');
  });

  test('submit button .admin-submit is visible', async ({ page }) => {
    await expect(page.locator('button.admin-submit')).toBeVisible();
  });

  test('.admin-back link points to /welcome.php', async ({ page }) => {
    const backLink = page.locator('a.admin-back');
    await expect(backLink).toBeVisible();
    await expect(backLink).toHaveAttribute('href', '/welcome.php');
  });

  test('#themeToggle and #langToggle are present', async ({ page }) => {
    await expect(page.locator('#themeToggle')).toBeAttached();
    await expect(page.locator('#langToggle')).toBeAttached();
  });

  test('robots meta is noindex, nofollow', async ({ page }) => {
    const robotsMeta = page.locator('meta[name="robots"]');
    await expect(robotsMeta).toHaveAttribute('content', /noindex/);
    await expect(robotsMeta).toHaveAttribute('content', /nofollow/);
  });

  test('CSRF hidden input present with 64-char hex value', async ({ page }) => {
    const csrfInput = page.locator('input[type="hidden"][name="csrf_token"]');
    await expect(csrfInput).toBeAttached();
    const value = await csrfInput.getAttribute('value');
    expect(value).toMatch(/^[0-9a-f]{64}$/i);
  });

  test('html element has lang=ar and dir=rtl after main.js loads', async ({ page }) => {
    await page.waitForLoadState('networkidle');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  });

  test('#bg-canvas particle element is in DOM', async ({ page }) => {
    await expect(page.locator('#bg-canvas')).toBeAttached();
  });

  // ── 2. Validation errors — server-side (bypass HTML5) ────────────────────────

  test('empty fields show err_fields_required in .admin-error', async ({ page }) => {
    await page.evaluate(() => {
      document.querySelectorAll<HTMLInputElement>('input[required]')
        .forEach(el => el.removeAttribute('required'));
    });
    await page.click('button.admin-submit');
    await page.waitForLoadState('networkidle');

    const errorEl = page.locator('.admin-error');
    await expect(errorEl).toBeVisible();
    await expect(errorEl).toContainText('الرجاء إدخال الإيميل وكلمة المرور');
  });

  test('err_fields_required span carries correct data-i18n key', async ({ page }) => {
    await page.evaluate(() => {
      document.querySelectorAll<HTMLInputElement>('input[required]')
        .forEach(el => el.removeAttribute('required'));
    });
    await page.click('button.admin-submit');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('.admin-error span[data-i18n="err_fields_required"]')).toBeAttached();
  });

  // ── 3. Backend error — wrong credentials ─────────────────────────────────────

  test('wrong credentials shows err_admin_credentials message', async ({ page }) => {
    await page.fill('#admin_email', 'notanadmin@example.com');
    await page.fill('#admin_pass', 'wrongpassword123');
    await page.click('button.admin-submit');
    await page.waitForLoadState('networkidle');

    const errorEl = page.locator('.admin-error');
    await expect(errorEl).toBeVisible();
    await expect(errorEl).toContainText(
      'الإيميل أو كلمة المرور غير صحيحة، أو الحساب لا يملك صلاحيات المدير'
    );
  });

  test('wrong credentials span has data-i18n="err_admin_credentials"', async ({ page }) => {
    await page.fill('#admin_email', 'notanadmin@example.com');
    await page.fill('#admin_pass', 'wrongpassword123');
    await page.click('button.admin-submit');
    await page.waitForLoadState('networkidle');
    await expect(
      page.locator('.admin-error span[data-i18n="err_admin_credentials"]')
    ).toBeAttached();
  });

  // ── 4. HTML5 native validation (no bypass) ───────────────────────────────────

  test('empty submit without bypass stays on page — no .admin-error rendered', async ({ page }) => {
    await page.click('button.admin-submit');
    expect(page.url()).toContain('/admin_login.php');
    await expect(page.locator('.admin-error')).not.toBeVisible();
  });

  // ── 5. Email pre-filled after error ─────────────────────────────────────────

  test('#admin_email retains submitted value after failed login', async ({ page }) => {
    const testEmail = 'wronguser@example.com';
    await page.fill('#admin_email', testEmail);
    await page.fill('#admin_pass', 'badpass99');
    await page.click('button.admin-submit');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('#admin_email')).toHaveValue(testEmail);
  });

  // ── 6. Navigation ────────────────────────────────────────────────────────────

  test('.admin-back link navigates to /welcome.php', async ({ page }) => {
    await page.click('a.admin-back');
    await page.waitForURL(/\/welcome\.php$/);
    expect(page.url()).toMatch(/\/welcome\.php$/);
  });

  // ── 7. Happy path (skip — needs seeded admin account in DB) ──────────────────

  test.skip('valid admin credentials redirect to /admin/dashboard.php', async ({ page }) => {
    // Replace with a real seeded admin account before enabling.
    await page.fill('#admin_email', 'admin@mizan.test');
    await page.fill('#admin_pass', 'AdminSecret123!');
    await page.click('button.admin-submit');
    await page.waitForURL(/\/admin\/dashboard\.php$/);
    expect(page.url()).toContain('/admin/dashboard.php');
  });

});
