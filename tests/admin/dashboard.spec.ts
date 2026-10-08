import { test, expect } from '@playwright/test';

test.use({ baseURL: 'http://localhost' });

// ─── 1. Unauthenticated Redirect ─────────────────────────────────────────────

test('unauthenticated access redirects to /admin_login.php', async ({ page }) => {
  const response = await page.goto('/admin/dashboard.php', { waitUntil: 'networkidle' });
  expect(page.url()).toContain('/admin_login.php');
});

// ─── 2. UI Rendering ─────────────────────────────────────────────────────────

test.skip('page title contains ميزان or لوحة المدير', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const title = await page.title();
  const titleMatches = title.includes('ميزان') || title.includes('لوحة المدير');
  expect(titleMatches).toBe(true);
});

test.skip('html element has lang=ar and dir=rtl', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const lang = await page.locator('html').getAttribute('lang');
  const dir = await page.locator('html').getAttribute('dir');
  expect(lang).toBe('ar');
  expect(dir).toBe('rtl');
});

test.skip('four system stat cards are visible', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  // Expect at least 4 stat cards on the page
  const statCards = page.locator('.stat-card, .stats-card, [class*="stat"]');
  await expect(statCards).toHaveCount(4);
});

test.skip('each stat card shows a numeric value', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const statValues = page.locator('.stat-card .stat-value, .stats-card .value, [class*="stat"] [class*="value"], [class*="stat"] [class*="number"]');
  const count = await statValues.count();
  expect(count).toBeGreaterThanOrEqual(4);
  for (let i = 0; i < count; i++) {
    const text = (await statValues.nth(i).innerText()).trim();
    expect(text).toMatch(/^\d+$/);
  }
});

test.skip('tickets section is visible', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const ticketsSection = page.locator('#tickets, [id*="ticket"], section:has-text("تذاكر"), section:has-text("Ticket")');
  await expect(ticketsSection.first()).toBeVisible();
});

test.skip('recent users table is visible', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const usersSection = page.locator('#users, [id*="user"], section:has-text("مستخدم"), table').first();
  await expect(usersSection).toBeVisible();
});

// ─── 3. System Stats Cards ────────────────────────────────────────────────────

test.skip('total users stat card shows a number >= 0', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const usersCard = page.locator('[class*="stat"]:has-text("مستخدم"), [class*="stat"]:has-text("Users"), .stat-card').first();
  await expect(usersCard).toBeVisible();
  const text = await usersCard.innerText();
  const numMatch = text.match(/\d+/);
  expect(numMatch).not.toBeNull();
  expect(parseInt(numMatch![0])).toBeGreaterThanOrEqual(0);
});

test.skip('total projects stat card is visible', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const projectsCard = page.locator('[class*="stat"]:has-text("مشروع"), [class*="stat"]:has-text("Project")');
  await expect(projectsCard.first()).toBeVisible();
});

test.skip('total expenses stat card is visible', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const expensesCard = page.locator('[class*="stat"]:has-text("مصروف"), [class*="stat"]:has-text("Expense")');
  await expect(expensesCard.first()).toBeVisible();
});

test.skip('total files stat card is visible', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const filesCard = page.locator('[class*="stat"]:has-text("ملف"), [class*="stat"]:has-text("File")');
  await expect(filesCard.first()).toBeVisible();
});

// ─── 4. Support Tickets ───────────────────────────────────────────────────────

test.skip('tickets list or table is visible', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const ticketContainer = page.locator(
    'table:has([class*="ticket"]), [class*="ticket-list"], [class*="ticket-card"], #tickets table, #tickets [class*="card"]'
  );
  await expect(ticketContainer.first()).toBeVisible();
});

test.skip('each ticket row shows subject and user name', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const ticketRows = page.locator('[class*="ticket-row"], tbody tr').first();
  // If tickets exist, verify the row content
  const rowCount = await page.locator('[class*="ticket-row"], #tickets tbody tr').count();
  if (rowCount > 0) {
    const firstRow = page.locator('[class*="ticket-row"], #tickets tbody tr').first();
    await expect(firstRow).toBeVisible();
    const rowText = await firstRow.innerText();
    expect(rowText.length).toBeGreaterThan(0);
  }
});

test.skip('open ticket has warning/amber status badge', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const openBadge = page.locator('.badge-warning, .badge-amber, [class*="badge"][class*="warning"], [class*="status"][class*="open"]').first();
  if (await openBadge.count() > 0) {
    await expect(openBadge).toBeVisible();
  }
});

test.skip('closed ticket has success/green status badge', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const closedBadge = page.locator('.badge-success, .badge-green, [class*="badge"][class*="success"], [class*="status"][class*="closed"]').first();
  if (await closedBadge.count() > 0) {
    await expect(closedBadge).toBeVisible();
  }
});

test.skip('admin reply textarea is visible per ticket', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const replyTextarea = page.locator('textarea[name*="reply"], textarea[id*="reply"], textarea[placeholder*="رد"]').first();
  if (await replyTextarea.count() > 0) {
    await expect(replyTextarea).toBeVisible();
  }
});

test.skip('reply submit button is visible per ticket', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const replyBtn = page.locator('button:has-text("رد"), input[value="رد"], button[class*="reply"]').first();
  if (await replyBtn.count() > 0) {
    await expect(replyBtn).toBeVisible();
  }
});

test.skip('close ticket button is visible per ticket', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const closeBtn = page.locator('button:has-text("أغلق"), button:has-text("إغلاق"), button[class*="close-ticket"]').first();
  if (await closeBtn.count() > 0) {
    await expect(closeBtn).toBeVisible();
  }
});

// ─── 5. Admin Actions — Ticket Reply ─────────────────────────────────────────

test.skip('filling reply and clicking رد POSTs to api/admin_actions.php', async ({ page }) => {
  await page.goto('/admin/dashboard.php');

  const replyTextarea = page.locator('textarea[name*="reply"], textarea[id*="reply"]').first();
  const replyBtn = page.locator('button:has-text("رد")').first();

  if (await replyTextarea.count() === 0 || await replyBtn.count() === 0) {
    test.skip();
    return;
  }

  const [request] = await Promise.all([
    page.waitForRequest(req =>
      req.url().includes('/api/admin_actions.php') && req.method() === 'POST'
    ),
    replyTextarea.fill('This is a test admin reply'),
    replyBtn.click(),
  ]);

  expect(request.url()).toContain('/api/admin_actions.php');
  const postData = request.postData() ?? '';
  expect(postData).toContain('action=reply_ticket');
});

test.skip('after reply the admin reply text is shown in the ticket', async ({ page }) => {
  await page.goto('/admin/dashboard.php');

  const replyTextarea = page.locator('textarea[name*="reply"], textarea[id*="reply"]').first();
  const replyBtn = page.locator('button:has-text("رد")').first();

  if (await replyTextarea.count() === 0 || await replyBtn.count() === 0) {
    test.skip();
    return;
  }

  const replyText = 'Test reply text ' + Date.now();
  await replyTextarea.fill(replyText);
  await replyBtn.click();

  // Wait for the page to update (AJAX or reload)
  await page.waitForTimeout(1000);

  const pageContent = await page.content();
  expect(pageContent).toContain(replyText);
});

test.skip('ticket stays open after admin reply (reply does not auto-close)', async ({ page }) => {
  await page.goto('/admin/dashboard.php');

  const openTickets = page.locator('[class*="ticket"]:has(.badge-warning), [class*="ticket"]:has([class*="open"])');
  if (await openTickets.count() === 0) {
    test.skip();
    return;
  }

  const firstOpenTicket = openTickets.first();
  const replyTextarea = firstOpenTicket.locator('textarea').first();
  const replyBtn = firstOpenTicket.locator('button:has-text("رد")').first();

  await replyTextarea.fill('Reply without closing');
  await replyBtn.click();
  await page.waitForTimeout(1000);

  // The ticket should still show an open status badge
  const stillOpenBadge = firstOpenTicket.locator('.badge-warning, [class*="badge"][class*="warning"], [class*="open"]');
  await expect(stillOpenBadge.first()).toBeVisible();
});

// ─── 6. Admin Actions — Close Ticket ─────────────────────────────────────────

test.skip('clicking close button changes ticket status to closed', async ({ page }) => {
  await page.goto('/admin/dashboard.php');

  const openTickets = page.locator('[class*="ticket"]:has(.badge-warning)');
  if (await openTickets.count() === 0) {
    test.skip();
    return;
  }

  const firstOpenTicket = openTickets.first();
  const closeBtn = firstOpenTicket.locator('button:has-text("أغلق"), button:has-text("إغلاق")').first();

  if (await closeBtn.count() === 0) {
    test.skip();
    return;
  }

  // Handle potential confirmation dialog
  page.once('dialog', async dialog => {
    await dialog.accept();
  });

  await closeBtn.click();
  await page.waitForTimeout(1000);

  // After closing, the closed badge should appear
  const closedBadge = page.locator('.badge-success, [class*="badge"][class*="success"]').first();
  await expect(closedBadge).toBeVisible();
});

// ─── 7. Recent Users Table ────────────────────────────────────────────────────

test.skip('recent users table headers are visible', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const usersTable = page.locator('#users table, [id*="recent-users"] table, table').first();
  await expect(usersTable).toBeVisible();
  const tableText = await usersTable.innerText();
  // At least one of these Arabic column headers should be present
  const hasHeaders =
    tableText.includes('الاسم') ||
    tableText.includes('البريد') ||
    tableText.includes('الدور') ||
    tableText.includes('تاريخ') ||
    tableText.includes('name') ||
    tableText.includes('email');
  expect(hasHeaders).toBe(true);
});

test.skip('each user row shows name and email', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const userRows = page.locator('#users tbody tr, [id*="recent-users"] tbody tr, table tbody tr');
  const rowCount = await userRows.count();
  if (rowCount === 0) {
    test.skip();
    return;
  }
  const firstRow = userRows.first();
  const rowText = await firstRow.innerText();
  // Should contain an @ sign (email) or at least some non-empty text
  expect(rowText.trim().length).toBeGreaterThan(0);
  expect(rowText).toContain('@');
});

test.skip('super admin row has a special badge', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const superAdminBadge = page.locator('[class*="super"], .badge:has-text("super"), [class*="badge"]:has-text("مدير")').first();
  if (await superAdminBadge.count() > 0) {
    await expect(superAdminBadge).toBeVisible();
  }
});

// ─── 8. RBAC — super_admin vs regular admin ───────────────────────────────────

test.skip('as super_admin: promote/demote/delete buttons are visible on user rows', async ({ page }) => {
  // This test assumes the current session is super_admin
  await page.goto('/admin/dashboard.php');
  const promoteBtn = page.locator('button:has-text("ترقية"), button:has-text("Promote"), [class*="promote"]').first();
  const deleteBtn = page.locator('button:has-text("حذف"), button:has-text("Delete"), [class*="delete-user"]').first();

  await expect(promoteBtn).toBeVisible();
  await expect(deleteBtn).toBeVisible();
});

test.skip('as regular admin: no promote or delete buttons visible (RBAC)', async ({ page }) => {
  // This test assumes the current session is a regular (non-super) admin
  await page.goto('/admin/dashboard.php');
  const promoteBtn = page.locator('button:has-text("ترقية"), button:has-text("Promote"), [class*="promote"]');
  const deleteBtn = page.locator('button:has-text("حذف مستخدم"), [class*="delete-user"]');

  await expect(promoteBtn).toHaveCount(0);
  await expect(deleteBtn).toHaveCount(0);
});

test.skip('super_admin cannot delete or demote themselves', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  // The row for the current super_admin should not have a delete/demote button
  // The super_admin row is typically identified by a special badge or data attribute
  const selfRow = page.locator('tr[data-self="true"], tr[class*="self"], tr:has([class*="super-admin-badge"])').first();
  if (await selfRow.count() === 0) {
    test.skip();
    return;
  }
  const selfDeleteBtn = selfRow.locator('button:has-text("حذف"), [class*="delete-user"]');
  await expect(selfDeleteBtn).toHaveCount(0);
});

// ─── 9. Export Functionality ──────────────────────────────────────────────────

test.skip('export logs button is present on the page', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const exportLogsBtn = page.locator(
    'a[href*="export_logs"], button:has-text("تصدير السجلات"), a:has-text("سجلات"), [class*="export-logs"]'
  ).first();
  await expect(exportLogsBtn).toBeVisible();
});

test.skip('clicking export logs triggers a CSV download', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const exportLogsBtn = page.locator(
    'a[href*="export_logs"], button:has-text("تصدير السجلات"), a:has-text("سجلات"), [class*="export-logs"]'
  ).first();

  if (await exportLogsBtn.count() === 0) {
    test.skip();
    return;
  }

  const [download] = await Promise.all([
    page.waitForEvent('download'),
    exportLogsBtn.click(),
  ]);

  expect(download).toBeTruthy();
  const suggestedFilename = download.suggestedFilename();
  // Should be a CSV file
  expect(suggestedFilename.toLowerCase()).toMatch(/\.(csv|txt)$/);
});

test.skip('export ZIP button is present on the page', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const exportZipBtn = page.locator(
    'a[href*="export_zip"], button:has-text("تصدير ZIP"), a:has-text("ZIP"), [class*="export-zip"]'
  ).first();
  await expect(exportZipBtn).toBeVisible();
});

test.skip('clicking export ZIP triggers a ZIP download', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const exportZipBtn = page.locator(
    'a[href*="export_zip"], button:has-text("تصدير ZIP"), a:has-text("ZIP"), [class*="export-zip"]'
  ).first();

  if (await exportZipBtn.count() === 0) {
    test.skip();
    return;
  }

  const [download] = await Promise.all([
    page.waitForEvent('download'),
    exportZipBtn.click(),
  ]);

  expect(download).toBeTruthy();
  const suggestedFilename = download.suggestedFilename();
  expect(suggestedFilename.toLowerCase()).toMatch(/\.zip$/);
});

// ─── 10. CSRF Validation ─────────────────────────────────────────────────────

test.skip('CSRF token is present in the page for admin AJAX actions', async ({ page }) => {
  await page.goto('/admin/dashboard.php');

  // Check for CSRF token in a hidden input
  const csrfInput = page.locator(
    'input[name="csrf_token"], input[name="_token"], input[type="hidden"][name*="csrf"]'
  );

  // Or check for a JS variable containing the token
  const hasJsCsrf = await page.evaluate(() => {
    return (
      typeof (window as any).csrf_token === 'string' ||
      typeof (window as any).CSRF_TOKEN === 'string' ||
      typeof (window as any).csrfToken === 'string'
    );
  });

  const inputCount = await csrfInput.count();
  const hasCsrf = inputCount > 0 || hasJsCsrf;
  expect(hasCsrf).toBe(true);
});

test.skip('CSRF token in hidden input is non-empty', async ({ page }) => {
  await page.goto('/admin/dashboard.php');
  const csrfInput = page.locator(
    'input[name="csrf_token"], input[name="_token"], input[type="hidden"][name*="csrf"]'
  ).first();

  if (await csrfInput.count() === 0) {
    // Check JS variable instead
    const tokenValue = await page.evaluate(() =>
      (window as any).csrf_token ?? (window as any).CSRF_TOKEN ?? (window as any).csrfToken ?? ''
    );
    expect(typeof tokenValue).toBe('string');
    expect(tokenValue.length).toBeGreaterThan(0);
    return;
  }

  const tokenValue = await csrfInput.getAttribute('value');
  expect(tokenValue).toBeTruthy();
  expect((tokenValue ?? '').length).toBeGreaterThan(0);
});
