<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: share.php
 * PURPOSE: Public share-link resolver for read-only project snapshots.
 * OWNER: Mishal Al-jumaih - ScrumMaster & Development Member (Full Stack All Rounder)
 * ========================================================================
 */
require_once 'includes/security.php';
mz_send_security_headers();

require_once 'config/db.php';

$pdo        = getDB();
$token      = trim($_GET['t'] ?? '');
$url_id     = (int) ($_GET['id'] ?? 0); // legacy: still accepted for backward-compat

// Strict token format check — must be exactly 64 hex chars (matches our CSPRNG output).
// Anything else is treated as a forged/typo link and returned 404 without a DB hit.
if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>404</title></head><body style="font-family:sans-serif;text-align:center;padding:80px;"><h1>404</h1><p>الرابط غير صالح</p></body></html>';
    exit;
}

// The token alone determines which project to show — the URL ?id= is informational only.
// This prevents anyone from swapping ?id= to a different project while keeping a valid token.
// If legacy ?id= is present, validate it matches to prevent token-swapping.
$s = $pdo->prepare('
    SELECT project_id FROM project_share_tokens
    WHERE token = ? AND revoked_at IS NULL AND expires_at > NOW()
    LIMIT 1
');
$s->execute([$token]);
$tok_row = $s->fetch();

if (!$tok_row) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403</title></head><body style="font-family:sans-serif;text-align:center;padding:80px;"><h1>403</h1><p>انتهت صلاحية رابط المشاركة أو تم إلغاؤه</p></body></html>';
    exit;
}

$project_id = (int) $tok_row['project_id'];

// Legacy guard: if ?id= is in URL, it must match the token's project
if ($url_id > 0 && $url_id !== $project_id) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403</title></head><body style="font-family:sans-serif;text-align:center;padding:80px;"><h1>403</h1><p>رمز المشاركة غير صحيح</p></body></html>';
    exit;
}

$s = $pdo->prepare('SELECT * FROM projects WHERE id = ?');
$s->execute([$project_id]);
$project = $s->fetch();

if (!$project) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>404</title></head><body style="font-family:sans-serif;text-align:center;padding:80px;"><h1>404</h1><p>المشروع غير موجود</p></body></html>';
    exit;
}

// Fetch expenses with warranty + invoice data
$s = $pdo->prepare('
    SELECT e.*,
           w.id AS warranty_id, w.end_date AS warranty_end,
           DATEDIFF(w.end_date, CURDATE()) AS warranty_days_left,
           i.file_path AS invoice_file
    FROM expenses e
    LEFT JOIN warranties w ON w.expense_id = e.id
    LEFT JOIN invoices   i ON i.expense_id = e.id
    WHERE e.project_id = ?
    ORDER BY e.purchase_date DESC, e.created_at DESC
');
$s->execute([$project_id]);
$expenses = $s->fetchAll();

// Fetch files (metadata only — no download links exposed)
$sf = $pdo->prepare('
    SELECT id, file_path, COALESCE(file_size, 0) AS file_size, uploaded_at
    FROM files WHERE project_id = ?
    ORDER BY uploaded_at DESC
');
$sf->execute([$project_id]);
$proj_files = $sf->fetchAll();

// Derived numbers
$total_cost  = array_sum(array_column($expenses, 'amount'));
$budget      = (float) $project['budget'];
$budget_pct  = $budget > 0 ? min(100, round(($total_cost / $budget) * 100)) : 0;
$profit      = (float) $project['sell_price'] > 0 ? ($project['sell_price'] - $total_cost) : null;

$warranty_active = $warranty_expiring = $warranty_expired = 0;
foreach ($expenses as $e) {
    if (!$e['warranty_id']) continue;
    $dl = (int) $e['warranty_days_left'];
    if ($dl < 0) $warranty_expired++;
    elseif ($dl <= 30) $warranty_expiring++;
    else $warranty_active++;
}

// Category totals for Chart.js
$cat_totals = [];
foreach ($expenses as $e) {
    $cat = trim((string) ($e['category'] ?? '')) ?: 'أخرى';
    $cat_totals[$cat] = ($cat_totals[$cat] ?? 0) + (float) $e['amount'];
}
arsort($cat_totals);
$chart_labels = array_keys($cat_totals);
$chart_values = array_values($cat_totals);

// Whitelist the stored color to a strict #RRGGBB pattern before interpolating into CSS
// — defends against stored-XSS / CSS-injection via the projects.color column
$raw_color = (string) ($project['color'] ?: '#3b82f6');
$color     = preg_match('/^#[0-9a-fA-F]{6}$/', $raw_color) ? $raw_color : '#3b82f6';

// Status
$statuses    = ['active' => 'نشط', 'done' => 'مكتمل', 'archived' => 'مؤرشف'];
$status_label = htmlspecialchars($statuses[$project['status']] ?? '—', ENT_QUOTES, 'UTF-8');

// Project icon
$icons = ['car' => '🚗', 'house' => '🏠', 'occasion' => '🎉', 'work' => '💼', 'devices' => '📦', 'custom' => '✏️'];
$icon  = $icons[$project['type']] ?? '✏️';

// Helper: human-readable file size
function fmt_size(int $b): string {
    if ($b >= 1073741824) return round($b / 1073741824, 1) . ' GB';
    if ($b >= 1048576)    return round($b / 1048576, 1)    . ' MB';
    if ($b >= 1024)       return round($b / 1024, 1)       . ' KB';
    return $b . ' B';
}

// Helper: file extension → type label
function file_ext_label(string $path): string {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return match ($ext) {
        'pdf'              => 'PDF',
        'jpg','jpeg','png','webp','gif','heic' => 'صورة',
        'doc','docx'       => 'Word',
        'xls','xlsx'       => 'Excel',
        'zip','rar','7z'   => 'ضغط',
        default            => strtoupper($ext) ?: 'ملف',
    };
}
// ─────────────────────────────────────────────────────────────────────────
// END PHP LOGIC — BEGIN HTML VIEW (read-only public share page)
// ─────────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($project['name']) ?> — ميزان (مشاركة)</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon-32x32.png">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
    <meta property="og:image" content="/assets/icons/meta-banner.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>
    <style>
    /* ── Share page layout ── */
    body {
        background: var(--bg);
        min-height: 100vh;
        font-family: 'Tajawal', sans-serif;
        position: relative;
    }

    #bg-canvas {
        position: fixed;
        inset: 0;
        width: 100%;
        height: 100%;
        z-index: 0;
        pointer-events: none;
        opacity: 0;
        transition: opacity 1.4s ease-out;
    }
    #bg-canvas.ready { opacity: .45; }
    [data-theme="dark"] #bg-canvas.ready { opacity: .22; }

    .share-page {
        position: relative;
        z-index: 2;
        max-width: 960px;
        margin: 0 auto;
        padding: 28px 20px 60px;
    }

    /* ── Read-only header bar ── */
    .share-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 10px;
    }

    .share-brand {
        font-size: 17px;
        font-weight: 900;
        color: var(--primary);
        letter-spacing: -.3px;
    }

    .share-topbar-right {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .share-readonly-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(14, 165, 233, 0.10);
        color: var(--info);
        border: 1px solid rgba(14, 165, 233, 0.22);
        padding: 5px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .3px;
    }

    .share-ctrl {
        width: 34px;
        height: 34px;
        background: var(--surface2, var(--surf));
        border: 1px solid var(--border, var(--bdr));
        border-radius: 9px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 700;
        color: var(--text-muted, var(--mut));
        font-family: 'Tajawal', sans-serif;
        transition: border-color .2s, color .2s, transform .15s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .share-ctrl:hover {
        border-color: var(--primary);
        color: var(--primary);
        transform: scale(1.08);
    }

    /* ── Project hero card ── */
    .share-hero {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 28px 32px 24px;
        margin-bottom: 20px;
        position: relative;
        overflow: hidden;
        animation: shareCardIn .7s var(--ease-spring) both;
    }

    .share-hero::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 4px;
        background: <?= $color ?>;
    }

    .share-status-badge {
        position: absolute;
        top: 16px;
        inset-inline-end: 20px;
        background: var(--surface2);
        color: var(--text-muted);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        border: 1px solid var(--border);
    }

    .share-hero-top {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 22px;
    }

    .share-icon-wrap {
        width: 58px;
        height: 58px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        background: <?= $color ?>22;
        border: 2px solid <?= $color ?>55;
        flex-shrink: 0;
    }

    .share-project-name {
        font-size: 22px;
        font-weight: 800;
        color: var(--text);
        line-height: 1.2;
    }

    .share-project-meta {
        font-size: 13px;
        color: var(--text-muted);
        margin-top: 4px;
    }

    /* ── Stats grid ── */
    .share-stats {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }

    .share-stat {
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 14px 16px;
        text-align: center;
        transition: transform .25s var(--ease-spring), box-shadow .25s;
    }

    .share-stat:hover {
        transform: translateY(-3px);
        box-shadow: var(--gs-hover);
    }

    .share-stat-val {
        font-size: 22px;
        font-weight: 800;
        color: var(--text);
        line-height: 1;
    }

    .share-stat-lbl {
        font-size: 11.5px;
        color: var(--text-muted);
        margin-top: 5px;
        line-height: 1.4;
    }

    /* ── Budget bar ── */
    .share-budget {
        margin-top: 4px;
    }

    .share-budget-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 12.5px;
        color: var(--text-muted);
        margin-bottom: 7px;
    }

    .share-budget-row strong { color: var(--text); }

    .share-budget-track {
        height: 9px;
        background: var(--surface2);
        border-radius: 5px;
        overflow: hidden;
    }

    .share-budget-fill {
        height: 100%;
        border-radius: 5px;
        transition: width 1.4s cubic-bezier(.22, 1, .36, 1);
    }

    /* ── Generic section card ── */
    .share-section {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        margin-bottom: 20px;
        overflow: hidden;
        animation: shareCardIn .7s var(--ease-spring) both;
    }

    .share-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 24px 16px;
        border-bottom: 1px solid var(--border);
    }

    .share-section-title {
        font-size: 15px;
        font-weight: 700;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 9px;
        margin: 0;
    }

    .share-count-badge {
        background: var(--surface2);
        color: var(--text-muted);
        border: 1px solid var(--border);
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 700;
    }

    /* ── Chart section ── */
    .chart-grid {
        display: grid;
        grid-template-columns: 200px 1fr;
        gap: 24px;
        align-items: center;
        padding: 24px;
    }

    @media (max-width: 600px) {
        .chart-grid { grid-template-columns: 1fr; }
    }

    .chart-canvas-wrap {
        position: relative;
        width: 200px;
        height: 200px;
        flex-shrink: 0;
    }

    @media (max-width: 600px) {
        .chart-canvas-wrap { width: 180px; height: 180px; margin: 0 auto; }
    }

    .chart-total-center {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        pointer-events: none;
    }

    .chart-total-val {
        font-size: 18px;
        font-weight: 800;
        color: var(--text);
        line-height: 1;
    }

    .chart-total-lbl {
        font-size: 10.5px;
        color: var(--text-muted);
        margin-top: 3px;
    }

    .chart-legend {
        display: flex;
        flex-direction: column;
        gap: 9px;
    }

    .chart-legend-item {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .chart-legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .chart-legend-name {
        font-size: 13px;
        color: var(--text);
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .chart-legend-pct {
        font-size: 12.5px;
        font-weight: 700;
        color: var(--text-muted);
        white-space: nowrap;
    }

    .chart-legend-bar-wrap {
        flex: 1;
        height: 5px;
        background: var(--surface2);
        border-radius: 3px;
        overflow: hidden;
    }

    .chart-legend-bar {
        height: 100%;
        border-radius: 3px;
        transition: width 1s var(--ease-spring);
    }

    /* ── Expense list ── */
    .expense-list { padding: 0 24px; }

    .share-expense {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 14px 0;
        border-bottom: 1px solid var(--border);
        gap: 12px;
    }

    .share-expense:last-child { border-bottom: none; }

    .share-expense-info { flex: 1; min-width: 0; }

    .share-expense-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .share-expense-meta {
        font-size: 11.5px;
        color: var(--text-muted);
        margin-top: 3px;
    }

    .share-warranty-pill {
        display: inline-flex;
        align-items: center;
        font-size: 10.5px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 10px;
        margin-top: 5px;
    }

    .w-active   { background: rgba(5,150,105,0.10);  color: var(--success); }
    .w-expiring { background: rgba(217,119,6,0.10);  color: var(--warning); }
    .w-expired  { background: rgba(239,68,68,0.10);  color: var(--danger);  }

    .share-expense-amount {
        font-size: 15px;
        font-weight: 800;
        color: var(--text);
        white-space: nowrap;
        text-align: left;
        flex-shrink: 0;
    }

    .share-expense-amount small {
        font-size: 10px;
        font-weight: 500;
        color: var(--text-muted);
        display: block;
        margin-top: 1px;
    }

    /* ── File vault ── */
    .file-list { padding: 0 24px; }

    .share-file {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 0;
        border-bottom: 1px solid var(--border);
    }

    .share-file:last-child { border-bottom: none; }

    .share-file-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: var(--surface2);
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .share-file-icon svg { width: 16px; height: 16px; opacity: .7; }

    .share-file-name {
        flex: 1;
        min-width: 0;
        font-size: 13px;
        font-weight: 600;
        color: var(--text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .share-file-meta {
        font-size: 11.5px;
        color: var(--text-muted);
        margin-top: 2px;
    }

    .share-file-badge {
        font-size: 10.5px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 8px;
        background: var(--surface2);
        color: var(--text-muted);
        border: 1px solid var(--border);
        flex-shrink: 0;
    }

    /* ── Warranty summary ── */
    .warranty-stats { padding: 16px 24px 20px; }

    /* ── Footer ── */
    .share-footer {
        text-align: center;
        padding: 28px 24px;
        font-size: 13px;
        color: var(--text-muted);
    }

    .share-footer-brand {
        font-weight: 800;
        color: var(--primary);
        font-size: 15px;
    }

    /* ── Animations ── */
    @keyframes shareCardIn {
        from { opacity: 0; transform: translateY(24px) scale(.98); }
        to   { opacity: 1; transform: none; }
    }

    .share-section:nth-child(2) { animation-delay: .08s; }
    .share-section:nth-child(3) { animation-delay: .14s; }
    .share-section:nth-child(4) { animation-delay: .20s; }
    .share-section:nth-child(5) { animation-delay: .26s; }

    /* Stagger children inside lists */
    .stagger-enter > * {
        opacity: 0;
        transform: translateY(12px);
        animation: shareItemIn .45s var(--ease-spring) forwards;
    }

    @keyframes shareItemIn {
        to { opacity: 1; transform: none; }
    }

    .share-file-preview { width: 100%; margin-bottom: 2px; }
    .share-file-preview-img img { max-height: 320px; object-fit: contain; width: 100%; }
    .share-file { flex-wrap: wrap; }

    @media (prefers-reduced-motion: reduce) {
        .share-hero, .share-section, .stagger-enter > * {
            animation: none; opacity: 1; transform: none;
        }
    }

    @media (max-width: 600px) {
        .share-hero { padding: 20px 18px 18px; }
        .share-section-head { padding: 14px 16px 12px; }
        .expense-list, .file-list { padding: 0 16px; }
        .chart-grid { padding: 16px; }
        .share-page { padding: 16px 12px 48px; }
    }
    </style>
</head>

<body>
<canvas id="bg-canvas"></canvas>

<div class="share-page">

    <!-- Top bar -->
    <div class="share-topbar">
        <span class="share-brand"><img src="/assets/icons/logo.png" alt="Mizan" style="height:24px;width:auto;vertical-align:middle;margin-inline-end:4px"> ميزان</span>
        <div class="share-topbar-right">
            <span class="share-readonly-pill">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1a5 5 0 0 1 5 5v2h1a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V10a2 2 0 0 1 2-2h1V6a5 5 0 0 1 5-5zm0 11a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm0-9a3 3 0 0 0-3 3v2h6V6a3 3 0 0 0-3-3z"/></svg>
                <span class="share-ro-text" data-i18n="read_only">عرض للقراءة فقط · Read Only</span>
            </span>
            <button class="share-ctrl" id="shareThemeBtn" onclick="toggleShareTheme()" title="تبديل المظهر" aria-label="Toggle theme">🌙</button>
            <button class="share-ctrl" id="shareLangBtn" onclick="toggleShareLang()" title="Switch language" aria-label="Switch language">EN</button>
        </div>
    </div>

    <!-- ====== Hero: Project Header ====== -->
    <div class="share-hero">
        <span class="share-status-badge"><?= $status_label ?></span>
        <div class="share-hero-top">
            <div class="share-icon-wrap"><?= $icon ?></div>
            <div>
                <div class="share-project-name"><?= htmlspecialchars($project['name']) ?></div>
                <div class="share-project-meta">
                    <?php if ($project['created_at']): ?>تاريخ الإنشاء: <?= date('Y/m/d', strtotime($project['created_at'])) ?><?php endif; ?>
                    <?php if ($project['description'] ?? ''): ?> · <?= htmlspecialchars(mb_strimwidth($project['description'] ?? '', 0, 80, '…')) ?><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="share-stats">
            <div class="share-stat">
                <div class="share-stat-val"><?= number_format($total_cost, 0) ?></div>
                <div class="share-stat-lbl" data-ar="إجمالي المصروفات (﷼)" data-en="Total Expenses (SAR)">إجمالي المصروفات (﷼)</div>
            </div>
            <div class="share-stat">
                <div class="share-stat-val"><?= count($expenses) ?></div>
                <div class="share-stat-lbl" data-ar="عدد العمليات" data-en="# Transactions">عدد العمليات</div>
            </div>
            <?php if ($budget > 0): ?>
            <div class="share-stat">
                <div class="share-stat-val"><?= number_format($budget, 0) ?></div>
                <div class="share-stat-lbl" data-ar="الميزانية (﷼)" data-en="Budget (SAR)">الميزانية (﷼)</div>
            </div>
            <?php endif; ?>
            <?php if ($profit !== null): ?>
            <div class="share-stat">
                <div class="share-stat-val" style="color:<?= $profit >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                    <?= ($profit >= 0 ? '+' : '') . number_format($profit, 0) ?>
                </div>
                <div class="share-stat-lbl" data-ar="الربح / الخسارة (﷼)" data-en="Profit / Loss (SAR)">الربح / الخسارة (﷼)</div>
            </div>
            <?php endif; ?>
            <?php if (count($proj_files) > 0): ?>
            <div class="share-stat">
                <div class="share-stat-val"><?= count($proj_files) ?></div>
                <div class="share-stat-lbl" data-ar="الملفات المرفقة" data-en="Attached Files">الملفات المرفقة</div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($budget > 0): ?>
        <?php
            $bar_clr = 'var(--primary)';
            if ($budget_pct > 90) $bar_clr = 'var(--danger)';
            elseif ($budget_pct > 75) $bar_clr = 'var(--warning)';
        ?>
        <div class="share-budget">
            <div class="share-budget-row">
                <span><?= number_format($total_cost, 0) ?> / <?= number_format($budget, 0) ?> ﷼</span>
                <strong><span data-ar="مستخدم" data-en="used">مستخدم</span> <?= $budget_pct ?>%</strong>
            </div>
            <div class="share-budget-track">
                <div class="share-budget-fill" id="budgetFill" style="width:0%; background:<?= $bar_clr ?>;"></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ====== Chart + Category Breakdown ====== -->
    <?php if (!empty($cat_totals)): ?>
    <div class="share-section">
        <div class="share-section-head">
            <h3 class="share-section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
                <span data-ar="تحليل المصروفات" data-en="Expense Analysis">تحليل المصروفات</span>
            </h3>
        </div>
        <div class="chart-grid">
            <div class="chart-canvas-wrap">
                <canvas id="expenseChart" width="200" height="200" aria-label="مخطط توزيع المصروفات" role="img"></canvas>
                <div class="chart-total-center">
                    <div class="chart-total-val"><?= number_format($total_cost, 0) ?></div>
                    <div class="chart-total-lbl" data-ar="﷼ إجمالي" data-en="Total SAR">﷼ إجمالي</div>
                </div>
            </div>
            <div class="chart-legend" id="chartLegend"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ====== Expense List ====== -->
    <?php if (!empty($expenses)): ?>
    <div class="share-section">
        <div class="share-section-head">
            <h3 class="share-section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/></svg>
                <span data-ar="المصروفات" data-en="Expenses">المصروفات</span>
            </h3>
            <span class="share-count-badge"><?= count($expenses) ?></span>
        </div>
        <div class="expense-list stagger-enter">
            <?php foreach ($expenses as $idx => $e): ?>
            <?php
                $dl = $e['warranty_id'] ? (int) $e['warranty_days_left'] : null;
                if ($dl !== null) {
                    if ($dl < 0)       { $wc = 'w-expired';  $wl = 'ضمان منتهي'; }
                    elseif ($dl <= 30) { $wc = 'w-expiring'; $wl = "ينتهي خلال {$dl} يوم"; }
                    else               { $wc = 'w-active';   $wl = "ضمان ساري ({$dl} يوم)"; }
                }
            ?>
            <div class="share-expense" style="--i:<?= $idx ?>; animation-delay:<?= $idx * 0.04 ?>s;">
                <div class="share-expense-info">
                    <div class="share-expense-title"><?= htmlspecialchars($e['title']) ?></div>
                    <div class="share-expense-meta">
                        <?php if ($e['category']): ?><span><?= htmlspecialchars($e['category']) ?></span><?php endif; ?>
                        <?php if ($e['purchase_date']): ?><span> · <?= htmlspecialchars($e['purchase_date']) ?></span><?php endif; ?>
                        <?php if ($e['vendor']): ?><span> · <?= htmlspecialchars($e['vendor']) ?></span><?php endif; ?>
                    </div>
                    <?php if ($dl !== null): ?>
                    <span class="share-warranty-pill <?= $wc ?>"><?= $wl ?></span>
                    <?php endif; ?>
                </div>
                <div class="share-expense-amount">
                    <?= number_format($e['amount'], 0) ?>
                    <small>﷼</small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="share-section" style="text-align:center; padding:48px 24px; color:var(--text-muted);">
        <span data-ar="لا توجد مصروفات مسجّلة في هذا المشروع" data-en="No expenses recorded for this project">لا توجد مصروفات مسجّلة في هذا المشروع</span>
    </div>
    <?php endif; ?>

    <!-- ====== File Vault ====== -->
    <?php if (!empty($proj_files)): ?>
    <div class="share-section">
        <div class="share-section-head">
            <h3 class="share-section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <span data-ar="مخزن الملفات" data-en="File Vault">مخزن الملفات</span>
            </h3>
            <span class="share-count-badge"><?= count($proj_files) ?></span>
        </div>
        <div class="file-list stagger-enter">
            <?php foreach ($proj_files as $idx => $f): ?>
            <?php
                $fpath     = $f['file_path'];
                $fname     = htmlspecialchars(basename($fpath));
                $fext      = strtolower(pathinfo($fpath, PATHINFO_EXTENSION));
                $furl      = htmlspecialchars('/' . ltrim($fpath, '/'), ENT_QUOTES, 'UTF-8');
                $ftype     = file_ext_label($fpath);
                $fsize     = (int) $f['file_size'];
                $fdate     = $f['uploaded_at'] ? date('Y/m/d', strtotime($f['uploaded_at'])) : '—';
                $is_img    = in_array($fext, ['jpg','jpeg','png','webp','gif','heic']);
                $is_pdf    = $fext === 'pdf';
            ?>
            <div class="share-file" style="animation-delay:<?= $idx * 0.04 ?>s;">
                <?php if ($is_img): ?>
                <div class="share-file-preview share-file-preview-img">
                    <img src="<?= $furl ?>" alt="<?= $fname ?>" loading="lazy"
                         style="max-width:100%;border-radius:6px;display:block;">
                </div>
                <?php elseif ($is_pdf): ?>
                <div class="share-file-preview share-file-preview-pdf">
                    <iframe src="<?= $furl ?>" loading="lazy"
                            style="width:100%;height:420px;border:0;border-radius:6px;"
                            title="<?= $fname ?>"></iframe>
                </div>
                <?php else: ?>
                <div class="share-file-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <?php endif; ?>
                <div style="flex:1; min-width:0; margin-top:<?= ($is_img || $is_pdf) ? '8px' : '0' ?>;">
                    <div class="share-file-name"><?= $fname ?></div>
                    <div class="share-file-meta">
                        <?= $fdate ?>
                        <?php if ($fsize > 0): ?> · <?= htmlspecialchars(fmt_size($fsize)) ?><?php endif; ?>
                    </div>
                </div>
                <span class="share-file-badge"><?= htmlspecialchars($ftype) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ====== Warranty Summary ====== -->
    <?php if ($warranty_active + $warranty_expiring + $warranty_expired > 0): ?>
    <div class="share-section">
        <div class="share-section-head">
            <h3 class="share-section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span data-ar="ملخص الضمانات" data-en="Warranty Summary">ملخص الضمانات</span>
            </h3>
        </div>
        <div class="warranty-stats">
            <div class="share-stats">
                <div class="share-stat">
                    <div class="share-stat-val" style="color:var(--success);"><?= $warranty_active ?></div>
                    <div class="share-stat-lbl" data-ar="ضمانات سارية" data-en="Active">ضمانات سارية</div>
                </div>
                <div class="share-stat">
                    <div class="share-stat-val" style="color:var(--warning);"><?= $warranty_expiring ?></div>
                    <div class="share-stat-lbl" data-ar="تنتهي قريباً" data-en="Expiring Soon">تنتهي قريباً</div>
                </div>
                <div class="share-stat">
                    <div class="share-stat-val" style="color:var(--danger);"><?= $warranty_expired ?></div>
                    <div class="share-stat-lbl" data-ar="منتهية" data-en="Expired">منتهية</div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="share-footer">
        <div class="share-footer-brand">
            <img src="/assets/icons/meta-banner1.png" alt="ميزان" style="height:500px;width:auto;vertical-align:middle;margin-inline-end:6px;"
                 onerror="this.replaceWith(Object.assign(document.createElement('span'),{textContent:'⚖️'}))">
            <span data-ar="ميزان" data-en="Mizan">ميزان</span>
        </div>
        <div style="margin-top:6px;" data-ar="أرشيفك المالي الشخصي — هذه الصفحة للقراءة فقط" data-en="Your personal financial archive — this page is read-only">أرشيفك المالي الشخصي — هذه الصفحة للقراءة فقط</div>
    </div>
</div>

<script>
// ── 1. Apply saved theme + lang immediately ──
(function() {
    const t = localStorage.getItem('mizan_theme') || 'light';
    document.documentElement.setAttribute('data-theme', t);
    const btn = document.getElementById('shareThemeBtn');
    if (btn) btn.textContent = t === 'dark' ? '☀️' : '🌙';

    const l = localStorage.getItem('mizan_lang') || 'ar';
    document.documentElement.setAttribute('lang', l);
    document.documentElement.setAttribute('dir', l === 'ar' ? 'rtl' : 'ltr');
    const lb = document.getElementById('shareLangBtn');
    if (lb) lb.textContent = l === 'ar' ? 'EN' : 'ع';
})();

// Toggles the share theme.
function toggleShareTheme() {
    const cur = document.documentElement.getAttribute('data-theme') || 'light';
    const next = cur === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('mizan_theme', next);
    const btn = document.getElementById('shareThemeBtn');
    if (btn) btn.textContent = next === 'dark' ? '☀️' : '🌙';
}

// Toggles the share lang.
function toggleShareLang() {
    const cur = document.documentElement.getAttribute('lang') || 'ar';
    const next = cur === 'ar' ? 'en' : 'ar';
    document.documentElement.setAttribute('lang', next);
    document.documentElement.setAttribute('dir', next === 'ar' ? 'rtl' : 'ltr');
    localStorage.setItem('mizan_lang', next);
    const btn = document.getElementById('shareLangBtn');
    if (btn) btn.textContent = next === 'ar' ? 'EN' : 'ع';
    // Translate static text that has data-lang attributes
    document.querySelectorAll('[data-ar][data-en]').forEach(el => {
        el.textContent = el.dataset[next] || el.textContent;
    });
}

// ── 2. Particle canvas (self-contained, no main.js dependency) ──
(function initCanvas() {
    const cv  = document.getElementById('bg-canvas');
    const ctx = cv.getContext('2d');
    let pts   = [];
    const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';

    // Defines the resize routine.
    function resize() { cv.width = innerWidth; cv.height = innerHeight; }

    // Defines the spawn routine.
    function spawn() {
        pts = [];
        const n = Math.max(20, Math.floor((cv.width * cv.height) / 15000));
        for (let i = 0; i < n; i++) {
            pts.push({
                x:  Math.random() * cv.width,
                y:  Math.random() * cv.height,
                vx: (Math.random() - .5) * .45,
                vy: (Math.random() - .5) * .45,
                r:  Math.random() * 1.8 + .8,
            });
        }
    }

    let raf;
    // Renders.
    function draw() {
        ctx.clearRect(0, 0, cv.width, cv.height);
        const dark = isDark();
        const base = dark ? '52,211,153' : '29,30,82';
        const MD   = 110;

        for (let i = 0; i < pts.length; i++) {
            const p = pts[i];
            p.x += p.vx; p.y += p.vy;
            if (p.x < 0 || p.x > cv.width)  p.vx *= -1;
            if (p.y < 0 || p.y > cv.height) p.vy *= -1;

            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(${base},.45)`;
            ctx.fill();

            for (let j = i + 1; j < pts.length; j++) {
                const q  = pts[j];
                const dx = p.x - q.x, dy = p.y - q.y;
                const d  = Math.sqrt(dx * dx + dy * dy);
                if (d < MD) {
                    ctx.beginPath();
                    ctx.moveTo(p.x, p.y);
                    ctx.lineTo(q.x, q.y);
                    ctx.strokeStyle = `rgba(${base},${(.14 * (1 - d / MD)).toFixed(3)})`;
                    ctx.lineWidth   = .7;
                    ctx.stroke();
                }
            }
        }
        raf = requestAnimationFrame(draw);
    }

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    resize(); spawn(); draw();
    setTimeout(() => cv.classList.add('ready'), 200);
    window.addEventListener('resize', () => { cancelAnimationFrame(raf); resize(); spawn(); draw(); });
})();

// ── 3. Stagger-enter: apply animation-delay from CSS var / index ──
document.querySelectorAll('.stagger-enter').forEach(function(list) {
    Array.from(list.children).forEach(function(child, i) {
        child.style.animationDelay = (i * 0.05) + 's';
    });
});

// ── 4. Budget fill animation (delayed so transition fires) ──
window.addEventListener('load', function() {
    const fill = document.getElementById('budgetFill');
    if (fill) setTimeout(() => { fill.style.width = fill.dataset.pct || fill.style.width; }, 400);
});
<?php if ($budget > 0): ?>
document.addEventListener('DOMContentLoaded', function() {
    const fill = document.getElementById('budgetFill');
    if (!fill) return;
    const pct = <?= (int)$budget_pct ?>;
    setTimeout(() => { fill.style.width = pct + '%'; }, 300);
});
<?php endif; ?>

// ── 5. Chart.js expense doughnut ──
<?php if (!empty($cat_totals)): ?>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') return;

    const labels  = <?= json_encode($chart_labels, JSON_UNESCAPED_UNICODE) ?>;
    const values  = <?= json_encode($chart_values) ?>;
    const total   = <?= json_encode($total_cost) ?>;

    const PALETTE = [
        '#3B82F6','#10B981','#F59E0B','#EF4444',
        '#8B5CF6','#EC4899','#0EA5E9','#F97316',
        '#14B8A6','#6366F1','#84CC16','#06B6D4',
    ];

    const colors = labels.map((_, i) => PALETTE[i % PALETTE.length]);
    const cs     = getComputedStyle(document.documentElement);
    const txtClr = cs.getPropertyValue('--text').trim()      || '#0F172A';
    const bdrClr = cs.getPropertyValue('--border').trim()    || 'rgba(0,0,0,.1)';
    const surfClr= cs.getPropertyValue('--surface').trim()   || '#E8E4DA';

    const ctx = document.getElementById('expenseChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: colors,
                borderColor: surfClr,
                borderWidth: 2,
                hoverOffset: 6,
            }],
        },
        options: {
            cutout: '68%',
            responsive: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            const pct = total > 0 ? Math.round(ctx.parsed / total * 100) : 0;
                            return ' ' + ctx.parsed.toLocaleString('ar-SA') + ' ﷼ (' + pct + '%)';
                        }
                    }
                },
            },
        },
    });

    // Build accessible legend via DOM (no innerHTML)
    const legend = document.getElementById('chartLegend');
    if (!legend) return;
    labels.forEach(function(lbl, i) {
        const pct = total > 0 ? Math.round(values[i] / total * 100) : 0;

        const row  = document.createElement('div');
        row.className = 'chart-legend-item';

        const dot  = document.createElement('div');
        dot.className = 'chart-legend-dot';
        dot.style.background = colors[i];

        const name = document.createElement('div');
        name.className = 'chart-legend-name';
        name.textContent = lbl;

        const barWrap = document.createElement('div');
        barWrap.className = 'chart-legend-bar-wrap';
        const bar = document.createElement('div');
        bar.className = 'chart-legend-bar';
        bar.style.background = colors[i];
        bar.style.width = '0%';
        setTimeout(function() { bar.style.width = pct + '%'; }, 400 + i * 60);
        barWrap.appendChild(bar);

        const pctEl = document.createElement('div');
        pctEl.className = 'chart-legend-pct';
        pctEl.textContent = pct + '%';

        row.appendChild(dot);
        row.appendChild(name);
        row.appendChild(barWrap);
        row.appendChild(pctEl);
        legend.appendChild(row);
    });
});
<?php endif; ?>
</script>
</body>
</html>
