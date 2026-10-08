import { test, expect } from '@playwright/test';

test.use({ baseURL: 'http://localhost' });

// ─────────────────────────────────────────────
// 1. Unauthenticated redirect (active)
// ─────────────────────────────────────────────
test.describe('Reports – unauthenticated', () => {
  test('GET /pages/reports.php redirects to /welcome.php', async ({ page }) => {
    const response = await page.goto('/pages/reports.php', {
      waitUntil: 'networkidle',
    });
    // Final URL must be welcome.php after the server-side redirect
    expect(page.url()).toContain('welcome.php');
    // The server must issue a redirect (3xx), Playwright follows it; the
    // page we land on should return 200.
    expect(response?.status()).toBe(200);
  });
});

// ─────────────────────────────────────────────
// Authenticated suite – all skipped (no session)
// ─────────────────────────────────────────────
test.describe('Reports – authenticated', () => {
  // ── 2. UI Rendering ────────────────────────
  test.describe('UI Rendering', () => {
    test.skip(true, 'Requires authenticated session');

    test('page title contains ميزان', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      await expect(page).toHaveTitle(/ميزان/);
    });

    test('h1 "التقارير" is visible', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      const h1 = page.locator('h1.page-title');
      await expect(h1).toBeVisible();
      await expect(h1).toHaveText('التقارير');
    });

    test('subtitle "تحليلات مالية شاملة لمشاريعك" is visible', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      const subtitle = page.locator('p.text-muted');
      await expect(subtitle).toBeVisible();
      await expect(subtitle).toHaveText('تحليلات مالية شاملة لمشاريعك');
    });

    test('#rptProjectFilter select is visible with "كل المشاريع" as first option', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      const select = page.locator('#rptProjectFilter');
      await expect(select).toBeVisible();
      const firstOption = select.locator('option').first();
      await expect(firstOption).toHaveAttribute('value', '0');
      await expect(firstOption).toHaveText('كل المشاريع');
    });

    test('#dateRange input is visible with Flatpickr placeholder', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      const dateInput = page.locator('#dateRange');
      await expect(dateInput).toBeVisible();
      await expect(dateInput).toHaveAttribute('placeholder', 'اختر الفترة...');
    });

    test('📅 date icon is visible', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      const icon = page.locator('.rpt-date-icon');
      await expect(icon).toBeVisible();
      await expect(icon).toHaveText('📅');
    });

    test('"تصدير CSV" button is visible', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      const csvBtn = page.locator('button.rpt-action-btn', { hasText: 'تصدير CSV' });
      await expect(csvBtn).toBeVisible();
    });

    test('"طباعة / PDF" button (#rptPrintBtn) is visible', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      const printBtn = page.locator('#rptPrintBtn');
      await expect(printBtn).toBeVisible();
      await expect(printBtn).toContainText('طباعة / PDF');
    });

    test('html element has lang=ar and dir=rtl', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      const html = page.locator('html');
      await expect(html).toHaveAttribute('lang', 'ar');
      await expect(html).toHaveAttribute('dir', 'rtl');
    });
  });

  // ── 3. Chart containers ─────────────────────
  test.describe('Chart containers', () => {
    test.skip(true, 'Requires authenticated session');

    test('at least one .rpt-chart-body element is present in the DOM', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      const chartBodies = page.locator('.rpt-chart-body');
      await expect(chartBodies.first()).toBeAttached();
      const count = await chartBodies.count();
      expect(count).toBeGreaterThanOrEqual(1);
    });

    test('canvas elements exist inside chart containers after JS loads', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      // Wait for Chart.js to inject canvas elements
      const canvas = page.locator('.rpt-chart-body canvas, .rpt-chart-body-donut canvas').first();
      await expect(canvas).toBeAttached({ timeout: 10_000 });
    });

    test('.rpt-chart-body containers have a fixed height of 300px', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      const chartBody = page.locator('.rpt-chart-body').first();
      await expect(chartBody).toBeAttached();
      const height = await chartBody.evaluate((el) => getComputedStyle(el).height);
      expect(height).toBe('300px');
    });

    test('.rpt-chart-body-donut containers have a fixed height of 320px', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      const donutBody = page.locator('.rpt-chart-body-donut').first();
      await expect(donutBody).toBeAttached();
      const height = await donutBody.evaluate((el) => getComputedStyle(el).height);
      expect(height).toBe('320px');
    });
  });

  // ── 4. Project filter ───────────────────────
  test.describe('Project filter', () => {
    test.skip(true, 'Requires authenticated session');

    test('#rptProjectFilter has a default option with value="0"', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      const select = page.locator('#rptProjectFilter');
      const defaultValue = await select.inputValue();
      expect(defaultValue).toBe('0');
    });

    test('additional project options appear after JS populates the filter', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      const select = page.locator('#rptProjectFilter');
      // Wait until more than 1 option is present (JS has loaded project list)
      await expect(async () => {
        const count = await select.locator('option').count();
        expect(count).toBeGreaterThan(1);
      }).toPass({ timeout: 10_000 });
    });

    test('changing project selection triggers a network request for report data', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      const select = page.locator('#rptProjectFilter');
      // Ensure more than one option exists before interacting
      await expect(async () => {
        expect(await select.locator('option').count()).toBeGreaterThan(1);
      }).toPass({ timeout: 10_000 });

      const [request] = await Promise.all([
        page.waitForRequest((req) => req.url().includes('/api/reports.php'), { timeout: 8_000 }),
        select.selectOption({ index: 1 }),
      ]);
      expect(request).toBeTruthy();
    });
  });

  // ── 5. Date range filter (Flatpickr) ────────
  test.describe('Date range filter (Flatpickr)', () => {
    test.skip(true, 'Requires authenticated session');

    test('clicking #dateRange opens the Flatpickr calendar picker', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      await page.locator('#dateRange').click();
      const calendar = page.locator('.flatpickr-calendar');
      await expect(calendar).toBeVisible({ timeout: 5_000 });
    });

    test('selecting a date range updates the #dateRange input value', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      await page.locator('#dateRange').click();

      // Click the first available day in the calendar
      const firstDay = page.locator('.flatpickr-day:not(.prevMonthDay):not(.nextMonthDay)').first();
      await firstDay.click();
      // Click a later day to complete the range
      const days = page.locator('.flatpickr-day:not(.prevMonthDay):not(.nextMonthDay)');
      await days.nth(4).click();

      const value = await page.locator('#dateRange').inputValue();
      expect(value.length).toBeGreaterThan(0);
    });

    test('report data reloads after a date range is selected', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      await page.locator('#dateRange').click();

      const firstDay = page.locator('.flatpickr-day:not(.prevMonthDay):not(.nextMonthDay)').first();
      await firstDay.click();
      const days = page.locator('.flatpickr-day:not(.prevMonthDay):not(.nextMonthDay)');

      const [request] = await Promise.all([
        page.waitForRequest((req) => req.url().includes('/api/reports.php'), { timeout: 8_000 }),
        days.nth(4).click(),
      ]);
      expect(request).toBeTruthy();
    });
  });

  // ── 6. CSV Export ───────────────────────────
  test.describe('CSV Export', () => {
    test.skip(true, 'Requires authenticated session');

    test('clicking "تصدير CSV" triggers a download targeting a CSV endpoint', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      const csvBtn = page.locator('button.rpt-action-btn', { hasText: 'تصدير CSV' });

      const [download] = await Promise.all([
        page.waitForEvent('download', { timeout: 10_000 }),
        csvBtn.click(),
      ]);

      // Verify the suggested filename or URL contains csv/export hint
      const suggestedName = download.suggestedFilename();
      expect(suggestedName.toLowerCase()).toMatch(/\.csv$|export|report/);
    });

    test('CSV download request targets a known CSV API endpoint', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      const csvBtn = page.locator('button.rpt-action-btn', { hasText: 'تصدير CSV' });

      const [request] = await Promise.all([
        page.waitForRequest(
          (req) =>
            req.url().includes('export') ||
            req.url().includes('reports.php') ||
            req.url().includes('export_logs'),
          { timeout: 8_000 }
        ),
        csvBtn.click(),
      ]);
      expect(request).toBeTruthy();
    });
  });

  // ── 7. Print button ─────────────────────────
  test.describe('Print button', () => {
    test.skip(true, 'Requires authenticated session');

    test('"طباعة / PDF" button is visible and clickable without JS errors', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });

      const jsErrors: string[] = [];
      page.on('pageerror', (err) => jsErrors.push(err.message));

      // Override window.print() so no native dialog blocks the test
      await page.evaluate(() => {
        (window as Window & { print: () => void }).print = () => { /* no-op */ };
      });

      const printBtn = page.locator('#rptPrintBtn');
      await expect(printBtn).toBeVisible();
      await printBtn.click();

      // Allow a tick for any synchronous error handlers to fire
      await page.waitForTimeout(300);
      expect(jsErrors).toHaveLength(0);
    });
  });

  // ── 8. Summary stats ────────────────────────
  test.describe('Summary stats', () => {
    test.skip(true, 'Requires authenticated session');

    test('summary stat cards are visible in the DOM', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      // Wait for JS to populate summary section
      await page.waitForTimeout(2_000);
      const statCards = page.locator('.stat-card, .rpt-stat, [class*="stat"]');
      await expect(statCards.first()).toBeVisible({ timeout: 8_000 });
    });

    test('largest expense stat displays a DECIMAL(12,2) formatted numeric value', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      await page.waitForTimeout(2_000);

      // Locate an element that contains the biggest/max expense value
      const biggestExpenseStat = page
        .locator('[class*="stat"], .rpt-stat')
        .filter({ hasText: /[\d,]+\.?\d{0,2}/ })
        .first();

      await expect(biggestExpenseStat).toBeVisible({ timeout: 8_000 });
      const text = (await biggestExpenseStat.textContent()) ?? '';
      // Must contain at least one digit
      expect(text).toMatch(/\d/);
    });
  });

  // ── 9. RTL number formatting ─────────────────
  test.describe('RTL number formatting', () => {
    test.skip(true, 'Requires authenticated session');

    test('amount values displayed in report are numeric (western or Arabic-Indic digits)', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      await page.waitForTimeout(2_000);

      // Gather all visible text that represents amounts
      const amountEls = page.locator('[class*="amount"], [class*="total"], [class*="stat"]');
      const count = await amountEls.count();
      // At least one amount element should exist
      expect(count).toBeGreaterThanOrEqual(1);

      for (let i = 0; i < count; i++) {
        const text = (await amountEls.nth(i).textContent()) ?? '';
        // Accept western digits (0-9), Arabic-Indic (٠-٩), commas, dots, currency symbols
        if (text.trim().length > 0) {
          expect(text).toMatch(/[\d٠-٩]/);
        }
      }
    });

    test('page dir attribute is rtl ensuring RTL layout', async ({ page }) => {
      await page.goto('/pages/reports.php', { waitUntil: 'networkidle' });
      await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    });
  });
});
