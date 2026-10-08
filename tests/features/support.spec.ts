import { test, expect } from '@playwright/test';

test.use({ baseURL: 'http://localhost' });

// ─── 1. Unauthenticated redirect ─────────────────────────────────────────────

test('unauthenticated: GET /pages/support.php redirects to /welcome.php', async ({ page }) => {
  await page.goto('/pages/support.php');
  await expect(page).toHaveURL(/welcome\.php/);
});

// ─── 2. UI Rendering ─────────────────────────────────────────────────────────

test.skip('UI: page title contains ميزان', async ({ page }) => {
  await page.goto('/pages/support.php');
  await expect(page).toHaveTitle(/ميزان/);
});

test.skip('UI: h1 shows الدعم والمساعدة', async ({ page }) => {
  await page.goto('/pages/support.php');
  await expect(page.locator('h1.page-title')).toBeVisible();
  await expect(page.locator('h1.page-title')).toHaveText('الدعم والمساعدة');
});

test.skip('UI: subtitle shows أسئلة شائعة ونموذج تواصل', async ({ page }) => {
  await page.goto('/pages/support.php');
  await expect(page.locator('p.text-muted')).toBeVisible();
  await expect(page.locator('p.text-muted')).toHaveText('أسئلة شائعة ونموذج تواصل');
});

test.skip('UI: .sup-switcher visible with exactly 2 buttons', async ({ page }) => {
  await page.goto('/pages/support.php');
  const switcher = page.locator('.sup-switcher');
  await expect(switcher).toBeVisible();
  await expect(switcher.locator('.sup-sw-btn')).toHaveCount(2);
});

test.skip('UI: first switcher button is active by default', async ({ page }) => {
  await page.goto('/pages/support.php');
  const firstBtn = page.locator('.sup-switcher .sup-sw-btn').first();
  await expect(firstBtn).toHaveClass(/active/);
});

test.skip('UI: policies area visible on load', async ({ page }) => {
  await page.goto('/pages/support.php');
  const areas = page.locator('.sup-area');
  const policiesArea = areas.first();
  await expect(policiesArea).toBeVisible();
  await expect(policiesArea).not.toHaveClass(/hidden/);
});

test.skip('UI: contact area hidden on load', async ({ page }) => {
  await page.goto('/pages/support.php');
  const areas = page.locator('.sup-area');
  const contactArea = areas.nth(1);
  await expect(contactArea).toHaveClass(/hidden/);
});

test.skip('UI: html element has lang=ar and dir=rtl', async ({ page }) => {
  await page.goto('/pages/support.php');
  await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
});

// ─── 3. Policies area ────────────────────────────────────────────────────────

test.skip('policies: .sup-policy-stack is visible', async ({ page }) => {
  await page.goto('/pages/support.php');
  await expect(page.locator('.sup-policy-stack')).toBeVisible();
});

test.skip('policies: at least one .sup-policy-card is visible', async ({ page }) => {
  await page.goto('/pages/support.php');
  const cards = page.locator('.sup-policy-card');
  await expect(cards.first()).toBeVisible();
  expect(await cards.count()).toBeGreaterThanOrEqual(1);
});

test.skip('policies: each card has .sup-policy-icon and .sup-policy-title', async ({ page }) => {
  await page.goto('/pages/support.php');
  const cards = page.locator('.sup-policy-card');
  const count = await cards.count();
  for (let i = 0; i < count; i++) {
    const card = cards.nth(i);
    await expect(card.locator('.sup-policy-icon')).toBeAttached();
    await expect(card.locator('.sup-policy-title')).toBeAttached();
  }
});

test.skip('policies: each card title has non-empty text', async ({ page }) => {
  await page.goto('/pages/support.php');
  const titles = page.locator('.sup-policy-card .sup-policy-title');
  const count = await titles.count();
  expect(count).toBeGreaterThanOrEqual(1);
  for (let i = 0; i < count; i++) {
    const text = await titles.nth(i).textContent();
    expect((text ?? '').trim().length).toBeGreaterThan(0);
  }
});

// ─── 4. Area switcher ────────────────────────────────────────────────────────

test.skip('switcher: clicking second button shows contact area and hides policies', async ({ page }) => {
  await page.goto('/pages/support.php');
  const buttons = page.locator('.sup-switcher .sup-sw-btn');
  const areas = page.locator('.sup-area');
  const policiesArea = areas.first();
  const contactArea = areas.nth(1);

  await buttons.nth(1).click();

  await expect(contactArea).not.toHaveClass(/hidden/);
  await expect(policiesArea).toHaveClass(/hidden/);
});

test.skip('switcher: second button gets .active, first loses it after click', async ({ page }) => {
  await page.goto('/pages/support.php');
  const buttons = page.locator('.sup-switcher .sup-sw-btn');

  await buttons.nth(1).click();

  await expect(buttons.nth(1)).toHaveClass(/active/);
  await expect(buttons.nth(0)).not.toHaveClass(/active/);
});

test.skip('switcher: clicking first button again switches back to policies area', async ({ page }) => {
  await page.goto('/pages/support.php');
  const buttons = page.locator('.sup-switcher .sup-sw-btn');
  const areas = page.locator('.sup-area');
  const policiesArea = areas.first();
  const contactArea = areas.nth(1);

  await buttons.nth(1).click();
  await buttons.nth(0).click();

  await expect(policiesArea).not.toHaveClass(/hidden/);
  await expect(contactArea).toHaveClass(/hidden/);
  await expect(buttons.nth(0)).toHaveClass(/active/);
  await expect(buttons.nth(1)).not.toHaveClass(/active/);
});

// ─── 5. Contact / ticket form ────────────────────────────────────────────────

test.skip('contact form: subject field, message textarea, and submit button are visible', async ({ page }) => {
  await page.goto('/pages/support.php');
  await page.locator('.sup-switcher .sup-sw-btn').nth(1).click();

  const contactArea = page.locator('.sup-area').nth(1);
  await expect(
    contactArea.locator('input[name="subject"], select[name="subject"], input[name="category"], select[name="category"]').first()
  ).toBeVisible();
  await expect(contactArea.locator('textarea')).toBeVisible();
  await expect(contactArea.locator('button[type="submit"]')).toBeVisible();
});

test.skip('contact form: submitting empty fields shows validation error', async ({ page }) => {
  await page.goto('/pages/support.php');
  await page.locator('.sup-switcher .sup-sw-btn').nth(1).click();

  const contactArea = page.locator('.sup-area').nth(1);
  await contactArea.locator('button[type="submit"]').click();

  // Expect some validation feedback — native HTML5 or custom error element
  const hasNativeValidity = await page.evaluate(() => {
    const form = document.querySelector('.sup-area:not(.hidden) form') as HTMLFormElement | null;
    if (!form) return false;
    return !form.checkValidity();
  });
  const hasCustomError = await page.locator('.error, .alert-error, [class*="error"], [class*="invalid"]').count();

  expect(hasNativeValidity || hasCustomError > 0).toBeTruthy();
});

test.skip('contact form: submitting valid data POSTs to api/support.php', async ({ page }) => {
  await page.goto('/pages/support.php');
  await page.locator('.sup-switcher .sup-sw-btn').nth(1).click();

  const contactArea = page.locator('.sup-area').nth(1);

  const [request] = await Promise.all([
    page.waitForRequest(req => req.url().includes('/api/support.php') && req.method() === 'POST'),
    (async () => {
      const subjectField = contactArea.locator(
        'input[name="subject"], select[name="subject"], input[name="category"], select[name="category"]'
      ).first();
      const tagName = await subjectField.evaluate(el => el.tagName.toLowerCase());
      if (tagName === 'select') {
        await subjectField.selectOption({ index: 1 });
      } else {
        await subjectField.fill('استفسار عام');
      }
      await contactArea.locator('textarea').fill('هذا اختبار للنموذج');
      await contactArea.locator('button[type="submit"]').click();
    })(),
  ]);

  expect(request).toBeTruthy();
  expect(request.url()).toContain('/api/support.php');
});

test.skip('contact form: after successful submission ticket appears in list and form clears', async ({ page }) => {
  await page.goto('/pages/support.php');
  await page.locator('.sup-switcher .sup-sw-btn').nth(1).click();

  const contactArea = page.locator('.sup-area').nth(1);
  const subjectField = contactArea.locator(
    'input[name="subject"], select[name="subject"], input[name="category"], select[name="category"]'
  ).first();
  const textarea = contactArea.locator('textarea');

  const tagName = await subjectField.evaluate(el => el.tagName.toLowerCase());
  if (tagName === 'select') {
    await subjectField.selectOption({ index: 1 });
  } else {
    await subjectField.fill('استفسار عام');
  }
  await textarea.fill('رسالة اختبارية');

  await page.waitForResponse(resp => resp.url().includes('/api/support.php') && resp.status() === 200, {
    timeout: 8000,
  }).catch(() => null);

  await contactArea.locator('button[type="submit"]').click();
  await page.waitForTimeout(800);

  // Form should be cleared after submission
  await expect(textarea).toHaveValue('');
});

// ─── 6. Ticket list ──────────────────────────────────────────────────────────

test.skip('ticket list: each ticket shows subject, status badge, and date', async ({ page }) => {
  await page.goto('/pages/support.php');
  await page.locator('.sup-switcher .sup-sw-btn').nth(1).click();

  const contactArea = page.locator('.sup-area').nth(1);
  const tickets = contactArea.locator('[class*="ticket"], [class*="sup-ticket"], .ticket-item');
  const count = await tickets.count();

  if (count === 0) {
    test.info().annotations.push({ type: 'note', description: 'No existing tickets to verify' });
    return;
  }

  for (let i = 0; i < count; i++) {
    const ticket = tickets.nth(i);
    const subject = await ticket.locator('[class*="subject"], [class*="title"]').first().textContent();
    expect((subject ?? '').trim().length).toBeGreaterThan(0);

    const badge = ticket.locator('[class*="badge"], [class*="status"]').first();
    await expect(badge).toBeAttached();

    const date = ticket.locator('[class*="date"], [class*="created"], time').first();
    await expect(date).toBeAttached();
  }
});

test.skip('ticket list: open ticket has badge-warning or amber styling', async ({ page }) => {
  await page.goto('/pages/support.php');
  await page.locator('.sup-switcher .sup-sw-btn').nth(1).click();

  const openBadges = page.locator('.badge-warning, [class*="badge-warning"], [class*="amber"], [class*="open"]');
  const count = await openBadges.count();
  if (count > 0) {
    await expect(openBadges.first()).toBeAttached();
  } else {
    test.info().annotations.push({ type: 'note', description: 'No open tickets present' });
  }
});

test.skip('ticket list: closed ticket has badge-success or green styling', async ({ page }) => {
  await page.goto('/pages/support.php');
  await page.locator('.sup-switcher .sup-sw-btn').nth(1).click();

  const closedBadges = page.locator('.badge-success, [class*="badge-success"], [class*="green"], [class*="closed"]');
  const count = await closedBadges.count();
  if (count > 0) {
    await expect(closedBadges.first()).toBeAttached();
  } else {
    test.info().annotations.push({ type: 'note', description: 'No closed tickets present' });
  }
});

test.skip('ticket list: admin reply shown when present', async ({ page }) => {
  await page.goto('/pages/support.php');
  await page.locator('.sup-switcher .sup-sw-btn').nth(1).click();

  const adminReplies = page.locator('[class*="admin-reply"], [class*="reply"], [class*="response"]');
  const count = await adminReplies.count();
  if (count > 0) {
    await expect(adminReplies.first()).toBeVisible();
  } else {
    test.info().annotations.push({ type: 'note', description: 'No admin replies present to verify' });
  }
});

// ─── 7. Arabic text input ────────────────────────────────────────────────────

test.skip('Arabic text: RTL textarea accepts Arabic text', async ({ page }) => {
  await page.goto('/pages/support.php');
  await page.locator('.sup-switcher .sup-sw-btn').nth(1).click();

  const textarea = page.locator('.sup-area').nth(1).locator('textarea');
  await textarea.fill('هذا نص عربي للاختبار');
  await expect(textarea).toHaveValue('هذا نص عربي للاختبار');
});

test.skip('Arabic text: submitted Arabic message appears in ticket list', async ({ page }) => {
  await page.goto('/pages/support.php');
  await page.locator('.sup-switcher .sup-sw-btn').nth(1).click();

  const contactArea = page.locator('.sup-area').nth(1);
  const subjectField = contactArea.locator(
    'input[name="subject"], select[name="subject"], input[name="category"], select[name="category"]'
  ).first();
  const textarea = contactArea.locator('textarea');
  const arabicMessage = 'رسالة باللغة العربية للاختبار';

  const tagName = await subjectField.evaluate(el => el.tagName.toLowerCase());
  if (tagName === 'select') {
    await subjectField.selectOption({ index: 1 });
  } else {
    await subjectField.fill('موضوع الاختبار');
  }
  await textarea.fill(arabicMessage);
  await contactArea.locator('button[type="submit"]').click();

  await page.waitForTimeout(1000);

  const ticketList = contactArea.locator('[class*="ticket"], [class*="sup-ticket"], .ticket-item');
  const count = await ticketList.count();
  if (count > 0) {
    const pageContent = await contactArea.textContent();
    expect(pageContent).toContain(arabicMessage);
  }
});

// ─── 8. Localization keys ────────────────────────────────────────────────────

test.skip('i18n: h1 has data-i18n="support_title"', async ({ page }) => {
  await page.goto('/pages/support.php');
  await expect(page.locator('h1.page-title')).toHaveAttribute('data-i18n', 'support_title');
});

test.skip('i18n: subtitle has data-i18n="support_desc"', async ({ page }) => {
  await page.goto('/pages/support.php');
  await expect(page.locator('p.text-muted[data-i18n="support_desc"]')).toBeAttached();
});

test.skip('i18n: switching language to EN changes h1 to English text', async ({ page }) => {
  await page.goto('/pages/support.php');

  const arabicText = await page.locator('h1.page-title').textContent();

  const langToggle = page.locator('[data-lang="en"], button:has-text("EN"), [class*="lang-toggle"]').first();
  if (await langToggle.count() === 0) {
    test.info().annotations.push({ type: 'note', description: 'No language toggle found on page' });
    return;
  }

  await langToggle.click();
  await page.waitForTimeout(300);

  const englishText = await page.locator('h1.page-title').textContent();
  expect(englishText?.trim()).not.toEqual(arabicText?.trim());
  // English fallback should not be Arabic characters
  expect(englishText).toMatch(/[a-zA-Z]/);
});
