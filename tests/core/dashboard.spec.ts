import { test, expect } from '@playwright/test';

test.use({ baseURL: 'http://localhost' });

// ---------------------------------------------------------------------------
// 1. Unauthenticated redirect
// ---------------------------------------------------------------------------

test.describe('Unauthenticated access', () => {
  test('GET /index.php without session redirects to /welcome.php', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'load' });
    await expect(page).toHaveURL(/welcome\.php/);
  });

  test('redirect lands on a URL matching /welcome.php', async ({ page }) => {
    const response = await page.goto('/index.php');
    // Final URL after redirect chain must contain welcome.php
    expect(page.url()).toMatch(/welcome\.php/);
    // PHP redirect is a 302 → Playwright follows it; the final HTTP status is 200
    // (response reflects the last navigation)
    expect(response?.status()).toBe(200);
  });
});

// ---------------------------------------------------------------------------
// 2. UI Rendering (authenticated session required — all skipped)
// ---------------------------------------------------------------------------

test.describe('UI rendering — authenticated', () => {
  test.skip(true, 'Requires authenticated PHP session ($_SESSION[user_id])');

  test('page <title> contains "ميزان" or "لوحة التحكم"', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    const title = await page.title();
    expect(title).toMatch(/ميزان|لوحة التحكم/);
  });

  test('page-header section is visible', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    await expect(page.locator('header.page-header.dashboard-header')).toBeVisible();
  });

  test('h1.page-title with data-i18n="dashboard" is visible', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    const heading = page.locator('h1.page-title[data-i18n="dashboard"]');
    await expect(heading).toBeVisible();
    await expect(heading).toHaveText(/لوحة التحكم/);
  });

  test('#greetingText is visible and non-empty', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    const greeting = page.locator('#greetingText');
    await expect(greeting).toBeVisible();
    const text = await greeting.textContent();
    expect(text?.trim().length).toBeGreaterThan(0);
  });

  test('"مشروع جديد" button is visible and has btn-primary class', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    const btn = page.locator('button.btn.btn-primary', { hasText: 'مشروع جديد' });
    await expect(btn).toBeVisible();
    await expect(btn).toHaveAttribute('onclick', /openProjectCreate/);
  });

  test('at least 2 .stat-card elements are visible', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    const cards = page.locator('.stat-card');
    await expect(cards.first()).toBeVisible();
    expect(await cards.count()).toBeGreaterThanOrEqual(2);
  });

  test('stat cards contain .count-up elements with data-target attribute', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    const countUps = page.locator('.stat-card .count-up');
    const count = await countUps.count();
    expect(count).toBeGreaterThan(0);
    for (let i = 0; i < count; i++) {
      await expect(countUps.nth(i)).toHaveAttribute('data-target', /.+/);
    }
  });

  test('expenses chart article[aria-label] is present', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    const chartCard = page.locator('article.card[aria-label="Expenses chart"]');
    await expect(chartCard).toBeVisible();
    const title = chartCard.locator('.card-title');
    await expect(title).toContainText('المصاريف');
  });

  test('#chartFilter select has exactly 3 options with values 6, 3, 12', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    const select = page.locator('#chartFilter');
    await expect(select).toBeVisible();
    const options = select.locator('option');
    await expect(options).toHaveCount(3);
    await expect(options.nth(0)).toHaveAttribute('value', '6');
    await expect(options.nth(1)).toHaveAttribute('value', '3');
    await expect(options.nth(2)).toHaveAttribute('value', '12');
  });

  test('warranties article is present with "عرض الكل" link', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    const warrantyCard = page.locator('article.card[aria-label="Expiring warranties"]');
    await expect(warrantyCard).toBeVisible();
    const link = warrantyCard.locator('a.section-link[href="pages/warranties.php"]');
    await expect(link).toBeVisible();
    await expect(link).toContainText('عرض الكل');
  });

  test('recent projects section present with link to pages/projects.php', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    const section = page.locator('section.card[aria-label="Recent projects"]');
    await expect(section).toBeVisible();
    const title = section.locator('.card-title');
    await expect(title).toContainText('آخر المشاريع');
    const link = section.locator('a.section-link[href="pages/projects.php"]');
    await expect(link).toBeVisible();
  });

  test('recent projects section shows empty-state OR a table', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    const section = page.locator('section.card[aria-label="Recent projects"]');
    const hasTable = await section.locator('table').count();
    const hasEmpty = await section.locator('.empty-state').count();
    expect(hasTable + hasEmpty).toBeGreaterThan(0);
  });

  test('<html> has lang="ar" and dir="rtl"', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  });
});

// ---------------------------------------------------------------------------
// 3. Interactivity (authenticated session required — all skipped)
// ---------------------------------------------------------------------------

test.describe('Interactivity — authenticated', () => {
  test.skip(true, 'Requires authenticated PHP session ($_SESSION[user_id])');

  test('clicking "مشروع جديد" opens the project create modal', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    const btn = page.locator('button.btn.btn-primary', { hasText: 'مشروع جديد' });
    await btn.click();
    // Modal should become visible; the exact selector depends on main.js implementation
    const modal = page.locator('.modal, [role="dialog"]').filter({ hasText: /مشروع|project/i });
    await expect(modal.first()).toBeVisible({ timeout: 5000 });
  });

  test('changing #chartFilter value keeps chart canvas visible', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    const select = page.locator('#chartFilter');
    // Chart canvas is only present when there is data
    const canvasCount = await page.locator('#expensesChart').count();
    test.skip(canvasCount === 0, 'No chart data available in this environment');
    await select.selectOption('3');
    await page.waitForTimeout(500); // allow updateChart() to re-render
    await expect(page.locator('#expensesChart')).toBeVisible();
    await select.selectOption('12');
    await page.waitForTimeout(500);
    await expect(page.locator('#expensesChart')).toBeVisible();
  });

  test('clicking a .table-row-hover navigates to /pages/project-detail.php', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    const rows = page.locator('.table-row-hover');
    const rowCount = await rows.count();
    test.skip(rowCount === 0, 'No project rows rendered — empty state active');
    await rows.first().click();
    await expect(page).toHaveURL(/pages\/project-detail\.php\?id=\d+/);
  });
});

// ---------------------------------------------------------------------------
// 4. Count-up animation (authenticated session required — all skipped)
// ---------------------------------------------------------------------------

test.describe('Count-up animation — authenticated', () => {
  test.skip(true, 'Requires authenticated PHP session ($_SESSION[user_id])');

  test('.count-up elements show non-zero text when data-target > 0', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    // Wait enough time for the count-up animation to complete
    await page.waitForTimeout(2000);
    const countUps = page.locator('.count-up');
    const count = await countUps.count();
    for (let i = 0; i < count; i++) {
      const el = countUps.nth(i);
      const target = await el.getAttribute('data-target');
      if (target && parseFloat(target) > 0) {
        const text = await el.textContent();
        expect(text?.trim()).not.toBe('0');
        expect(text?.trim().length).toBeGreaterThan(0);
      }
    }
  });

  test('count-up text after animation uses locale-formatted digits', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    const countUps = page.locator('.count-up');
    const count = await countUps.count();
    for (let i = 0; i < count; i++) {
      const text = (await countUps.nth(i).textContent()) ?? '';
      // Accept Arabic-Indic digits, Western digits, commas, dots, and spaces
      expect(text.trim()).toMatch(/^[\d٠-٩,.\s]+$/);
    }
  });
});

// ---------------------------------------------------------------------------
// 5. Greeting text (authenticated session required — all skipped)
// ---------------------------------------------------------------------------

test.describe('Greeting text — authenticated', () => {
  test.skip(true, 'Requires authenticated PHP session ($_SESSION[user_id])');

  test('#greetingText contains a greeting word', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'networkidle' });
    const greeting = page.locator('#greetingText');
    await expect(greeting).toBeVisible();
    const text = await greeting.textContent();
    // setGreeting() uses time-of-day salutations; accept any of the known variants
    expect(text).toMatch(/مرحباً|صباح|مساء|أهلاً|طيبة/);
  });
});

// ---------------------------------------------------------------------------
// 6. Navigation redirect (no auth needed — active)
// ---------------------------------------------------------------------------

test.describe('Navigation redirect — no auth', () => {
  test('/index.php redirect chain ends at a URL matching /welcome.php', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'load' });
    expect(page.url()).toMatch(/welcome\.php/);
  });

  test('/index.php redirect does not land on /index.php itself', async ({ page }) => {
    await page.goto('/index.php', { waitUntil: 'load' });
    expect(page.url()).not.toMatch(/index\.php/);
  });

  test('/index.php with trailing query string still redirects when unauthenticated', async ({ page }) => {
    await page.goto('/index.php?foo=bar', { waitUntil: 'load' });
    expect(page.url()).toMatch(/welcome\.php/);
  });
});
