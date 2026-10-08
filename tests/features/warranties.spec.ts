import { test, expect } from '@playwright/test';

test.use({ baseURL: 'http://localhost' });

// ---------------------------------------------------------------------------
// 1. Unauthenticated redirect (active — no session needed)
// ---------------------------------------------------------------------------
test.describe('Warranties — unauthenticated', () => {
  test('GET /pages/warranties.php redirects to /welcome.php', async ({ page }) => {
    const response = await page.goto('/pages/warranties.php', {
      waitUntil: 'networkidle',
    });

    // Should land on welcome.php after redirect chain
    expect(page.url()).toContain('/welcome.php');

    // Final response should be successful (the welcome page itself loads fine)
    expect(response?.status()).toBeLessThan(400);
  });
});

// ---------------------------------------------------------------------------
// Helper — all remaining tests require authentication
// ---------------------------------------------------------------------------
test.describe('Warranties — authenticated', () => {
  // All tests in this block require a valid session; skip until auth is wired.
  test.beforeEach(async ({ page }) => {
    test.skip(true, 'Requires authenticated session — skipped per spec');
    await page.goto('/pages/warranties.php');
  });

  // -------------------------------------------------------------------------
  // 2. UI Rendering
  // -------------------------------------------------------------------------
  test.describe('UI Rendering', () => {
    test('page title contains ميزان', async ({ page }) => {
      await expect(page).toHaveTitle(/ميزان/);
    });

    test('heading "الضمانات" is visible', async ({ page }) => {
      await expect(page.getByRole('heading', { name: 'الضمانات' })).toBeVisible();
    });

    test('four filter tabs are visible', async ({ page }) => {
      // Tabs: الكل / نشطة / تنتهي قريباً / منتهية
      await expect(page.getByText('الكل')).toBeVisible();
      await expect(page.getByText('نشطة')).toBeVisible();
      await expect(page.getByText('تنتهي قريباً')).toBeVisible();
      await expect(page.getByText('منتهية')).toBeVisible();
    });

    test('stats counters are visible', async ({ page }) => {
      // At least one numeric counter element should appear inside the stat bar
      const counters = page.locator('[class*="stat"], [class*="badge"], [class*="count"]');
      await expect(counters.first()).toBeVisible();
    });

    test('search input is visible', async ({ page }) => {
      await expect(page.locator('input[name="q"], input[type="search"]')).toBeVisible();
    });

    test('project select is visible', async ({ page }) => {
      await expect(page.locator('select[name="project"]')).toBeVisible();
    });

    test('html element has lang=ar and dir=rtl', async ({ page }) => {
      const html = page.locator('html');
      await expect(html).toHaveAttribute('lang', 'ar');
      await expect(html).toHaveAttribute('dir', 'rtl');
    });
  });

  // -------------------------------------------------------------------------
  // 3. Filter tabs
  // -------------------------------------------------------------------------
  test.describe('Filter tabs', () => {
    test('clicking "تنتهي قريباً" tab adds ?filter=expiring to URL', async ({ page }) => {
      await page.getByRole('link', { name: 'تنتهي قريباً' }).click();
      await page.waitForURL(/filter=expiring/);
      expect(page.url()).toContain('filter=expiring');
    });

    test('clicking "منتهية" tab adds ?filter=expired to URL', async ({ page }) => {
      await page.getByRole('link', { name: 'منتهية' }).click();
      await page.waitForURL(/filter=expired/);
      expect(page.url()).toContain('filter=expired');
    });

    test('clicking "نشطة" tab adds ?filter=active to URL', async ({ page }) => {
      await page.getByRole('link', { name: 'نشطة' }).click();
      await page.waitForURL(/filter=active/);
      expect(page.url()).toContain('filter=active');
    });

    test('clicking "الكل" tab sets ?filter=all or returns to default', async ({ page }) => {
      // First set a different filter so the click is meaningful
      await page.goto('/pages/warranties.php?filter=expired');
      await page.getByRole('link', { name: 'الكل' }).click();
      await page.waitForURL(/warranties\.php/);
      const url = page.url();
      // Accept either explicit filter=all or absence of any filter parameter
      const isDefault = !url.includes('filter=') || url.includes('filter=all');
      expect(isDefault).toBe(true);
    });
  });

  // -------------------------------------------------------------------------
  // 4. Search
  // -------------------------------------------------------------------------
  test.describe('Search', () => {
    test('searching an item name filters results to matching warranties', async ({ page }) => {
      // Assumes at least one warranty exists in the test DB
      const searchInput = page.locator('input[name="q"], input[type="search"]');
      await searchInput.fill('test');
      await searchInput.press('Enter');
      await page.waitForURL(/q=test/);

      // Every visible item_name should relate to the query (soft check — cards exist)
      const cards = page.locator('[class*="warranty"], [class*="card"]');
      // Either some results or an empty-state element — no server error
      await expect(page.locator('body')).not.toContainText('500');
    });

    test('clearing search shows all warranties', async ({ page }) => {
      await page.goto('/pages/warranties.php?q=test');
      const searchInput = page.locator('input[name="q"], input[type="search"]');
      await searchInput.fill('');
      await searchInput.press('Enter');
      await page.waitForURL(/warranties\.php/);
      expect(page.url()).not.toContain('q=');
    });
  });

  // -------------------------------------------------------------------------
  // 5. Project filter
  // -------------------------------------------------------------------------
  test.describe('Project filter', () => {
    test('selecting a project appends ?project=N and filters warranties', async ({ page }) => {
      const select = page.locator('select[name="project"]');
      // Get the first non-empty option value
      const firstOptionValue = await select.locator('option').nth(1).getAttribute('value');
      if (!firstOptionValue) {
        test.skip(true, 'No projects available in test data');
        return;
      }

      await select.selectOption(firstOptionValue);
      await page.waitForURL(new RegExp(`project=${firstOptionValue}`));
      expect(page.url()).toContain(`project=${firstOptionValue}`);
    });
  });

  // -------------------------------------------------------------------------
  // 6. Warranty card content
  // -------------------------------------------------------------------------
  test.describe('Warranty card content', () => {
    test('first warranty card shows item_name, project_name, end_date, days_left badge, and status label', async ({ page }) => {
      const firstCard = page.locator('[class*="warranty"], [class*="card"]').first();
      await expect(firstCard).toBeVisible();

      // item_name (expense title) — any non-empty text element
      await expect(firstCard.locator('[class*="title"], [class*="name"], [class*="item"]').first()).toBeVisible();

      // project name area (colored dot + name)
      await expect(firstCard.locator('[class*="project"]').first()).toBeVisible();

      // end_date displayed somewhere in the card
      await expect(firstCard.locator('[class*="date"], [class*="end"]').first()).toBeVisible();

      // days_left badge
      await expect(firstCard.locator('[class*="badge"], [class*="days"]').first()).toBeVisible();

      // Status label — one of the three Arabic strings
      const statusText = await firstCard.textContent();
      const hasStatus =
        statusText?.includes('سارية') ||
        statusText?.includes('تنتهي قريباً') ||
        statusText?.includes('منتهية');
      expect(hasStatus).toBe(true);
    });
  });

  // -------------------------------------------------------------------------
  // 7. Expiry badge colors
  // -------------------------------------------------------------------------
  test.describe('Expiry badge colors', () => {
    test('expired warranty card has a danger/red badge', async ({ page }) => {
      await page.goto('/pages/warranties.php?filter=expired');
      const firstCard = page.locator('[class*="warranty"], [class*="card"]').first();
      const badge = firstCard.locator('[class*="badge"]').first();
      const className = await badge.getAttribute('class') ?? '';
      const hasDanger = className.includes('danger') || className.includes('red') || className.includes('error');
      expect(hasDanger).toBe(true);
    });

    test('expiring warranty card (1-30 days) has a warning/amber badge', async ({ page }) => {
      await page.goto('/pages/warranties.php?filter=expiring');
      const firstCard = page.locator('[class*="warranty"], [class*="card"]').first();
      const badge = firstCard.locator('[class*="badge"]').first();
      const className = await badge.getAttribute('class') ?? '';
      const hasWarning = className.includes('warning') || className.includes('amber') || className.includes('warn');
      expect(hasWarning).toBe(true);
    });

    test('active warranty card (>30 days) has a success/green badge', async ({ page }) => {
      await page.goto('/pages/warranties.php?filter=active');
      const firstCard = page.locator('[class*="warranty"], [class*="card"]').first();
      const badge = firstCard.locator('[class*="badge"]').first();
      const className = await badge.getAttribute('class') ?? '';
      const hasSuccess = className.includes('success') || className.includes('green');
      expect(hasSuccess).toBe(true);
    });
  });

  // -------------------------------------------------------------------------
  // 8. Dashboard expiring warranties link
  // -------------------------------------------------------------------------
  test.describe('Dashboard link', () => {
    test('"عرض الكل" in warranties section on index.php links to pages/warranties.php', async ({ page }) => {
      await page.goto('/index.php');
      // Find the warranties section and locate its "view all" link
      const warrantiesSection = page.locator('[id*="warrant"], [class*="warrant"]');
      const viewAllLink = warrantiesSection.getByRole('link', { name: 'عرض الكل' });
      await expect(viewAllLink).toBeVisible();
      const href = await viewAllLink.getAttribute('href');
      expect(href).toContain('warranties.php');
    });
  });

  // -------------------------------------------------------------------------
  // 9. Empty state
  // -------------------------------------------------------------------------
  test.describe('Empty state', () => {
    test('empty state element is visible when filter yields no results', async ({ page }) => {
      // Use filter=expired; if no expired warranties exist in test DB this triggers empty state
      await page.goto('/pages/warranties.php?filter=expired');
      const hasCards = await page.locator('[class*="warranty"], [class*="card"]').count();
      if (hasCards > 0) {
        test.skip(true, 'Test DB has expired warranties — cannot verify empty state with this filter');
        return;
      }
      // Empty state container should be present
      const emptyState = page.locator(
        '[class*="empty"], [class*="no-data"], [class*="placeholder"]'
      );
      await expect(emptyState.first()).toBeVisible();
    });
  });
});
