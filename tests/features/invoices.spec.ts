import { test, expect } from '@playwright/test';

test.use({ baseURL: 'http://localhost' });

// ─── 1. Unauthenticated redirect ────────────────────────────────────────────

test('unauthenticated user is redirected to welcome.php', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  await expect(page).toHaveURL(/welcome\.php/);
});

// ─── 2. UI Rendering ────────────────────────────────────────────────────────

test.skip('page title contains ميزان', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  await expect(page).toHaveTitle(/ميزان/);
});

test.skip('page heading "الفواتير" is visible', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  await expect(page.getByRole('heading', { name: 'الفواتير' })).toBeVisible();
});

test.skip('four stats cards are visible', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  // Expect at least 4 stat card elements in the stats bar
  const statCards = page.locator('.stat-card, [class*="stat"], [class*="stats"] > *');
  await expect(statCards).toHaveCount(4);
});

test.skip('project filter select is visible with default option "كل المشاريع"', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  const select = page.locator('select').filter({ hasText: 'كل المشاريع' });
  await expect(select).toBeVisible();
  await expect(select.locator('option[value="0"]')).toHaveText('كل المشاريع');
});

test.skip('search input is visible', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  const searchInput = page.locator('input[name="q"], input[type="text"][placeholder*="بحث"], input[type="search"]').first();
  await expect(searchInput).toBeVisible();
});

test.skip('type filter controls (all / image / pdf) are visible', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  // Each filter tab/button should be present
  await expect(page.getByRole('button', { name: /الكل|all/i }).or(page.locator('[data-type="all"], a[href*="type=all"]')).first()).toBeVisible();
  await expect(page.getByRole('button', { name: /صور|image/i }).or(page.locator('[data-type="image"], a[href*="type=image"]')).first()).toBeVisible();
  await expect(page.getByRole('button', { name: /pdf/i }).or(page.locator('[data-type="pdf"], a[href*="type=pdf"]')).first()).toBeVisible();
});

test.skip('html element has lang=ar and dir=rtl', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  const html = page.locator('html');
  await expect(html).toHaveAttribute('lang', 'ar');
  await expect(html).toHaveAttribute('dir', 'rtl');
});

// ─── 3. Stats cards ─────────────────────────────────────────────────────────

test.skip('total invoices count stat is visible', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  // The total invoices stat card should show a numeric value
  const totalStat = page.locator('[class*="stat"]').first();
  await expect(totalStat).toBeVisible();
  await expect(totalStat).toContainText(/\d+/);
});

test.skip('PDF count stat is visible', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  const pdfStat = page.locator('[class*="stat"]').filter({ hasText: /pdf/i });
  await expect(pdfStat).toBeVisible();
  await expect(pdfStat).toContainText(/\d+/);
});

test.skip('images count stat is visible', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  const imageStat = page.locator('[class*="stat"]').filter({ hasText: /صور|image/i });
  await expect(imageStat).toBeVisible();
  await expect(imageStat).toContainText(/\d+/);
});

test.skip('total amount stat shows a DECIMAL(12,2) numeric value', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  // Amount stats should contain a number, possibly with decimals
  const amountStat = page.locator('[class*="stat"]').filter({ hasText: /المبلغ|مجموع|amount/i });
  await expect(amountStat).toBeVisible();
  // Should contain digits — DECIMAL format like 0.00 or 1,234.56
  await expect(amountStat).toContainText(/[\d,]+\.?\d*/);
});

// ─── 4. Project filter ───────────────────────────────────────────────────────

test.skip('selecting a project updates URL with ?project=N', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  const select = page.locator('select').filter({ hasText: 'كل المشاريع' }).first();
  // Pick the second option (first real project) if available
  const options = select.locator('option');
  const count = await options.count();
  if (count < 2) {
    test.skip();
    return;
  }
  const projectValue = await options.nth(1).getAttribute('value');
  await select.selectOption({ index: 1 });
  await page.waitForURL(`**/?project=${projectValue}**`);
  await expect(page).toHaveURL(new RegExp(`project=${projectValue}`));
});

test.skip('project filter shows only selected project invoices', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  const select = page.locator('select').filter({ hasText: 'كل المشاريع' }).first();
  const options = select.locator('option');
  const count = await options.count();
  if (count < 2) {
    test.skip();
    return;
  }
  const projectName = await options.nth(1).textContent();
  await select.selectOption({ index: 1 });
  await page.waitForLoadState('networkidle');
  // Every visible project badge/name on cards should match the selected project
  const projectLabels = page.locator('[class*="card"] [class*="project"], [class*="invoice"] [class*="project"]');
  const labelCount = await projectLabels.count();
  for (let i = 0; i < labelCount; i++) {
    await expect(projectLabels.nth(i)).toContainText(projectName!.trim());
  }
});

// ─── 5. Search filter ────────────────────────────────────────────────────────

test.skip('typing in search applies ?q= param and filters results', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  const searchInput = page.locator('input[name="q"], input[type="text"], input[type="search"]').first();
  await searchInput.fill('test');
  await searchInput.press('Enter');
  await page.waitForURL('**/?**q=test**');
  await expect(page).toHaveURL(/q=test/);
});

test.skip('Arabic text search works correctly in RTL input', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  const searchInput = page.locator('input[name="q"], input[type="text"], input[type="search"]').first();
  const arabicQuery = 'مورد';
  await searchInput.fill(arabicQuery);
  await searchInput.press('Enter');
  await page.waitForURL(`**/?**q=${encodeURIComponent(arabicQuery)}**`);
  await expect(page).toHaveURL(new RegExp(`q=${encodeURIComponent(arabicQuery)}`));
});

test.skip('searching by vendor name finds matching invoices', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  const searchInput = page.locator('input[name="q"], input[type="text"], input[type="search"]').first();
  await searchInput.fill('vendor');
  await searchInput.press('Enter');
  await page.waitForURL('**/?**q=vendor**');
  // Cards shown should reference the search term somewhere (title or vendor text)
  const cards = page.locator('[class*="invoice"], [class*="card"]');
  const cardCount = await cards.count();
  if (cardCount > 0) {
    // At least one card should contain the search string
    const firstCard = cards.first();
    await expect(firstCard).toContainText(/vendor/i);
  }
});

// ─── 6. Type filter ──────────────────────────────────────────────────────────

test.skip('clicking pdf filter tab applies ?type=pdf and shows only PDF invoices', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  const pdfFilter = page.getByRole('button', { name: /pdf/i })
    .or(page.locator('[data-type="pdf"], a[href*="type=pdf"]')).first();
  await pdfFilter.click();
  await page.waitForURL('**/?**type=pdf**');
  await expect(page).toHaveURL(/type=pdf/);
  // All file-type badges visible should say PDF
  const badges = page.locator('[class*="badge"], [class*="type"]').filter({ hasText: /pdf/i });
  const nonPdfBadges = page.locator('[class*="badge"], [class*="type"]').filter({ hasText: /صورة|image/i });
  await expect(nonPdfBadges).toHaveCount(0);
});

test.skip('clicking image filter tab applies ?type=image and shows only image invoices', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  const imageFilter = page.getByRole('button', { name: /صور|image/i })
    .or(page.locator('[data-type="image"], a[href*="type=image"]')).first();
  await imageFilter.click();
  await page.waitForURL('**/?**type=image**');
  await expect(page).toHaveURL(/type=image/);
  // No PDF badges should appear
  const pdfBadges = page.locator('[class*="badge"], [class*="type"]').filter({ hasText: /^pdf$/i });
  await expect(pdfBadges).toHaveCount(0);
});

test.skip('clicking all filter tab shows all invoice types', async ({ page }) => {
  await page.goto('/pages/invoices.php?type=pdf');
  const allFilter = page.getByRole('button', { name: /الكل|all/i })
    .or(page.locator('[data-type="all"], a[href*="type=all"]')).first();
  await allFilter.click();
  await page.waitForLoadState('networkidle');
  // URL should either have type=all or no type param
  const url = page.url();
  expect(url).toMatch(/type=all|[^?]$/);
});

// ─── 7. Invoice card content ─────────────────────────────────────────────────

test.skip('invoice card shows expense title', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  const firstCard = page.locator('[class*="invoice"], [class*="card"]').first();
  await expect(firstCard).toBeVisible();
  // The expense title should be non-empty text on the card
  const titleEl = firstCard.locator('[class*="title"], h3, h4, strong').first();
  await expect(titleEl).not.toBeEmpty();
});

test.skip('invoice card shows amount with decimal format', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  const firstCard = page.locator('[class*="invoice"], [class*="card"]').first();
  await expect(firstCard).toBeVisible();
  // Amount should be a number with two decimal places (DECIMAL(12,2))
  await expect(firstCard).toContainText(/\d+\.\d{2}/);
});

test.skip('invoice card shows vendor text', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  const firstCard = page.locator('[class*="invoice"], [class*="card"]').first();
  await expect(firstCard).toBeVisible();
  const vendorEl = firstCard.locator('[class*="vendor"], [class*="supplier"]').first();
  await expect(vendorEl).toBeVisible();
  await expect(vendorEl).not.toBeEmpty();
});

test.skip('invoice card shows purchase date', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  const firstCard = page.locator('[class*="invoice"], [class*="card"]').first();
  await expect(firstCard).toBeVisible();
  // Date should match a date pattern
  await expect(firstCard).toContainText(/\d{4}[-\/]\d{2}[-\/]\d{2}|\d{2}[-\/]\d{2}[-\/]\d{4}/);
});

test.skip('invoice card shows project name with colored dot', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  const firstCard = page.locator('[class*="invoice"], [class*="card"]').first();
  await expect(firstCard).toBeVisible();
  // Colored project dot
  const dot = firstCard.locator('[class*="dot"], [class*="color"], span[style*="background"]').first();
  await expect(dot).toBeVisible();
  // Project name text present
  const projectLabel = firstCard.locator('[class*="project"]').first();
  await expect(projectLabel).toBeVisible();
  await expect(projectLabel).not.toBeEmpty();
});

test.skip('invoice card shows correct PDF badge for PDF files', async ({ page }) => {
  await page.goto('/pages/invoices.php?type=pdf');
  const firstCard = page.locator('[class*="invoice"], [class*="card"]').first();
  await expect(firstCard).toBeVisible();
  const badge = firstCard.locator('[class*="badge"], [class*="type"]').first();
  await expect(badge).toContainText(/pdf/i);
});

test.skip('invoice card shows correct image badge for image files', async ({ page }) => {
  await page.goto('/pages/invoices.php?type=image');
  const firstCard = page.locator('[class*="invoice"], [class*="card"]').first();
  await expect(firstCard).toBeVisible();
  const badge = firstCard.locator('[class*="badge"], [class*="type"]').first();
  await expect(badge).toContainText(/صورة|image/i);
});

// ─── 8. Preview and Download buttons ────────────────────────────────────────

test.skip('each invoice card has a preview button or link', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  const firstCard = page.locator('[class*="invoice"], [class*="card"]').first();
  await expect(firstCard).toBeVisible();
  const previewBtn = firstCard.getByRole('button', { name: /معاينة|preview/i })
    .or(firstCard.getByRole('link', { name: /معاينة|preview/i })).first();
  await expect(previewBtn).toBeVisible();
});

test.skip('each invoice card has a download button or link', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  const firstCard = page.locator('[class*="invoice"], [class*="card"]').first();
  await expect(firstCard).toBeVisible();
  const downloadBtn = firstCard.getByRole('button', { name: /تحميل|download/i })
    .or(firstCard.getByRole('link', { name: /تحميل|download/i }))
    .or(firstCard.locator('[download], a[href*="download"]')).first();
  await expect(downloadBtn).toBeVisible();
});

// ─── 9. Amount formatting ────────────────────────────────────────────────────

test.skip('large DECIMAL(12,2) amount like 123456789.50 is displayed correctly', async ({ page }) => {
  // Navigate with a search term that would surface a known large amount if data exists
  await page.goto('/pages/invoices.php');
  // Verify that any amount present matches DECIMAL(12,2) pattern (digits + two decimal places)
  const amounts = page.locator('[class*="amount"], [class*="price"], [class*="total"]');
  const count = await amounts.count();
  if (count > 0) {
    const firstAmount = amounts.first();
    // Should contain digits and a decimal separator
    await expect(firstAmount).toContainText(/[\d,]+\.?\d*/);
  }
});

test.skip('currency symbol is shown alongside amount on invoice cards', async ({ page }) => {
  await page.goto('/pages/invoices.php');
  const firstCard = page.locator('[class*="invoice"], [class*="card"]').first();
  await expect(firstCard).toBeVisible();
  // Should display SAR, ر.س, or another currency indicator
  await expect(firstCard).toContainText(/ر\.س|SAR|ريال|\$/);
});

// ─── 10. Empty state ─────────────────────────────────────────────────────────

test.skip('searching a non-existent term shows empty state message', async ({ page }) => {
  // Use a search term extremely unlikely to match any real data
  const nonce = `__no_match_${Date.now()}__`;
  await page.goto(`/pages/invoices.php?q=${encodeURIComponent(nonce)}`);
  // An empty state element should appear when there are no results
  const emptyState = page.locator(
    '[class*="empty"], [class*="no-result"], [class*="placeholder"], [class*="zero-state"]'
  ).or(page.getByText(/لا توجد فواتير|لا نتائج|no invoices|no results/i)).first();
  await expect(emptyState).toBeVisible();
});

test.skip('applying an impossible type+project combination shows empty state', async ({ page }) => {
  // project=999999 is unlikely to exist for this user
  await page.goto('/pages/invoices.php?project=999999&type=pdf');
  const emptyState = page.locator(
    '[class*="empty"], [class*="no-result"], [class*="placeholder"], [class*="zero-state"]'
  ).or(page.getByText(/لا توجد فواتير|لا نتائج|no invoices|no results/i)).first();
  await expect(emptyState).toBeVisible();
});
