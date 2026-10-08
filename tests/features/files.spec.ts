import { test, expect } from '@playwright/test';

test.use({ baseURL: 'http://localhost' });

// ---------------------------------------------------------------------------
// 1. Unauthenticated redirect (active — no session required)
// ---------------------------------------------------------------------------
test.describe('Unauthenticated redirect', () => {
  test('GET /pages/files.php redirects to /welcome.php when not logged in', async ({ page }) => {
    await page.goto('/pages/files.php', { waitUntil: 'load' });
    expect(page.url()).toMatch(/\/welcome\.php/);
  });
});

// ---------------------------------------------------------------------------
// All remaining tests require an authenticated session — skipped in CI
// ---------------------------------------------------------------------------
test.describe('UI Rendering', () => {
  test.skip(true, 'Requires authenticated session');

  test('page title contains "ميزان" or "Vault"', async ({ page }) => {
    await page.goto('/pages/files.php');
    const title = await page.title();
    expect(title).toMatch(/ميزان|Vault/);
  });

  test('page heading (h1) is visible', async ({ page }) => {
    await page.goto('/pages/files.php');
    await expect(page.locator('h1').first()).toBeVisible();
  });

  test('search input is visible', async ({ page }) => {
    await page.goto('/pages/files.php');
    await expect(page.locator('input[type="text"], input[type="search"]').first()).toBeVisible();
  });

  test('tab buttons for all / invoice / warranty / contract / other are visible', async ({ page }) => {
    await page.goto('/pages/files.php');
    for (const label of ['all', 'invoice', 'warranty', 'contract', 'other']) {
      await expect(
        page.locator(`[data-tab="${label}"], button:has-text("${label}"), a:has-text("${label}")`)
      ).toBeVisible();
    }
  });

  test('sort select is visible with expected options', async ({ page }) => {
    await page.goto('/pages/files.php');
    const sortSelect = page.locator('select').filter({ hasText: /newest|oldest|size|name/i }).first();
    await expect(sortSelect).toBeVisible();
    for (const value of ['newest', 'oldest', 'size', 'name']) {
      await expect(sortSelect.locator(`option[value="${value}"]`)).toHaveCount(1);
    }
  });

  test('project filter select is visible', async ({ page }) => {
    await page.goto('/pages/files.php');
    // The project select should be a <select> distinct from the sort select
    const selects = page.locator('select');
    expect(await selects.count()).toBeGreaterThanOrEqual(2);
  });

  test('html element has lang=ar and dir=rtl', async ({ page }) => {
    await page.goto('/pages/files.php');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  });
});

// ---------------------------------------------------------------------------
// 3. Tab filtering
// ---------------------------------------------------------------------------
test.describe('Tab filtering', () => {
  test.skip(true, 'Requires authenticated session');

  test('clicking "invoice" tab updates URL with tab=invoice', async ({ page }) => {
    await page.goto('/pages/files.php');
    await page.locator('[data-tab="invoice"], button:has-text("invoice"), a:has-text("invoice")').first().click();
    await page.waitForURL(/tab=invoice/);
    expect(page.url()).toContain('tab=invoice');
  });

  test('clicking "warranty" tab updates URL with tab=warranty', async ({ page }) => {
    await page.goto('/pages/files.php');
    await page.locator('[data-tab="warranty"], button:has-text("warranty"), a:has-text("warranty")').first().click();
    await page.waitForURL(/tab=warranty/);
    expect(page.url()).toContain('tab=warranty');
  });

  test('clicking "all" tab produces tab=all or removes tab param', async ({ page }) => {
    await page.goto('/pages/files.php?tab=invoice');
    await page.locator('[data-tab="all"], button:has-text("all"), a:has-text("all")').first().click();
    await page.waitForURL(/.*/);
    const url = page.url();
    // Either tab=all is explicit or the param is absent entirely
    expect(url.includes('tab=all') || !url.includes('tab=')).toBeTruthy();
  });
});

// ---------------------------------------------------------------------------
// 4. Search filtering
// ---------------------------------------------------------------------------
test.describe('Search filtering', () => {
  test.skip(true, 'Requires authenticated session');

  test('entering a query updates URL with ?q= param', async ({ page }) => {
    await page.goto('/pages/files.php');
    const searchInput = page.locator('input[type="text"], input[type="search"]').first();
    await searchInput.fill('invoice');
    await searchInput.press('Enter');
    await page.waitForURL(/q=invoice/);
    expect(page.url()).toContain('q=invoice');
  });

  test('clearing search shows all files (removes q param)', async ({ page }) => {
    await page.goto('/pages/files.php?q=invoice');
    const searchInput = page.locator('input[type="text"], input[type="search"]').first();
    await searchInput.clear();
    await searchInput.press('Enter');
    await page.waitForURL(/.*/);
    expect(page.url()).not.toContain('q=');
  });
});

// ---------------------------------------------------------------------------
// 5. Sort
// ---------------------------------------------------------------------------
test.describe('Sort', () => {
  test.skip(true, 'Requires authenticated session');

  test('changing sort to "oldest" updates URL with ?sort=oldest', async ({ page }) => {
    await page.goto('/pages/files.php');
    const sortSelect = page.locator('select').filter({ hasText: /newest|oldest|size|name/i }).first();
    await sortSelect.selectOption('oldest');
    await page.waitForURL(/sort=oldest/);
    expect(page.url()).toContain('sort=oldest');
  });

  test('changing sort to "size" updates URL with ?sort=size', async ({ page }) => {
    await page.goto('/pages/files.php');
    const sortSelect = page.locator('select').filter({ hasText: /newest|oldest|size|name/i }).first();
    await sortSelect.selectOption('size');
    await page.waitForURL(/sort=size/);
    expect(page.url()).toContain('sort=size');
  });
});

// ---------------------------------------------------------------------------
// 6. Price range filter
// ---------------------------------------------------------------------------
test.describe('Price range filter', () => {
  test.skip(true, 'Requires authenticated session');

  test('setting min=100 and max=500 updates URL with those params', async ({ page }) => {
    await page.goto('/pages/files.php');
    const minInput = page.locator('input[name="min"], input[placeholder*="min" i]').first();
    const maxInput = page.locator('input[name="max"], input[placeholder*="max" i]').first();
    await minInput.fill('100');
    await maxInput.fill('500');
    await maxInput.press('Enter');
    await page.waitForURL(/min=100/);
    expect(page.url()).toContain('min=100');
    expect(page.url()).toContain('max=500');
  });

  test('DECIMAL(12,2): max input accepts 999999999999.99', async ({ page }) => {
    await page.goto('/pages/files.php');
    const maxInput = page.locator('input[name="max"], input[placeholder*="max" i]').first();
    await maxInput.fill('999999999999.99');
    // Input should hold the full value without truncation
    await expect(maxInput).toHaveValue('999999999999.99');
  });
});

// ---------------------------------------------------------------------------
// 7. Project filter
// ---------------------------------------------------------------------------
test.describe('Project filter', () => {
  test.skip(true, 'Requires authenticated session');

  test('selecting a project updates URL with ?project=N', async ({ page }) => {
    await page.goto('/pages/files.php');
    const projectSelect = page.locator('select').filter({ hasText: /project|مشروع/i }).first();
    const options = await projectSelect.locator('option').all();
    // Find first non-empty (non-"all") option
    const nonEmptyOption = options.find(async (o) => {
      const val = await o.getAttribute('value');
      return val && val !== '' && val !== '0';
    });
    if (nonEmptyOption) {
      const value = await nonEmptyOption.getAttribute('value');
      await projectSelect.selectOption(value!);
      await page.waitForURL(new RegExp(`project=${value}`));
      expect(page.url()).toContain(`project=${value}`);
    }
  });
});

// ---------------------------------------------------------------------------
// 8. File actions
// ---------------------------------------------------------------------------
test.describe('File actions', () => {
  test.skip(true, 'Requires authenticated session');

  test('each file card has a download or view link', async ({ page }) => {
    await page.goto('/pages/files.php');
    const cards = page.locator('.file-card, .vault-card, [data-file-id]');
    const count = await cards.count();
    if (count === 0) {
      test.info().annotations.push({ type: 'note', description: 'No file cards present — skipping assertion' });
      return;
    }
    const firstCard = cards.first();
    const downloadLink = firstCard.locator('a[href*="download"], a[download], a[href*="uploads/"]');
    await expect(downloadLink.first()).toBeVisible();
  });

  test('each file card has a delete button', async ({ page }) => {
    await page.goto('/pages/files.php');
    const cards = page.locator('.file-card, .vault-card, [data-file-id]');
    const count = await cards.count();
    if (count === 0) {
      test.info().annotations.push({ type: 'note', description: 'No file cards present — skipping assertion' });
      return;
    }
    const deleteBtn = cards.first().locator('button[data-action="delete"], button.delete-btn, button:has-text("delete"), button:has-text("حذف")');
    await expect(deleteBtn.first()).toBeVisible();
  });

  test('clicking delete triggers a confirmation prompt', async ({ page }) => {
    await page.goto('/pages/files.php');
    const cards = page.locator('.file-card, .vault-card, [data-file-id]');
    if (await cards.count() === 0) return;

    let dialogSeen = false;
    page.once('dialog', async (dialog) => {
      dialogSeen = true;
      await dialog.dismiss(); // dismiss so nothing is actually deleted
    });

    const deleteBtn = cards.first().locator('button[data-action="delete"], button.delete-btn, button:has-text("delete"), button:has-text("حذف")');
    await deleteBtn.first().click();
    // Allow time for dialog / modal to appear
    await page.waitForTimeout(500);
    expect(dialogSeen).toBeTruthy();
  });
});

// ---------------------------------------------------------------------------
// 9. Smart upload modal
// ---------------------------------------------------------------------------
test.describe('Smart upload', () => {
  test.skip(true, 'Requires authenticated session');

  test('upload button triggers openSmartUpload modal', async ({ page }) => {
    await page.goto('/pages/files.php');
    const uploadTrigger = page.locator(
      'button:has-text("upload"), a:has-text("upload"), [onclick*="openSmartUpload"], button:has-text("رفع"), button:has-text("إضافة")'
    ).first();
    await expect(uploadTrigger).toBeVisible();
    await uploadTrigger.click();
    // Modal should become visible
    const modal = page.locator('.modal, dialog, [role="dialog"]').first();
    await expect(modal).toBeVisible();
  });

  test('upload modal file input accepts image and PDF MIME types', async ({ page }) => {
    await page.goto('/pages/files.php');
    const uploadTrigger = page.locator(
      'button:has-text("upload"), a:has-text("upload"), [onclick*="openSmartUpload"], button:has-text("رفع"), button:has-text("إضافة")'
    ).first();
    await uploadTrigger.click();
    await page.waitForSelector('.modal input[type="file"], dialog input[type="file"]');
    const fileInput = page.locator('.modal input[type="file"], dialog input[type="file"]').first();
    const accept = await fileInput.getAttribute('accept');
    // Should accept images and PDF
    expect(accept).toMatch(/image|jpg|png|webp|pdf/i);
  });
});

// ---------------------------------------------------------------------------
// 10. Empty state
// ---------------------------------------------------------------------------
test.describe('Empty state', () => {
  test.skip(true, 'Requires authenticated session');

  test('when no files match filters, an empty-state element with a message is visible', async ({ page }) => {
    // Use a highly unlikely search term to force empty results
    await page.goto('/pages/files.php?q=XYZZY_NO_MATCH_12345&tab=contract');
    const emptyState = page.locator('.empty-state, [class*="empty"], [class*="no-files"], [class*="no-results"]').first();
    await expect(emptyState).toBeVisible();
    const text = await emptyState.textContent();
    expect(text?.trim().length).toBeGreaterThan(0);
  });
});
