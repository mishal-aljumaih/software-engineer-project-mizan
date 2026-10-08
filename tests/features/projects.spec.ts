import { test, expect } from '@playwright/test';

test.use({ baseURL: 'http://localhost' });

// ---------------------------------------------------------------------------
// 1. Unauthenticated redirect (active — no session required)
// ---------------------------------------------------------------------------

test('unauthenticated user is redirected away from /pages/projects.php', async ({ page }) => {
  const response = await page.goto('/pages/projects.php');
  const finalUrl = page.url();
  const isRedirected =
    finalUrl.includes('/welcome.php') || finalUrl.includes('/login.php');
  expect(isRedirected).toBe(true);
});

// ---------------------------------------------------------------------------
// 2. UI Rendering (requires auth — skipped)
// ---------------------------------------------------------------------------

test.skip('page title contains ميزان', async ({ page }) => {
  await page.goto('/pages/projects.php');
  await expect(page).toHaveTitle(/ميزان/);
});

test.skip('html element has lang=ar and dir=rtl', async ({ page }) => {
  await page.goto('/pages/projects.php');
  const html = page.locator('html');
  await expect(html).toHaveAttribute('lang', 'ar');
  await expect(html).toHaveAttribute('dir', 'rtl');
});

test.skip('h1.page-title is visible with text المشاريع', async ({ page }) => {
  await page.goto('/pages/projects.php');
  const heading = page.locator('h1.page-title');
  await expect(heading).toBeVisible();
  await expect(heading).toHaveText('المشاريع');
});

test.skip('#projectSearch input is visible with correct placeholder', async ({ page }) => {
  await page.goto('/pages/projects.php');
  const input = page.locator('#projectSearch');
  await expect(input).toBeVisible();
  await expect(input).toHaveAttribute('placeholder', 'بحث في المشاريع...');
});

test.skip('#statusFilter select is visible and has 4 options', async ({ page }) => {
  await page.goto('/pages/projects.php');
  const select = page.locator('#statusFilter');
  await expect(select).toBeVisible();
  const options = select.locator('option');
  await expect(options).toHaveCount(4);
  const values = await options.evaluateAll((els: HTMLOptionElement[]) =>
    els.map((el) => el.value)
  );
  expect(values).toEqual(expect.arrayContaining(['all', 'active', 'done', 'archived']));
});

test.skip('ghost button "رفع ملف جديد" is visible', async ({ page }) => {
  await page.goto('/pages/projects.php');
  const btn = page.locator('button.btn-mz-ghost', { hasText: 'رفع ملف جديد' });
  await expect(btn).toBeVisible();
});

test.skip('primary button "مشروع جديد" is visible', async ({ page }) => {
  await page.goto('/pages/projects.php');
  const btn = page.locator('button.btn-mz-primary', { hasText: 'مشروع جديد' });
  await expect(btn).toBeVisible();
});

test.skip('#projectsGrid is visible', async ({ page }) => {
  await page.goto('/pages/projects.php');
  await expect(page.locator('#projectsGrid')).toBeVisible();
});

test.skip('skeleton #skelWrap is shown before AJAX completes', async ({ page }) => {
  // Intercept the API call and stall it so the skeleton stays visible
  await page.route('**/api/projects.php**', async (route) => {
    await new Promise((resolve) => setTimeout(resolve, 3000));
    await route.continue();
  });
  const gotoPromise = page.goto('/pages/projects.php');
  const skel = page.locator('#skelWrap');
  await expect(skel).toBeVisible();
  await gotoPromise;
});

// ---------------------------------------------------------------------------
// 3. Search filter (requires auth + projects in DB — skipped)
// ---------------------------------------------------------------------------

test.skip('typing in #projectSearch filters visible .prj-card elements', async ({ page }) => {
  await page.goto('/pages/projects.php');
  // Wait for real cards to appear (skeleton replaced)
  await page.waitForSelector('.prj-card');
  const allCards = page.locator('.prj-card');
  const totalBefore = await allCards.count();

  // Grab the name of the first card to search for
  const firstName = await allCards.first().locator('[class*="name"], h3, h2, strong').first().innerText();

  await page.fill('#projectSearch', firstName.trim());
  // Allow filterProjects() to run
  await page.waitForTimeout(200);

  const visibleCards = allCards.filter({ has: page.locator(':visible') });
  const visibleCount = await visibleCards.count();
  expect(visibleCount).toBeGreaterThanOrEqual(1);
  if (totalBefore > 1) {
    expect(visibleCount).toBeLessThan(totalBefore);
  }
});

test.skip('clearing #projectSearch restores all cards', async ({ page }) => {
  await page.goto('/pages/projects.php');
  await page.waitForSelector('.prj-card');
  const allCards = page.locator('.prj-card');
  const totalBefore = await allCards.count();

  await page.fill('#projectSearch', 'xyznonexistent');
  await page.waitForTimeout(200);

  await page.fill('#projectSearch', '');
  await page.waitForTimeout(200);

  // All cards should be visible again
  for (let i = 0; i < totalBefore; i++) {
    await expect(allCards.nth(i)).toBeVisible();
  }
});

test.skip('search is case-insensitive', async ({ page }) => {
  await page.goto('/pages/projects.php');
  await page.waitForSelector('.prj-card');
  const allCards = page.locator('.prj-card');

  const firstName = await allCards.first().locator('[class*="name"], h3, h2, strong').first().innerText();
  const upperQuery = firstName.trim().toUpperCase();

  await page.fill('#projectSearch', upperQuery);
  await page.waitForTimeout(200);

  const visibleAfter = allCards.filter({ has: page.locator(':visible') });
  await expect(visibleAfter.first()).toBeVisible();
});

// ---------------------------------------------------------------------------
// 4. Status filter (requires auth + projects — skipped)
// ---------------------------------------------------------------------------

test.skip('selecting "active" in #statusFilter hides non-active cards', async ({ page }) => {
  await page.goto('/pages/projects.php');
  await page.waitForSelector('.prj-card');

  await page.selectOption('#statusFilter', 'active');
  await page.waitForTimeout(200);

  const hiddenNonActive = await page.evaluate(() => {
    const cards = Array.from(document.querySelectorAll('.prj-card')) as HTMLElement[];
    return cards.every((card) => {
      const isHidden = card.style.display === 'none' || card.hidden;
      const status = card.dataset.status ?? card.getAttribute('data-status') ?? '';
      return isHidden ? status !== 'active' : status === 'active';
    });
  });
  expect(hiddenNonActive).toBe(true);
});

test.skip('selecting "all" in #statusFilter shows all cards', async ({ page }) => {
  await page.goto('/pages/projects.php');
  await page.waitForSelector('.prj-card');

  await page.selectOption('#statusFilter', 'active');
  await page.waitForTimeout(200);
  await page.selectOption('#statusFilter', 'all');
  await page.waitForTimeout(200);

  const allCards = page.locator('.prj-card');
  const count = await allCards.count();
  for (let i = 0; i < count; i++) {
    await expect(allCards.nth(i)).toBeVisible();
  }
});

// ---------------------------------------------------------------------------
// 5. Project creation modal (requires auth — skipped)
// ---------------------------------------------------------------------------

test.skip('clicking "مشروع جديد" button opens a modal or form', async ({ page }) => {
  await page.goto('/pages/projects.php');
  await page.click('button.btn-mz-primary');
  // A modal or at least a form should appear
  const modal = page.locator('[role="dialog"], .modal, form').first();
  await expect(modal).toBeVisible();
});

test.skip('project creation modal contains name, type, budget, and color fields', async ({ page }) => {
  await page.goto('/pages/projects.php');
  await page.click('button.btn-mz-primary');

  // Name field
  const nameInput = page.locator('input[name="name"], input[placeholder*="اسم"], #projectName').first();
  await expect(nameInput).toBeVisible();

  // Type selector — expects options: car, house, occasion, work, devices, custom
  const typeSelect = page.locator('select[name="type"], #projectType').first();
  await expect(typeSelect).toBeVisible();
  const typeOptions = await typeSelect.locator('option').evaluateAll((els: HTMLOptionElement[]) =>
    els.map((el) => el.value)
  );
  expect(typeOptions).toEqual(
    expect.arrayContaining(['car', 'house', 'occasion', 'work', 'devices', 'custom'])
  );

  // Budget input
  const budgetInput = page.locator('input[name="budget"], input[type="number"], #projectBudget').first();
  await expect(budgetInput).toBeVisible();

  // Color picker
  const colorPicker = page.locator('input[type="color"], .color-picker, [data-role="color"]').first();
  await expect(colorPicker).toBeVisible();
});

test.skip('submitting the creation form with an empty name shows a validation error', async ({ page }) => {
  await page.goto('/pages/projects.php');
  await page.click('button.btn-mz-primary');

  // Locate and click submit without filling name
  const submitBtn = page
    .locator('button[type="submit"], input[type="submit"], button', { hasText: /حفظ|إنشاء|إضافة/ })
    .first();
  await submitBtn.click();

  // Native HTML5 validation or custom error message
  const nameInput = page.locator('input[name="name"], #projectName').first();
  const validationMessage = await nameInput.evaluate((el: HTMLInputElement) => el.validationMessage);
  const hasError =
    validationMessage.length > 0 ||
    (await page.locator('.error, .alert, [role="alert"]').count()) > 0;
  expect(hasError).toBe(true);
});

test.skip('submitting valid project data creates a project that appears in the grid', async ({ page }) => {
  await page.goto('/pages/projects.php');
  const initialCount = await page.locator('.prj-card').count();

  await page.click('button.btn-mz-primary');

  await page.fill('input[name="name"], #projectName', 'مشروع اختباري Playwright');
  await page.selectOption('select[name="type"], #projectType', 'work');
  await page.fill('input[name="budget"], #projectBudget', '50000');

  const submitBtn = page
    .locator('button[type="submit"], input[type="submit"], button', { hasText: /حفظ|إنشاء|إضافة/ })
    .first();
  await submitBtn.click();

  // Modal should close and new card should appear
  await page.waitForTimeout(1000);
  const newCount = await page.locator('.prj-card').count();
  expect(newCount).toBeGreaterThan(initialCount);
});

test.skip('budget field accepts max DECIMAL(12,2) value 999999999999.99', async ({ page }) => {
  await page.goto('/pages/projects.php');
  await page.click('button.btn-mz-primary');

  const budgetInput = page.locator('input[name="budget"], #projectBudget').first();
  await budgetInput.fill('999999999999.99');
  const value = await budgetInput.inputValue();
  expect(value).toBe('999999999999.99');
});

// ---------------------------------------------------------------------------
// 6. Smart upload modal (requires auth — skipped)
// ---------------------------------------------------------------------------

test.skip('clicking "رفع ملف جديد" opens the upload modal', async ({ page }) => {
  await page.goto('/pages/projects.php');
  await page.click('button.btn-mz-ghost');
  const uploadModal = page.locator('[role="dialog"], .modal, .upload-modal').first();
  await expect(uploadModal).toBeVisible();
});

test.skip('upload modal has a file input (note: 500 MB limit is server-side)', async ({ page }) => {
  await page.goto('/pages/projects.php');
  await page.click('button.btn-mz-ghost');

  // A file input should be present; 500 MB enforcement is server-side
  const fileInput = page.locator('input[type="file"]').first();
  await expect(fileInput).toBeAttached();
  // If an accept attribute is declared, note it — but do not assert a specific value
  // as it is determined by the server configuration
});

// ---------------------------------------------------------------------------
// 7. Project card actions (requires auth + projects — skipped)
// ---------------------------------------------------------------------------

test.skip('edit button on a project card opens the edit modal', async ({ page }) => {
  await page.goto('/pages/projects.php');
  await page.waitForSelector('.prj-card');

  const editBtn = page.locator('.prj-card').first().locator('button[onclick*="edit"], .btn-edit, [data-action="edit"]').first();
  await editBtn.click();

  const modal = page.locator('[role="dialog"], .modal').first();
  await expect(modal).toBeVisible();
});

test.skip('delete button shows confirmation and removes card on confirm', async ({ page }) => {
  await page.goto('/pages/projects.php');
  await page.waitForSelector('.prj-card');
  const initialCount = await page.locator('.prj-card').count();

  const deleteBtn = page
    .locator('.prj-card')
    .first()
    .locator('button[onclick*="delete"], .btn-delete, [data-action="delete"]')
    .first();
  await deleteBtn.click();

  // Accept a browser confirm dialog if used
  page.once('dialog', (dialog) => dialog.accept());

  // Or click a custom confirm button in a modal
  const confirmBtn = page.locator('button', { hasText: /تأكيد|حذف|نعم/ }).last();
  if (await confirmBtn.isVisible()) {
    await confirmBtn.click();
  }

  await page.waitForTimeout(800);
  const newCount = await page.locator('.prj-card').count();
  expect(newCount).toBeLessThan(initialCount);
});

test.skip('clicking a project card navigates to /pages/project-detail.php with an id param', async ({ page }) => {
  await page.goto('/pages/projects.php');
  await page.waitForSelector('.prj-card');

  const firstCard = page.locator('.prj-card').first();
  // Cards may be wrapped in an anchor or have an onclick — click the card body
  await firstCard.click();

  await expect(page).toHaveURL(/\/pages\/project-detail\.php\?id=\d+/);
});
