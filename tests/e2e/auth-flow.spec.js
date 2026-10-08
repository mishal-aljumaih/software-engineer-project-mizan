const { test, expect } = require('@playwright/test');

const baseURL = process.env.MIZAN_BASE_URL || 'http://localhost';
const runId = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;

const account = {
  name: `Mizan E2E ${runId}`,
  email: `mizan.e2e.${runId}@example.com`,
  password: `MizanE2E!${runId}`,
};

const selectors = {
  auth: {
    email: 'input[name="email"]',
    password: 'input[name="password"]',
    name: 'input[name="name"]',
    confirmPassword: 'input[name="confirm_password"]',
    csrf: 'input[name="csrf_token"]',
    submit: 'button[type="submit"]',
    loginTitle: '[data-i18n="login_title"]',
    registerTitle: '[data-i18n="register_title"]',
    forgotPasswordTitle: '[data-i18n="forgot_password_title"]',
    adminEmail: '#admin_email',
    adminPassword: '#admin_pass',
  },
  alert: {
    invalidCredentials: '.alert-error [data-i18n="err_invalid_credentials"]',
    passwordMismatch: '.alert-error [data-i18n="pass_mismatch"]',
  },
  shell: {
    navbar: '#mainNavbar',
    sidebar: '#sidebarDesktop',
    main: '#mainContent',
    userMenu: '.navbar-user',
    logoutLink: 'a[href="/logout.php"]',
    logoutButton: '.btn-logout',
  },
};

const navTargets = [
  { href: '/index.php', titleKey: 'dashboard' },
  { href: '/pages/projects.php', titleKey: 'projects' },
  { href: '/pages/files.php', titleKey: 'files' },
  { href: '/pages/reports.php', titleKey: 'reports' },
  { href: '/pages/support.php', titleKey: 'support_title' },
];

const unauthorizedApiTargets = [
  '/api/projects.php?action=list',
  '/api/expenses.php?action=list&project_id=1',
  '/api/notifications.php?action=list',
  '/api/settings.php?action=export_data',
];

function appUrl(path) {
  const normalizedBase = baseURL.endsWith('/') ? baseURL : `${baseURL}/`;
  const normalizedPath = path.startsWith('/') ? path.slice(1) : path;
  return new URL(normalizedPath, normalizedBase).toString();
}

function pathPattern(path) {
  const escaped = path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return new RegExp(`${escaped.replace(/^\\\//, '\\/')}([?#].*)?$`);
}

async function preparePage(page) {
  await page.route(
    /^https:\/\/(cdn\.jsdelivr\.net|fonts\.googleapis\.com|fonts\.gstatic\.com|static\.cloudflareinsights\.com)\//,
    (route) => route.abort()
  );
  await page.addInitScript(() => {
    localStorage.setItem('mizan_lang', 'en');
    localStorage.setItem('mizan_theme', 'light');
    localStorage.setItem('mizan_cookie', 'essential');
  });
}

async function gotoApp(page, path) {
  await page.goto(appUrl(path), { waitUntil: 'domcontentloaded' });
}

async function resetLoginLock(page) {
  await gotoApp(page, '/login.php?reset_lock=1');
  await expect(page).toHaveURL(/\/login\.php(?:[?#].*)?$/);
}

async function submitVisibleForm(page) {
  await page.locator(selectors.auth.submit).last().click();
  await page.waitForLoadState('domcontentloaded').catch(() => {});
}

async function loginAs(page, email = account.email, password = account.password) {
  await resetLoginLock(page);
  await page.locator(selectors.auth.email).fill(email);
  await page.locator(selectors.auth.password).fill(password);
  await submitVisibleForm(page);
  await expect(page).toHaveURL(pathPattern('/index.php'));
  await expect(page.locator(selectors.shell.navbar)).toBeVisible();
}

async function readJsonAllowBom(response) {
  const raw = await response.text();
  return JSON.parse(raw.replace(/^\uFEFF/, ''));
}

async function expectUnauthorizedApiContract(request, path) {
  const response = await request.get(appUrl(path), {
    headers: { Accept: 'application/json' },
  });
  expect.soft(response.status(), `${path} status`).toBe(401);
  expect.soft(response.headers()['content-type'] || '', `${path} content-type`).toContain('application/json');

  const raw = await response.text();
  let body;
  try {
    body = JSON.parse(raw);
  } catch (error) {
    expect.soft(raw.slice(0, 120), `${path} body must be strict JSON`).toBe('valid JSON');
    return;
  }

  expect.soft(body, `${path} body`).toMatchObject({
    success: false,
    error: 'err_unauthorized',
  });
}

async function expectUnauthorizedApiLenient(request, path) {
  const response = await request.get(appUrl(path), {
    headers: { Accept: 'application/json' },
  });
  expect(response.status(), `${path} status`).toBe(401);
  const body = await readJsonAllowBom(response);
  expect(body, `${path} body`).toMatchObject({
    success: false,
    error: 'err_unauthorized',
  });
}

test.describe('Mizan auth, API gates, and protected shell', () => {
  test.beforeEach(async ({ page }) => {
    await preparePage(page);
  });

  test('renders public authentication pages and their core controls', async ({ page }) => {
    await resetLoginLock(page);
    await expect(page.locator(selectors.auth.loginTitle)).toBeVisible();
    await expect(page.locator(selectors.auth.email)).toBeVisible();
    await expect(page.locator(selectors.auth.password)).toBeVisible();
    await expect(page.locator(selectors.auth.csrf)).toHaveAttribute('value', /[a-f0-9]{64}/);

    await page.locator('a[href="register.php"]').click();
    await expect(page).toHaveURL(pathPattern('/register.php'));
    await expect(page.locator(selectors.auth.registerTitle)).toBeVisible();
    await expect(page.locator(selectors.auth.name)).toBeVisible();
    await expect(page.locator(selectors.auth.confirmPassword)).toBeVisible();

    await gotoApp(page, '/forgot-password.php');
    await expect(page.locator(selectors.auth.forgotPasswordTitle)).toBeVisible();
    await expect(page.locator(selectors.auth.email)).toBeVisible();

    await gotoApp(page, '/admin_login.php');
    await expect(page.locator(selectors.auth.adminEmail)).toBeVisible();
    await expect(page.locator(selectors.auth.adminPassword)).toBeVisible();
  });

  test('returns useful UI and API errors for unauthenticated access', async ({ page }) => {
    await gotoApp(page, '/pages/projects.php');
    await expect(page).toHaveURL(/\/login\.php\?next=/);
    await expect(page.locator(selectors.auth.loginTitle)).toBeVisible();

    for (const target of unauthorizedApiTargets) {
      await expectUnauthorizedApiContract(page.request, target);
    }
  });

  test('shows validation feedback for invalid login and registration attempts', async ({ page }) => {
    await resetLoginLock(page);
    await page.locator(selectors.auth.email).fill(`missing.${runId}@example.com`);
    await page.locator(selectors.auth.password).fill('WrongPassword!123');
    await submitVisibleForm(page);
    await expect(page.locator(selectors.alert.invalidCredentials)).toBeVisible();
    await expect(page.locator(selectors.auth.email)).toHaveValue(`missing.${runId}@example.com`);

    await gotoApp(page, '/register.php');
    await page.locator(selectors.auth.name).fill('Validation User');
    await page.locator(selectors.auth.email).fill(`validation.${runId}@example.com`);
    await page.locator(selectors.auth.password).fill('short');
    await page.locator(selectors.auth.confirmPassword).fill('short');
    await page.locator(selectors.auth.submit).last().click();
    await expect
      .poll(() => page.locator(selectors.auth.password).evaluate((input) => input.validity.tooShort))
      .toBe(true);

    await page.locator(selectors.auth.password).fill(`MizanE2E!${runId}`);
    await page.locator(selectors.auth.confirmPassword).fill(`Mismatch!${runId}`);
    await submitVisibleForm(page);
    await expect(page.locator(selectors.alert.passwordMismatch)).toBeVisible();
  });

  test.describe.serial('authenticated user lifecycle', () => {
    test('registers a fresh user, reads authenticated APIs, and logs out cleanly', async ({ page }) => {
      await gotoApp(page, '/register.php');
      await page.locator(selectors.auth.name).fill(account.name);
      await page.locator(selectors.auth.email).fill(account.email);
      await page.locator(selectors.auth.password).fill(account.password);
      await page.locator(selectors.auth.confirmPassword).fill(account.password);
      await submitVisibleForm(page);

      await expect(page).toHaveURL(pathPattern('/index.php'));
      await expect(page.locator(selectors.shell.navbar)).toBeVisible();
      await expect(page.locator(selectors.shell.main)).toBeVisible();

      const sessionCookie = (await page.context().cookies()).find((cookie) => cookie.name === 'MIZANSESSID');
      expect(sessionCookie).toMatchObject({
        httpOnly: true,
        sameSite: 'Lax',
      });

      const exportResponse = await page.request.get(appUrl('/api/settings.php?action=export_data'), {
        headers: { Accept: 'application/json' },
      });
      expect(exportResponse.status()).toBe(200);
      const exportBody = await readJsonAllowBom(exportResponse);
      expect(exportBody.success).toBe(true);
      expect(exportBody.data.user.email).toBe(account.email);

      await gotoApp(page, '/logout.php');
      await expect(page.locator(selectors.shell.logoutButton)).toBeVisible();
      await page.locator(selectors.shell.logoutButton).click();
      await expect(page).toHaveURL(pathPattern('/welcome.php'));
      await expectUnauthorizedApiLenient(page.request, '/api/settings.php?action=export_data');
    });

    test('logs back in and renders primary authenticated navigation', async ({ page }) => {
      await loginAs(page);

      for (const target of navTargets) {
        await page.locator(`${selectors.shell.sidebar} a[href="${target.href}"]`).click();
        await expect(page).toHaveURL(pathPattern(target.href));
        await expect(page.locator(selectors.shell.main)).toBeVisible();
        await expect(page.locator(`[data-i18n="${target.titleKey}"]`).first()).toBeVisible();
      }

      await page.locator(selectors.shell.userMenu).click();
      await expect(page.locator(selectors.shell.logoutLink).first()).toBeVisible();
    });
  });
});
