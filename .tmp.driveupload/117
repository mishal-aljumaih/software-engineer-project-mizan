<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: index.php
 * PURPOSE: Main authenticated user dashboard and page router.
 * OWNER: Mishal Al-jumaih - ScrumMaster & Development Member (Full Stack All Rounder)
 * ========================================================================
 */
require_once 'includes/security.php';
mz_send_security_headers();
mz_session_start();

// Auth gate — anonymous visitors are bounced to the welcome/marketing page
if (!isset($_SESSION['user_id'])) {
    header("Location: welcome.php");
    exit;
}

require_once 'includes/auth.php';
require_once 'config/db.php';
$pdo = getDB();

// ===== Dashboard KPI queries =====
// Every query is parameterized with the current user's ID so users can never see another's data

// Sum of every expense across all of this user's projects (the headline "total spent" tile)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(e.amount), 0)
    FROM expenses e
    JOIN projects p ON e.project_id = p.id
    WHERE p.user_id = ?
");
$stmt->execute([$current_user_id]);
$total_expenses = $stmt->fetchColumn();

// عدد المشاريع النشطة
$stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE user_id = ? AND status = 'active'");
$stmt->execute([$current_user_id]);
$active_projects = $stmt->fetchColumn();

// Profit tile is only shown for users who have at least one commercial project (sell_price > 0)
// — keeps the personal-use case uncluttered with business metrics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE user_id = ? AND sell_price > 0");
$stmt->execute([$current_user_id]);
$has_commercial = $stmt->fetchColumn() > 0;

$total_profit = 0;
if ($has_commercial) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(p.sell_price - (
            SELECT COALESCE(SUM(e2.amount), 0)
            FROM expenses e2 WHERE e2.project_id = p.id
        )), 0)
        FROM projects p
        WHERE p.user_id = ? AND p.status = 'done' AND p.sell_price > 0
    ");
    $stmt->execute([$current_user_id]);
    $total_profit = $stmt->fetchColumn();
}

// Warranty-soon counter — counts items whose end_date falls within the next 30 days (drives the alert tile)
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM warranties w
    JOIN expenses e ON w.expense_id = e.id
    JOIN projects p ON e.project_id = p.id
    WHERE p.user_id = ?
      AND w.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
");
$stmt->execute([$current_user_id]);
$expiring_warranties = $stmt->fetchColumn();

// ===== آخر 5 مشاريع =====
$stmt = $pdo->prepare("
    SELECT p.*,
           COALESCE(SUM(e.amount), 0) AS total_cost
    FROM projects p
    LEFT JOIN expenses e ON e.project_id = p.id
    WHERE p.user_id = ?
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 5
");
$stmt->execute([$current_user_id]);
$recent_projects = $stmt->fetchAll();

// ===== بيانات الرسم البياني (مصاريف آخر 6 أشهر) =====
$stmt = $pdo->prepare("
    SELECT
        DATE_FORMAT(e.purchase_date, '%Y-%m') AS month,
        COALESCE(SUM(e.amount), 0)            AS total
    FROM expenses e
    JOIN projects p ON e.project_id = p.id
    WHERE p.user_id = ?
      AND e.purchase_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month ASC
");
$stmt->execute([$current_user_id]);
$chart_raw = $stmt->fetchAll();

// Reshape the monthly aggregates into parallel label/value arrays that Chart.js consumes directly
$chart_labels = [];
$chart_data   = [];
$months_ar = ['01'=>'يناير','02'=>'فبراير','03'=>'مارس','04'=>'أبريل',
              '05'=>'مايو','06'=>'يونيو','07'=>'يوليو','08'=>'أغسطس',
              '09'=>'سبتمبر','10'=>'أكتوبر','11'=>'نوفمبر','12'=>'ديسمبر'];

foreach ($chart_raw as $row) {
    $parts          = explode('-', $row['month']);
    $chart_labels[] = $months_ar[$parts[1]] . ' ' . $parts[0];
    $chart_data[]   = (float)$row['total'];
}

// ===== ضمانات توشك على الانتهاء =====
$stmt = $pdo->prepare("
    SELECT w.*, e.title AS expense_title,
           p.name AS project_name,
           DATEDIFF(w.end_date, CURDATE()) AS days_left
    FROM warranties w
    JOIN expenses e ON w.expense_id = e.id
    JOIN projects p ON e.project_id = p.id
    WHERE p.user_id = ?
      AND w.end_date >= CURDATE()
      AND DATEDIFF(w.end_date, CURDATE()) <= 30
    ORDER BY days_left ASC
    LIMIT 5
");
$stmt->execute([$current_user_id]);
$expiring_list = $stmt->fetchAll();

$page_title = 'لوحة التحكم';
// includes/header.php emits the opening <html>, navbar, and <main> — this file then prints body content
require_once 'includes/header.php';
// ─────────────────────────────────────────────────────────────────────────
// END PHP DATA-FETCHING — BEGIN HTML/CHART VIEW for the dashboard
// ─────────────────────────────────────────────────────────────────────────

// دالة مساعدة لتحديد نوع المشروع
function projectTypeLabel($type) {
    $types = ['car'=>'🚗 سيارة','house'=>'🏠 منزل','occasion'=>'🎉 مناسبة',
              'work'=>'💼 عمل','devices'=>'📦 أجهزة','custom'=>'✏️ مخصص'];
    return $types[$type] ?? '✏️ مخصص';
}
// Defines the statusLabel routine.
function statusLabel($status) {
    $s = ['active'=>['label'=>'نشط','class'=>'badge-success'],
          'done'  =>['label'=>'مكتمل','class'=>'badge-info'],
          'archived'=>['label'=>'مؤرشف','class'=>'badge-neutral']];
    return $s[$status] ?? $s['active'];
}
?>

<!-- ===== محتوى الداشبورد ===== -->
<header class="page-header reveal dashboard-header">
    <div>
        <h1 class="page-title" data-i18n="dashboard">لوحة التحكم</h1>
        <p class="text-muted" id="greetingText">مرحباً، <?= htmlspecialchars($current_user_name, ENT_QUOTES, 'UTF-8') ?> 👋</p>
    </div>
    <!-- "New Project" button — opens the create-project modal (defined in main.js) -->
    <button type="button" class="btn btn-primary" onclick="window.openProjectCreate()"
            aria-label="<?= htmlspecialchars($current_user_name, ENT_QUOTES, 'UTF-8') ?> — Create new project">
        <span aria-hidden="true">＋</span> <span data-i18n="add_project">مشروع جديد</span>
    </button>
</header>

<!-- ===== بطاقات الإحصائيات ===== -->
<section class="grid-4 stagger-enter" style="margin-bottom: 28px;" aria-label="Dashboard summary statistics">

    <div class="stat-card reveal">
        <div class="stat-icon green">💸</div>
        <div>
            <div class="stat-label" data-i18n="total_expenses">إجمالي المصاريف</div>
            <div class="stat-value">
                <span class="count-up" data-target="<?= $total_expenses ?>">0</span>
                <span class="currency-symbol" style="font-size:13px; color:var(--text-muted); font-weight:500;"> <?= mz_currency_symbol() ?></span>
            </div>
        </div>
    </div>

    <div class="stat-card reveal">
        <div class="stat-icon blue">◈</div>
        <div>
            <div class="stat-label" data-i18n="active_projects">مشاريع نشطة</div>
            <div class="stat-value">
                <span class="count-up" data-target="<?= $active_projects ?>">0</span>
            </div>
        </div>
    </div>

    <?php if ($has_commercial): ?>
    <div class="stat-card reveal">
        <div class="stat-icon <?= $total_profit >= 0 ? 'green' : 'red' ?>">⚖️</div>
        <div>
            <div class="stat-label" data-i18n="total_profit">الربح الإجمالي</div>
            <div class="stat-value <?= $total_profit >= 0 ? 'text-success' : 'text-danger' ?>">
                <span class="count-up" data-target="<?= $total_profit ?>">0</span>
                <span class="currency-symbol" style="font-size:13px; font-weight:500;"> <?= mz_currency_symbol() ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="stat-card reveal">
        <div class="stat-icon <?= $expiring_warranties > 0 ? 'gold' : 'green' ?>">🛡️</div>
        <div>
            <div class="stat-label" data-i18n="expiring_warranties">ضمانات قاربت الانتهاء</div>
            <div class="stat-value <?= $expiring_warranties > 0 ? 'text-warning' : '' ?>">
                <span class="count-up" data-target="<?= $expiring_warranties ?>">0</span>
            </div>
        </div>
    </div>

</section>

<!-- ===== الصف الثاني: الرسم البياني + الضمانات ===== -->
<section class="grid-2" style="margin-bottom: 28px; align-items: start;" aria-label="Charts and warranties">

    <!-- الرسم البياني -->
    <article class="card reveal" aria-label="Expenses chart">
        <div class="card-header">
            <span class="card-title">📊 <span data-i18n="expenses_chart">المصاريف — آخر 6 أشهر</span></span>
            <select class="chart-filter ux-control" id="chartFilter" onchange="updateChart(this.value)">
                <option value="6" data-i18n="months_6">6 أشهر</option>
                <option value="3" data-i18n="months_3">3 أشهر</option>
                <option value="12" data-i18n="months_12">12 شهر</option>
            </select>
        </div>
        <div style="position:relative; height: 240px;">
            <?php if (empty($chart_data)): ?>
            <div class="empty-state" style="padding: 40px 0;">
                <div class="empty-icon">📊</div>
                <p data-i18n="no_chart_data">لا توجد بيانات بعد. ابدأ بإضافة مصاريف!</p>
            </div>
            <?php else: ?>
            <canvas id="expensesChart"></canvas>
            <?php endif; ?>
        </div>
    </article>

    <!-- ضمانات توشك على الانتهاء -->
    <article class="card reveal" aria-label="Expiring warranties">
        <div class="card-header">
            <span class="card-title">🛡️ <span data-i18n="expiring_soon">ضمانات تنتهي قريباً</span></span>
            <a href="pages/warranties.php" class="section-link" data-i18n="view_all">عرض
                الكل</a>
        </div>
        <?php if (empty($expiring_list)): ?>
        <div class="empty-state" style="padding: 30px 0;">
            <div class="empty-icon">✅</div>
            <p data-i18n="all_warranties_valid">كل الضمانات سارية، لا توجد تنبيهات</p>
        </div>
        <?php else: ?>
        <div class="warranty-list">
            <?php foreach ($expiring_list as $w): ?>
            <div class="warranty-item">
                <div class="warranty-info">
                    <div class="warranty-name"><?= htmlspecialchars($w['expense_title']) ?></div>
                    <div class="warranty-project text-muted"><?= htmlspecialchars($w['project_name']) ?></div>
                </div>
                <div class="warranty-days <?= $w['days_left'] <= 7 ? 'critical' : 'warning' ?>">
                    <span class="status-dot expiring"></span>
                    <?= $w['days_left'] ?> <span data-i18n="days">يوم</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </article>

</section>

<!-- ===== آخر المشاريع ===== -->
<section class="card reveal" aria-label="Recent projects">
    <div class="card-header">
        <span class="card-title">🗂️ <span data-i18n="recent_projects">آخر المشاريع</span></span>
        <a href="pages/projects.php" class="section-link" data-i18n="view_all">عرض الكل</a>
    </div>

    <?php if (empty($recent_projects)): ?>
    <div class="empty-state">
        <div class="empty-icon">🗂️</div>
        <h3 data-i18n="no_projects">لا توجد مشاريع بعد</h3>
        <p data-i18n="start_first_project">ابدأ بإنشاء أول مشروع لك!</p>
        <a href="pages/projects.php?new=1" class="btn btn-primary" style="margin-top:16px;">＋ <span
                data-i18n="add_project">مشروع جديد</span></a>
    </div>
    <?php else: ?>
    <div class="projects-table-wrap">
        <table>
            <thead>
                <tr>
                    <th data-i18n="th_project">المشروع</th>
                    <th data-i18n="th_type">النوع</th>
                    <th data-i18n="th_status">الحالة</th>
                    <th data-i18n="th_total_cost">التكلفة الإجمالية</th>
                    <?php if ($has_commercial): ?><th data-i18n="th_profit_loss">الربح / الخسارة</th><?php endif; ?>
                    <th data-i18n="th_date">التاريخ</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_projects as $proj):
                    $profit    = $proj['sell_price'] > 0 ? ($proj['sell_price'] - $proj['total_cost']) : null;
                    $statusInfo = statusLabel($proj['status']);
                ?>
                <tr class="table-row-hover" onclick="window.location='/pages/project-detail.php?id=<?= $proj['id'] ?>'"
                    style="cursor:pointer;">
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div class="project-dot"
                                style="width:10px;height:10px;border-radius:50%;background:<?= htmlspecialchars($proj['color']) ?>;flex-shrink:0;">
                            </div>
                            <strong><?= htmlspecialchars($proj['name']) ?></strong>
                        </div>
                    </td>
                    <td><span style="font-size:13px;"><?= projectTypeLabel($proj['type']) ?></span></td>
                    <td><span class="badge <?= $statusInfo['class'] ?>"><?= $statusInfo['label'] ?></span></td>
                    <td>
                        <span class="amount" style="font-size:15px;">
                            <?= number_format($proj['total_cost'], 0) ?>
                        </span>
                    </td>
                    <?php if ($has_commercial): ?>
                    <td>
                        <?php if ($profit !== null): ?>
                        <span style="font-weight:700; color:<?= $profit >= 0 ? 'var(--success)' : 'var(--danger)' ?>">
                            <?= $profit >= 0 ? '▲' : '▼' ?>
                            <?= mz_format_money(abs($profit), 0) ?>
                        </span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td class="text-muted" style="font-size:13px;">
                        <?= date('d/m/Y', strtotime($proj['created_at'])) ?>
                    </td>
                    <td>
                        <a href="/pages/project-detail.php?id=<?= $proj['id'] ?>" class="btn btn-ghost btn-sm"
                            onclick="event.stopPropagation()" data-i18n="details">تفاصيل ←</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<!-- ===== CSS خاص بالداشبورد ===== -->
<style>
/* Light theme only visual depth */
html:not([data-theme='dark']) .main-content {
    position: relative;
    isolation: isolate;
    background:
        linear-gradient(180deg, color-mix(in srgb, var(--surface) 92%, var(--bg) 8%) 0%, var(--bg) 100%);
}

html:not([data-theme='dark']) .card,
html:not([data-theme='dark']) .stat-card {
    background:
        linear-gradient(180deg, color-mix(in srgb, var(--surface) 96%, var(--bg) 4%) 0%, color-mix(in srgb, var(--surface) 90%, var(--bg) 10%) 100%);
    border-color: color-mix(in srgb, var(--border) 82%, var(--surface) 18%);
    box-shadow: 0 12px 28px rgba(15, 23, 42, .06), 0 1px 0 rgba(255, 255, 255, .55) inset;
}

html:not([data-theme='dark']) .main-content::before {
    content: "";
    position: absolute;
    inset: 0;
    z-index: -1;
    pointer-events: none;
    background:
        radial-gradient(700px 280px at 100% -5%, color-mix(in srgb, var(--primary) 8%, transparent), transparent 65%),
        radial-gradient(560px 220px at 0% 0%, color-mix(in srgb, var(--secondary) 8%, transparent), transparent 70%),
        repeating-linear-gradient(45deg, rgba(15, 23, 42, .013) 0 1px, transparent 1px 6px);
}

.dashboard-header {
    margin-bottom: 22px;
}

/* Shared button/control behavior (light mode only) */
html:not([data-theme='dark']) .btn {
    border-radius: var(--radius-sm);
    transition: transform .2s ease, box-shadow .2s ease, filter .2s ease, background-color .2s ease, border-color .2s ease;
    will-change: transform;
}

html:not([data-theme='dark']) .btn:hover {
    transform: translateY(-1px) scale(1.01);
}

html:not([data-theme='dark']) .btn:hover {
    box-shadow: 0 10px 22px rgba(15, 23, 42, .11);
    background-color: color-mix(in srgb, var(--surface) 88%, var(--primary) 12%);
}

html:not([data-theme='dark']) .btn:active,
html:not([data-theme='dark']) .btn.is-pressed {
    transform: translateY(0) scale(.985);
    box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
}

html:not([data-theme='dark']) .btn:focus-visible,
html:not([data-theme='dark']) .ux-control:focus-visible {
    outline: 2px solid color-mix(in srgb, var(--primary) 42%, transparent);
    outline-offset: 2px;
}

.section-link {
    font-size: 13px;
    color: var(--secondary);
    text-decoration: none;
    transition: color .18s ease, opacity .18s ease;
}

.section-link:hover {
    color: var(--primary);
    opacity: .95;
}

html:not([data-theme='dark']) .ux-control {
    font-size: 13px;
    padding: 6px 11px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text);
    font-family: 'Tajawal', sans-serif;
    transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
}

html:not([data-theme='dark']) .ux-control:hover {
    border-color: var(--primary);
    transform: translateY(-1px);
}

html:not([data-theme='dark']) .card-header .card-title {
    color: color-mix(in srgb, var(--text) 88%, var(--primary) 12%);
}

html:not([data-theme='dark']) .text-muted {
    color: color-mix(in srgb, var(--text-muted) 88%, var(--text) 12%);
}

/* Warranty List */
.warranty-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.warranty-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    background: var(--bg);
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    transition: all 0.2s ease;
}

.warranty-item:hover {
    border-color: var(--warning);
    transform: translateX(-3px);
}

.warranty-name {
    font-weight: 600;
    font-size: 14px;
    color: var(--text);
}

.warranty-project {
    font-size: 12px;
    margin-top: 2px;
}

.warranty-days {
    font-size: 13px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

.warranty-days.warning {
    background: rgba(217, 119, 6, 0.12);
    color: var(--warning);
}

.warranty-days.critical {
    background: rgba(239, 68, 68, 0.12);
    color: var(--danger);
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    background: var(--warning);
    flex-shrink: 0;
}

.status-dot.expiring.critical {
    background: var(--danger);
}

/* Table */
.projects-table-wrap {
    overflow-x: auto;
    margin: -4px -4px 0;
}

.table-row-hover:hover td {
    background: var(--bg);
}

.table-row-hover td {
    transition: background-color .2s ease;
}

/* Greeting time */
#greetingText {
    transition: opacity 0.3s ease;
}
</style>

<!-- ===== Chart.js ===== -->
<?php if (!empty($chart_data)): ?>
<script>
const chartLabels = <?= json_encode($chart_labels) ?>;
const chartData   = (<?= json_encode($chart_data)   ?>).map(Number);
// Fallback if main.js hasn't loaded yet (footer scripts load after this block)
window.getCurrencySymbol = window.getCurrencySymbol || function () {
    var c = (window.MIZAN_CURRENCY || 'SAR').toUpperCase();
    var lang = document.documentElement.lang || 'ar';
    if (c === 'SAR') return lang === 'en' ? 'SAR' : '﷼';
    if (c === 'USD') return '$';
    return c;
};

// Chart.js does not resolve CSS variables on its own — it sees the literal
// string "var(--border)" and silently falls back to defaults. We resolve
// the theme tokens through getComputedStyle() so charts always match the
// active light/dark palette.
function readThemeTokens() {
    const cs = getComputedStyle(document.documentElement);
    return {
        text:       cs.getPropertyValue('--text').trim()       || '#0F172A',
        textMuted:  cs.getPropertyValue('--text-muted').trim() || '#475569',
        border:     cs.getPropertyValue('--border').trim()     || '#CBD5E1',
        surface:    cs.getPropertyValue('--surface').trim()    || '#ffffff',
        primary:    cs.getPropertyValue('--primary').trim()    || '#0F172A',
        success:    cs.getPropertyValue('--success').trim()    || '#059669',
    };
}

const ctx = document.getElementById('expensesChart').getContext('2d');
let expensesChart;

// Builds the expenses chart.
function buildExpensesChart() {
    const tk = readThemeTokens();
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const accent = isDark ? tk.success : tk.primary;

    const gradient = ctx.createLinearGradient(0, 0, 0, 240);
    gradient.addColorStop(0, accent + (isDark ? '40' : '33'));
    gradient.addColorStop(1, accent + '00');

    if (expensesChart) {
        try { expensesChart.destroy(); } catch (e) {}
    }

    expensesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'المصاريف (' + getCurrencySymbol() + ')',
                data: chartData.map(Number),
                borderColor: accent,
                backgroundColor: gradient,
                borderWidth: 2.5,
                pointBackgroundColor: accent,
                pointBorderColor: tk.surface,
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
                fill: true,
                tension: 0.4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    rtl: true,
                    textDirection: 'rtl',
                    backgroundColor: tk.surface,
                    titleColor: tk.text,
                    bodyColor: tk.textMuted,
                    borderColor: tk.border,
                    borderWidth: 1,
                    padding: 12,
                    callbacks: {
                        label: ctx => '  ' + Number(ctx.raw).toLocaleString('en') + ' ' + getCurrencySymbol()
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: tk.border, drawBorder: false },
                    ticks: {
                        color: tk.textMuted,
                        font: { family: 'Tajawal', size: 12 }
                    }
                },
                y: {
                    grid: { color: tk.border, drawBorder: false },
                    ticks: {
                        color: tk.textMuted,
                        font: { family: 'Tajawal', size: 12 },
                        callback: v => Number(v).toLocaleString('en') + ' ' + getCurrencySymbol()
                    },
                    beginAtZero: true
                }
            },
            interaction: { intersect: false, mode: 'index' },
            animation: { duration: 1200, easing: 'easeInOutQuart' }
        }
    });
}

buildExpensesChart();

// Rebuild on theme flip so the chart palette stays in sync.
window.addEventListener('mizan:theme-change', buildExpensesChart);
</script>
<?php endif; ?>

<!-- ===== JS الداشبورد ===== -->
<script>
// Count-up animation — eases each stat-card number from 0 to its target over ~1.4s for visual polish
function countUp(el) {
    const target = parseFloat(el.dataset.target) || 0;
    const duration = 1400;
    const start = performance.now();
    const isFloat = target % 1 !== 0;

    // Defines the step routine.
    function step(now) {
        const elapsed = now - start;
        const progress = Math.min(elapsed / duration, 1);
        // Ease out quart
        const eased = 1 - Math.pow(1 - progress, 4);
        const current = target * eased;
        el.textContent = isFloat ?
            Number(current.toFixed(0)).toLocaleString('en') :
            Math.round(current).toLocaleString('en');
        if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}

// ===== Scroll Reveal =====
const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry, i) => {
        if (entry.isIntersecting) {
            setTimeout(() => {
                entry.target.classList.add('visible');
                // Count-up لأرقام الإحصائيات
                entry.target.querySelectorAll('.count-up').forEach(countUp);
            }, i * 80);
            revealObserver.unobserve(entry.target);
        }
    });
}, {
    threshold: 0.1
});

document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

// Time-of-day greeting — picks an Arabic salutation (morning/afternoon/evening) based on the user's local clock
function setGreeting() {
    const h = new Date().getHours();
    const name = '<?= htmlspecialchars($current_user_name) ?>';
    let msg;
    if (h < 6) msg = `طيبة ليلتك، ${name} 🌙`;
    else if (h < 12) msg = `صباح الخير، ${name} ☀️`;
    else if (h < 17) msg = `مرحباً، ${name} 👋`;
    else if (h < 21) msg = `مساء الخير، ${name} 🌆`;
    else msg = `أهلاً، ${name} 🌙`;
    document.getElementById('greetingText').textContent = msg;
}
setGreeting();

// Unified click feedback for all actionable buttons
document.querySelectorAll('.btn').forEach((btn) => {
    btn.addEventListener('pointerdown', () => btn.classList.add('is-pressed'));
    btn.addEventListener('pointerup', () => btn.classList.remove('is-pressed'));
    btn.addEventListener('pointerleave', () => btn.classList.remove('is-pressed'));
});

// ===== Navbar scroll shadow =====
window.addEventListener('scroll', () => {
    document.getElementById('mainNavbar')
        .classList.toggle('scrolled', window.scrollY > 10);
}, {
    passive: true
});
</script>

<?php require_once 'includes/footer.php'; ?>