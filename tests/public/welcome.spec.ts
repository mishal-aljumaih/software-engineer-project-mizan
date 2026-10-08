import { test, expect } from '@playwright/test';

test.use({ baseURL: 'http://localhost' });

test.describe('welcome.php — public landing page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/welcome.php');
  });

  // ── 1. UI Rendering (unauthenticated) ────────────────────────────────────

  test('page title contains ميزان', async ({ page }) => {
    await expect(page).toHaveTitle(/ميزان/);
  });

  test('nav#nav is visible', async ({ page }) => {
    await expect(page.locator('#nav')).toBeVisible();
  });

  test('nav-brand link href points to /welcome.php', async ({ page }) => {
    const brand = page.locator('.nav-brand').first();
    await expect(brand).toHaveAttribute('href', '/welcome.php');
  });

  test('#themeBtn is present', async ({ page }) => {
    await expect(page.locator('#themeBtn')).toBeVisible();
  });

  test('#langBtn is present', async ({ page }) => {
    await expect(page.locator('#langBtn')).toBeVisible();
  });

  test('#navLogin is present with text دخول', async ({ page }) => {
    const login = page.locator('#navLogin');
    await expect(login).toBeVisible();
    await expect(login).toContainText('دخول');
  });

  test('#navReg is present and href="register.php"', async ({ page }) => {
    const reg = page.locator('#navReg');
    await expect(reg).toBeVisible();
    await expect(reg).toHaveAttribute('href', 'register.php');
  });

  test('html[data-theme=light] by default', async ({ page }) => {
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
  });

  test('html[lang=ar][dir=rtl] by default', async ({ page }) => {
    const html = page.locator('html');
    await expect(html).toHaveAttribute('lang', 'ar');
    await expect(html).toHaveAttribute('dir', 'rtl');
  });

  test('hero section visible with h1 and .hero-badge', async ({ page }) => {
    await expect(page.locator('.hero')).toBeVisible();
    await expect(page.locator('.hero h1')).toBeVisible();
    await expect(page.locator('.hero-badge')).toBeVisible();
  });

  test('.hero-btns visible', async ({ page }) => {
    await expect(page.locator('.hero-btns')).toBeVisible();
  });

  test('.hero-btns contains link to /register.php', async ({ page }) => {
    const startBtn = page.locator('.hero-btns a[href*="register.php"]');
    await expect(startBtn).toBeVisible();
  });

  test('.hero-btns contains link to /login.php', async ({ page }) => {
    const signinBtn = page.locator('.hero-btns a[href*="login.php"]');
    await expect(signinBtn).toBeVisible();
  });

  test('#dashCard is visible', async ({ page }) => {
    await expect(page.locator('#dashCard')).toBeVisible();
  });

  test('section#features visible with .sec-title', async ({ page }) => {
    const features = page.locator('section#features');
    await expect(features).toBeVisible();
    await expect(features.locator('.sec-title')).toBeVisible();
  });

  test('section#usecases is visible', async ({ page }) => {
    await expect(page.locator('section#usecases')).toBeVisible();
  });

  test('section#how is visible with 3 .step elements', async ({ page }) => {
    const how = page.locator('section#how');
    await expect(how).toBeVisible();
    await expect(how.locator('.step')).toHaveCount(3);
  });

  test('section.cta visible with .btn-cta href="/register.php"', async ({ page }) => {
    const cta = page.locator('section.cta');
    await expect(cta).toBeVisible();
    const ctaBtn = cta.locator('.btn-cta');
    await expect(ctaBtn).toBeVisible();
    await expect(ctaBtn).toHaveAttribute('href', '/register.php');
  });

  test('footer is visible and contains ميزان', async ({ page }) => {
    const footer = page.locator('footer');
    await expect(footer).toBeVisible();
    await expect(footer).toContainText('ميزان');
  });

  test('.uni-name contains university name', async ({ page }) => {
    await expect(page.locator('.uni-name')).toContainText('جامعة الإمام عبدالرحمن بن فيصل');
  });

  test('scrum lead Mishal Al-jumaih visible in .scrum-triangle', async ({ page }) => {
    const triangle = page.locator('.scrum-triangle');
    await expect(triangle).toBeVisible();
    await expect(triangle).toContainText('Mishal Al-jumaih');
  });

  test('#bg-canvas is in the DOM', async ({ page }) => {
    await expect(page.locator('#bg-canvas')).toBeAttached();
  });

  test('#dp-ar has class "on" by default (Arabic declaration shown)', async ({ page }) => {
    const dpAr = page.locator('#dp-ar');
    await expect(dpAr).toHaveClass(/\bon\b/);
  });

  // ── 2. Language toggle ───────────────────────────────────────────────────

  test('clicking #langBtn switches html to lang=en dir=ltr', async ({ page }) => {
    await page.locator('#langBtn').click();
    const html = page.locator('html');
    await expect(html).toHaveAttribute('lang', 'en');
    await expect(html).toHaveAttribute('dir', 'ltr');
  });

  test('#langBtn text changes to "ع" after switching to EN', async ({ page }) => {
    await page.locator('#langBtn').click();
    await expect(page.locator('#langBtn')).toContainText('ع');
  });

  test('[data-t="fttl"] text changes to English after toggle', async ({ page }) => {
    const arText = await page.locator('[data-t="fttl"]').textContent();
    await page.locator('#langBtn').click();
    const enText = await page.locator('[data-t="fttl"]').textContent();
    expect(enText).not.toBe(arText);
    expect(enText?.trim().length).toBeGreaterThan(0);
  });

  test('#dp-en becomes active after lang switch', async ({ page }) => {
    await page.locator('#langBtn').click();
    await expect(page.locator('#dp-en')).toHaveClass(/\bon\b/);
  });

  test('clicking #langBtn twice reverts to AR', async ({ page }) => {
    await page.locator('#langBtn').click();
    await page.locator('#langBtn').click();
    const html = page.locator('html');
    await expect(html).toHaveAttribute('lang', 'ar');
    await expect(html).toHaveAttribute('dir', 'rtl');
  });

  // ── 3. Theme toggle ──────────────────────────────────────────────────────

  test('clicking #themeBtn switches to dark theme', async ({ page }) => {
    await page.locator('#themeBtn').click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  });

  test('#themeBtn shows ☀️ in dark mode', async ({ page }) => {
    await page.locator('#themeBtn').click();
    await expect(page.locator('#themeBtn')).toContainText('☀');
  });

  test('clicking #themeBtn twice returns to light theme', async ({ page }) => {
    await page.locator('#themeBtn').click();
    await page.locator('#themeBtn').click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
  });

  // ── 4. Navigation links ──────────────────────────────────────────────────

  test('clicking .btn-p (ابدأ مجاناً) navigates to /register.php', async ({ page }) => {
    await page.locator('.hero-btns .btn-p').click();
    await expect(page).toHaveURL(/register\.php/);
  });

  test('clicking .btn-g (تسجيل الدخول) navigates to /login.php', async ({ page }) => {
    await page.locator('.hero-btns .btn-g').click();
    await expect(page).toHaveURL(/login\.php/);
  });

  test('CTA .btn-cta navigates to /register.php', async ({ page }) => {
    await page.locator('section.cta .btn-cta').click();
    await expect(page).toHaveURL(/register\.php/);
  });

  // ── 5. Scroll behavior ───────────────────────────────────────────────────

  test('nav gets class "scrolled" after scrolling 30px', async ({ page }) => {
    await page.evaluate(() => window.scrollTo({ top: 30, behavior: 'instant' }));
    // Give scroll event listeners a tick to fire
    await page.waitForTimeout(100);
    await expect(page.locator('#nav')).toHaveClass(/\bscrolled\b/);
  });
});
