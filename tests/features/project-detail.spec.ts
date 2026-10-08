import { test, expect } from '@playwright/test';

test.use({ baseURL: 'http://localhost' });

// ---------------------------------------------------------------------------
// 1. Invalid / missing ID redirect (active — no auth required for id <= 0)
// ---------------------------------------------------------------------------
test.describe('Invalid/missing ID redirect', () => {
  test('GET /pages/project-detail.php with no id redirects away', async ({ page }) => {
    await page.goto('/pages/project-detail.php');
    await expect(page).toHaveURL(/projects\.php|welcome\.php/);
  });

  test('GET /pages/project-detail.php?id=0 redirects away', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=0');
    await expect(page).toHaveURL(/projects\.php|welcome\.php/);
  });

  test('GET /pages/project-detail.php?id=-1 redirects away', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=-1');
    await expect(page).toHaveURL(/projects\.php|welcome\.php/);
  });

  // Ownership check only fires after auth — skip until credentials available
  test.skip('GET /pages/project-detail.php?id=99999999 (non-existent) redirects away', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=99999999');
    await expect(page).toHaveURL(/projects\.php/);
  });
});

// ---------------------------------------------------------------------------
// 2. Unauthenticated redirect (active)
// ---------------------------------------------------------------------------
test.describe('Unauthenticated redirect', () => {
  test('GET /pages/project-detail.php?id=1 without session redirects to welcome.php', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    await expect(page).toHaveURL(/welcome\.php/);
  });
});

// ---------------------------------------------------------------------------
// 3. UI Rendering (requires auth + valid project)
// ---------------------------------------------------------------------------
test.describe('UI Rendering', () => {
  test.skip('page title contains ميزان', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    await expect(page).toHaveTitle(/ميزان/);
  });

  test.skip('project name visible in a heading', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    const heading = page.locator('h1, h2, h3').first();
    await expect(heading).toBeVisible();
    await expect(heading).not.toBeEmpty();
  });

  test.skip('stat cards for expenses, warranties, and files are visible', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    // Each stat card exists; exact selectors depend on markup produced by header.php
    await expect(page.locator('[data-stat="expense_count"], .stat-card').first()).toBeVisible();
    await expect(page.locator('[data-stat="warranty_count"], .stat-card').nth(1)).toBeVisible();
    await expect(page.locator('[data-stat="file_count"], .stat-card').nth(2)).toBeVisible();
  });

  test.skip('status badge is visible', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    await expect(page.locator('.badge')).toBeVisible();
  });

  test.skip('budget bar is visible when project has a budget', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    // Budget bar / progress element should exist when budget > 0
    await expect(page.locator('[role="progressbar"], .progress, .budget-bar').first()).toBeVisible();
  });

  test.skip('html element has lang=ar and dir=rtl', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  });

  test.skip('status update form present with select[name=status]', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    await expect(page.locator('form select[name="status"]')).toBeVisible();
    await expect(page.locator('form select[name="status"] option[value="active"]')).toHaveCount(1);
    await expect(page.locator('form select[name="status"] option[value="done"]')).toHaveCount(1);
    await expect(page.locator('form select[name="status"] option[value="archived"]')).toHaveCount(1);
  });

  test.skip('CSRF hidden input present in status update form', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    const csrf = page.locator('form input[type="hidden"]').first();
    await expect(csrf).toHaveCount(1);
    // Value should be a non-empty token
    const value = await csrf.inputValue();
    expect(value.trim().length).toBeGreaterThan(0);
  });
});

// ---------------------------------------------------------------------------
// 4. Status update (requires auth + project)
// ---------------------------------------------------------------------------
test.describe('Status update', () => {
  test.skip('changing status to done reloads with ?updated=1 and badge shows مكتمل', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    await page.locator('form select[name="status"]').selectOption('done');
    await page.locator('form[action*="update_status"] [type="submit"], form select[name="status"] ~ [type="submit"]').click();
    await expect(page).toHaveURL(/[?&]updated=1/);
    await expect(page.locator('.badge')).toContainText('مكتمل');
  });

  test.skip('changing status to archived shows badge مؤرشف', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    await page.locator('form select[name="status"]').selectOption('archived');
    await page.locator('form select[name="status"] ~ [type="submit"], form [type="submit"]').first().click();
    await expect(page).toHaveURL(/[?&]updated=1/);
    await expect(page.locator('.badge')).toContainText('مؤرشف');
  });

  test.skip('reverting status back to active shows badge نشط', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    await page.locator('form select[name="status"]').selectOption('active');
    await page.locator('form [type="submit"]').first().click();
    await expect(page).toHaveURL(/[?&]updated=1/);
    await expect(page.locator('.badge')).toContainText('نشط');
  });
});

// ---------------------------------------------------------------------------
// 5. Commercial project — sell_price (requires auth + commercial project)
// ---------------------------------------------------------------------------
test.describe('Commercial project — sell_price', () => {
  test.skip('sell_price input visible for commercial project', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    await expect(page.locator('input[name="sell_price"]')).toBeVisible();
  });

  test.skip('entering sell_price higher than total cost shows positive profit', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    await page.locator('input[name="sell_price"]').fill('999999');
    await page.locator('form [type="submit"]').first().click();
    await expect(page).toHaveURL(/[?&]updated=1/);
    // Profit element should carry a green colour indicator
    const profit = page.locator('[class*="profit"], [data-profit]').first();
    await expect(profit).toBeVisible();
    const cls = await profit.getAttribute('class') ?? '';
    expect(cls).toMatch(/green|success|positive/i);
  });

  test.skip('entering sell_price lower than total cost shows negative profit (red)', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    await page.locator('input[name="sell_price"]').fill('1');
    await page.locator('form [type="submit"]').first().click();
    await expect(page).toHaveURL(/[?&]updated=1/);
    const profit = page.locator('[class*="profit"], [data-profit]').first();
    await expect(profit).toBeVisible();
    const cls = await profit.getAttribute('class') ?? '';
    expect(cls).toMatch(/red|danger|negative/i);
  });

  test.skip('DECIMAL(12,2): sell_price field accepts 1234567890.50', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    const input = page.locator('input[name="sell_price"]');
    await input.fill('1234567890.50');
    await expect(input).toHaveValue('1234567890.50');
    // Submit and verify no validation error
    await page.locator('form [type="submit"]').first().click();
    await expect(page).not.toHaveURL(/error/i);
  });
});

// ---------------------------------------------------------------------------
// 6. Expenses section (requires auth + project)
// ---------------------------------------------------------------------------
test.describe('Expenses section', () => {
  test.skip('expenses table or list is visible', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    await expect(
      page.locator('table, ul.expenses, .expenses-list, [data-section="expenses"]').first()
    ).toBeVisible();
  });

  test.skip('each expense row shows title, amount, vendor, and date', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    const firstRow = page.locator('table tbody tr, .expense-item').first();
    await expect(firstRow).toBeVisible();
    // At minimum four columns / data points present
    const cells = firstRow.locator('td, [data-field]');
    await expect(cells).toHaveCount(await cells.count()); // existence check
    expect(await cells.count()).toBeGreaterThanOrEqual(4);
  });

  test.skip('add expense button is present', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    const addBtn = page.locator('button, a').filter({ hasText: /إضافة|add/i }).first();
    await expect(addBtn).toBeVisible();
  });

  test.skip('expense amount field accepts DECIMAL(12,2) up to 999999999999.99', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    // Open add-expense modal or form
    const addBtn = page.locator('button, a').filter({ hasText: /إضافة|add/i }).first();
    await addBtn.click();
    const amountInput = page.locator('input[name="amount"]').first();
    await expect(amountInput).toBeVisible();
    await amountInput.fill('999999999999.99');
    await expect(amountInput).toHaveValue('999999999999.99');
  });
});

// ---------------------------------------------------------------------------
// 7. Files section (requires auth + project)
// ---------------------------------------------------------------------------
test.describe('Files section', () => {
  test.skip('files section is rendered from embedded JSON', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    await expect(
      page.locator('[data-section="files"], .files-section, #files').first()
    ).toBeVisible();
  });

  test.skip('empty state shown when project has no files', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    // If files list is empty the empty-state placeholder should be visible
    const emptyState = page.locator('[data-empty], .empty-state, .no-files').first();
    await expect(emptyState).toBeVisible();
  });

  test.skip('file download or preview links are present when files exist', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    const fileLink = page.locator('a[download], a[href*="download"], a[href*="files"]').first();
    await expect(fileLink).toBeVisible();
  });
});

// ---------------------------------------------------------------------------
// 8. ?updated=1 flash (requires auth + project)
// ---------------------------------------------------------------------------
test.describe('?updated=1 success flash', () => {
  test.skip('success banner or toast appears when URL contains ?updated=1', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1&updated=1');
    // Toast or banner with a success indicator should be visible
    const flash = page.locator(
      '[role="alert"], .toast, .alert-success, .flash, .banner, [class*="success"]'
    ).first();
    await expect(flash).toBeVisible();
  });

  test.skip('success flash is not shown when ?updated=1 is absent', async ({ page }) => {
    await page.goto('/pages/project-detail.php?id=1');
    const flash = page.locator(
      '[role="alert"].success, .alert-success, .flash-success'
    ).first();
    await expect(flash).not.toBeVisible();
  });
});
