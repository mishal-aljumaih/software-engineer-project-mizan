<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: warranties.php
 * PURPOSE: Warranty tracking screen with expiry alerts.
 * OWNER: Alwaleed Alzahrani - Development & Database Admin (Lead Programmer)
 * ========================================================================
 */
require_once '../includes/auth.php';
require_once '../config/db.php';

$pdo        = getDB();
$page_title = 'الضمانات';

// ─── فلاتر ────────────────────────────────────────────────
$filter     = $_GET['filter']  ?? 'all'; // all | active | expiring | expired
$search     = trim($_GET['q']  ?? '');
$project_id = intval($_GET['project'] ?? 0);

// قائمة المشاريع (للتصفية + اسم المشروع المختار)
$projects_stmt = $pdo->prepare("SELECT id, name FROM projects WHERE user_id = ? ORDER BY name");
$projects_stmt->execute([$current_user_id]);
$projects = $projects_stmt->fetchAll();

// ─── جلب الضمانات ─────────────────────────────────────────
$where  = "WHERE p.user_id = ?";
$params = [$current_user_id];

if ($project_id > 0) {
    $where   .= " AND p.id = ?";
    $params[] = $project_id;
}
if ($search !== '') {
    $where   .= " AND e.title LIKE ?";
    $params[] = "%{$search}%";
}

// Apply status filter — only known values are appended (no user-controlled SQL is added)
switch ($filter) {
    // "Active" — warranty end date is more than 30 days away (healthy)
    case 'active':
        $where .= " AND w.end_date > CURDATE() AND DATEDIFF(w.end_date, CURDATE()) > 30";
        break;
    case 'expiring':
        $where .= " AND DATEDIFF(w.end_date, CURDATE()) BETWEEN 0 AND 30";
        break;
    case 'expired':
        $where .= " AND w.end_date < CURDATE()";
        break;
}

$stmt = $pdo->prepare("
    SELECT w.*,
           e.title       AS item_name,
           e.amount,
           e.vendor,
           e.purchase_date,
           p.id          AS project_id,
           p.name        AS project_name,
           p.color       AS project_color,
           DATEDIFF(w.end_date, CURDATE()) AS days_left
    FROM warranties w
    JOIN expenses e ON e.id = w.expense_id
    JOIN projects p ON p.id = e.project_id
    {$where}
    ORDER BY days_left ASC
");
$stmt->execute($params);
$warranties = $stmt->fetchAll();

// ─── إحصائيات ─────────────────────────────────────────────
$stats = $pdo->prepare("
    SELECT
        COUNT(*)                                                  AS total,
        SUM(CASE WHEN w.end_date > CURDATE()
                  AND DATEDIFF(w.end_date,CURDATE()) > 30   THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN DATEDIFF(w.end_date,CURDATE()) BETWEEN 0 AND 30 THEN 1 ELSE 0 END) AS expiring,
        SUM(CASE WHEN w.end_date < CURDATE()                THEN 1 ELSE 0 END) AS expired
    FROM warranties w
    JOIN expenses e ON e.id = w.expense_id
    JOIN projects p ON p.id = e.project_id
    WHERE p.user_id = ?
");
$stats->execute([$current_user_id]);
$st = $stats->fetch();

require_once '../includes/header.php';
?>

<div style="margin-bottom:8px;">
    <a href="projects.php" class="pd-back" data-i18n="back_to_projects">← المشاريع</a>
</div>
<div class="page-header">
    <div class="ph-right">
        <h1 class="page-title">🛡️ <span data-i18n="warranties">الضمانات</span></h1>
        <p class="page-sub" data-i18n="warranties_desc">تابع كل ضماناتك وتواريخ انتهائها</p>
    </div>
</div>

<!-- ═══ Stats ═══ -->
<div class="stats-row">
    <div class="stat-card" style="cursor:pointer" onclick="location.href='?filter=all'">
        <div class="stat-icon">🛡️</div>
        <div class="stat-body">
            <div class="stat-num"><?= $st['total'] ?></div>
            <div class="stat-lbl" data-i18n="war_total">إجمالي الضمانات</div>
        </div>
    </div>
    <div class="stat-card" style="cursor:pointer" onclick="location.href='?filter=active'">
        <div class="stat-icon">✅</div>
        <div class="stat-body">
            <div class="stat-num" style="color:#22C55E"><?= $st['active'] ?></div>
            <div class="stat-lbl" data-i18n="war_active">ضمان نشط</div>
        </div>
    </div>
    <div class="stat-card" style="cursor:pointer" onclick="location.href='?filter=expiring'">
        <div class="stat-icon">⚠️</div>
        <div class="stat-body">
            <div class="stat-num" style="color:#D97706"><?= $st['expiring'] ?></div>
            <div class="stat-lbl" data-i18n="war_expiring">ينتهي قريباً (30 يوم)</div>
        </div>
    </div>
    <div class="stat-card" style="cursor:pointer" onclick="location.href='?filter=expired'">
        <div class="stat-icon">❌</div>
        <div class="stat-body">
            <div class="stat-num" style="color:#EF4444"><?= $st['expired'] ?></div>
            <div class="stat-lbl" data-i18n="war_expired">منتهي الصلاحية</div>
        </div>
    </div>
</div>

<!-- ═══ Filters ═══ -->
<div class="filter-bar">
    <form method="GET" action="" class="filter-form">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="بحث في الضمانات..."
            class="filter-input" data-i18n-placeholder="search_warranties" onchange="this.form.submit()">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">

        <select name="project" class="filter-select" onchange="this.form.submit()">
            <option value="0" data-i18n="all_projects">كل المشاريع</option>
            <?php foreach ($projects as $pr): ?>
            <option value="<?= $pr['id'] ?>" <?= $project_id == $pr['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($pr['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>

        <div class="filter-tabs">
            <?php $tabs = [
                'all'      => ['icon' => '',    'ar' => 'الكل',          'i18n' => 'tab_all'],
                'active'   => ['icon' => '✅ ', 'ar' => 'نشط',           'i18n' => 'war_filter_active'],
                'expiring' => ['icon' => '⚠️ ', 'ar' => 'ينتهي قريباً',  'i18n' => 'war_filter_expiring'],
                'expired'  => ['icon' => '❌ ', 'ar' => 'منتهي',          'i18n' => 'war_filter_expired'],
            ]; ?>
            <?php foreach ($tabs as $k => $td): ?>
            <a href="?filter=<?= $k ?>&q=<?= urlencode($search) ?>&project=<?= $project_id ?>"
                class="ftab <?= $filter===$k ? 'on' : '' ?>"><?= $td['icon'] ?><span data-i18n="<?= $td['i18n'] ?>"><?= $td['ar'] ?></span></a>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="btn btn-secondary">🔍 <span data-i18n="search">بحث</span></button>
    </form>
</div>

<!-- ═══ Warranties List ═══ -->
<?php if (empty($warranties)): ?>
<div class="empty-state">
    <div class="empty-icon">🛡️</div>
    <h3 data-i18n="no_warranties">لا توجد ضمانات</h3>
    <p data-i18n="warranties_hint">الضمانات تُضاف عند إدخال مصاريف في صفحة المشروع</p>
    <a href="/pages/projects.php" class="btn btn-primary" data-i18n="go_to_projects">الذهاب للمشاريع</a>
</div>

<?php else: ?>
<div class="warranties-list">
    <?php foreach ($warranties as $w):
        $days    = (int)$w['days_left'];
        $expired = $days < 0;
        $urgent  = !$expired && $days <= 7;
        $warning = !$expired && !$urgent && $days <= 30;
        $healthy = !$expired && $days > 30;

        $status_class = $expired ? 'status-expired'
                      : ($urgent  ? 'status-urgent'
                      : ($warning ? 'status-warning'
                      :             'status-healthy'));
        $status_label = $expired ? 'منتهي'
                      : ($urgent  ? "عاجل: {$days} يوم"
                      : ($warning ? "ينتهي خلال {$days} يوم"
                      :             'نشط'));

        // حساب نسبة شريط التقدم
        $total_days = max(1, (int)((strtotime($w['end_date']) - strtotime($w['start_date'] ?? $w['purchase_date'])) / 86400));
        $elapsed    = max(0, $total_days - max(0, $days));
        $pct        = min(100, round(($elapsed / $total_days) * 100));
        $bar_color  = $expired ? '#EF4444' : ($urgent ? '#F97316' : ($warning ? '#EAB308' : '#22C55E'));
    ?>
    <div class="w-card <?= $status_class ?>" data-id="<?= $w['id'] ?>">
        <div class="w-left">
            <!-- Status dot + icon -->
            <div class="w-icon-wrap">
                <span class="w-icon">🛡️</span>
                <span class="w-dot"></span>
            </div>
        </div>

        <div class="w-main">
            <div class="w-head">
                <div class="w-name"><?= htmlspecialchars($w['item_name']) ?></div>
                <span class="w-status-badge <?= $status_class ?>"><?= $status_label ?></span>
            </div>

            <div class="w-meta">
                <span class="w-proj" style="border-color:<?= htmlspecialchars($w['project_color']) ?>">
                    <?= htmlspecialchars($w['project_name']) ?>
                </span>
                <?php if ($w['vendor']): ?>
                <span class="w-vendor">🏪 <?= htmlspecialchars($w['vendor']) ?></span>
                <?php endif; ?>
                <span class="w-amount">💰 <?= mz_format_money($w['amount'], 0) ?></span>
            </div>

            <!-- Progress bar -->
            <div class="w-bar-wrap">
                <div class="w-bar-track">
                    <div class="w-bar-fill" style="width:<?= $pct ?>%;background:<?= $bar_color ?>"></div>
                </div>
                <div class="w-dates">
                    <span><?= $w['start_date'] ? date('Y/m/d', strtotime($w['start_date'])) : '—' ?></span>
                    <span><?= date('Y/m/d', strtotime($w['end_date'])) ?></span>
                </div>
            </div>

            <!-- Countdown -->
            <?php if (!$expired): ?>
            <div class="w-countdown" data-end="<?= $w['end_date'] ?>">
                <div class="cd-block">
                    <span class="cd-num" id="cd-d-<?= $w['id'] ?>"><?= floor(abs($days)) ?></span>
                    <span class="cd-lbl">يوم</span>
                </div>
                <div class="cd-sep">:</div>
                <div class="cd-block">
                    <span class="cd-num" id="cd-h-<?= $w['id'] ?>">00</span>
                    <span class="cd-lbl">ساعة</span>
                </div>
                <div class="cd-sep">:</div>
                <div class="cd-block">
                    <span class="cd-num" id="cd-m-<?= $w['id'] ?>">00</span>
                    <span class="cd-lbl">دقيقة</span>
                </div>
            </div>
            <?php else: ?>
            <div class="w-expired-badge">
                ❌ انتهى منذ <?= abs($days) ?> يوم
            </div>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="w-actions">
            <?php if ($w['file_path']): ?>
            <a href="/<?= htmlspecialchars($w['file_path']) ?>" download class="btn-icon"
                title="تحميل ملف الضمان">⬇️</a>
            <button onclick="previewFile('/<?= htmlspecialchars($w['file_path']) ?>')" class="btn-icon"
                title="معاينة">🔍</button>
            <?php endif; ?>
            <button type="button" class="btn-icon js-edit-warranty" data-id="<?= (int)$w['id'] ?>"
                data-end="<?= htmlspecialchars($w['end_date']) ?>" data-name="<?= htmlspecialchars($w['item_name']) ?>"
                title="تعديل">✏️</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ═══ Edit Modal ═══ -->
<div class="modal" id="editModal">
    <div class="modal-overlay" onclick="closeEdit()"></div>
    <div class="modal-box">
        <div class="modal-header">
            <h3>✏️ تعديل الضمان</h3>
            <button class="modal-close" onclick="closeEdit()">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editId">
            <div class="form-group">
                <label>اسم المنتج</label>
                <input type="text" id="editName" readonly style="background:var(--s2)">
            </div>
            <div class="form-group">
                <label>تاريخ البداية</label>
                <input type="date" id="editStart">
            </div>
            <div class="form-group">
                <label>تاريخ الانتهاء</label>
                <input type="date" id="editEnd" required>
            </div>
            <div class="form-group">
                <label>ملاحظات</label>
                <input type="text" id="editNotes" placeholder="مدة الضمان، الشركة...">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeEdit()">إلغاء</button>
            <button class="btn btn-primary" onclick="saveWarranty()">💾 حفظ</button>
        </div>
    </div>
</div>

<style>
/* ═══ Warranties Styles ═══ */
.warranties-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-top: 24px;
}

.w-card {
    background: var(--surf);
    border: 1.5px solid var(--bdr);
    border-radius: 18px;
    padding: 18px 20px;
    display: flex;
    gap: 16px;
    align-items: flex-start;
    transition: all .3s;
    position: relative;
    overflow: hidden;
}

.w-card::before {
    content: '';
    position: absolute;
    right: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    border-radius: 0 18px 18px 0;
}

.status-healthy::before {
    background: #22C55E
}

.status-warning::before {
    background: #EAB308
}

.status-urgent::before {
    background: #F97316
}

.status-expired::before {
    background: #EF4444
}

.w-card:hover {
    transform: translateX(-4px);
    box-shadow: 0 8px 28px rgba(0, 0, 0, .08);
}

.w-icon-wrap {
    position: relative;
    width: 48px;
    height: 48px;
    flex-shrink: 0;
}

.w-icon {
    font-size: 32px;
}

.w-dot {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid var(--surf);
}

.status-healthy .w-dot {
    background: #22C55E
}

.status-warning .w-dot {
    background: #EAB308
}

.status-urgent .w-dot {
    background: #F97316
}

.status-expired .w-dot {
    background: #EF4444
}

.w-main {
    flex: 1;
    min-width: 0;
}

.w-head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
    flex-wrap: wrap;
}

.w-name {
    font-size: 15px;
    font-weight: 800;
    color: var(--txt);
}

.w-status-badge {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
    white-space: nowrap;
}

.status-healthy .w-status-badge {
    background: rgba(34, 197, 94, .12);
    color: #16A34A
}

.status-warning .w-status-badge {
    background: rgba(234, 179, 8, .12);
    color: #A16207
}

.status-urgent .w-status-badge {
    background: rgba(249, 115, 22, .12);
    color: #C2410C;
    animation: pulse .8s ease-in-out infinite alternate;
}

.status-expired .w-status-badge {
    background: rgba(239, 68, 68, .12);
    color: #DC2626
}

@keyframes pulse {
    from {
        opacity: .7
    }

    to {
        opacity: 1
    }
}

.w-meta {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 10px;
    font-size: 12px;
    align-items: center;
}

.w-proj {
    padding: 2px 10px;
    border-radius: 12px;
    border: 1.5px solid;
    font-weight: 600;
    color: var(--txt);
}

.w-vendor,
.w-amount {
    color: var(--mut);
}

.w-bar-wrap {
    margin-bottom: 10px;
}

.w-bar-track {
    height: 7px;
    background: var(--s2);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 4px;
}

.w-bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width .6s;
}

.w-dates {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: var(--mut);
}

/* Countdown */
.w-countdown {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 6px;
}

.cd-block {
    display: flex;
    flex-direction: column;
    align-items: center;
    background: var(--s2);
    border-radius: 8px;
    padding: 5px 10px;
    min-width: 42px;
}

.cd-num {
    font-size: 16px;
    font-weight: 900;
    color: var(--txt);
    line-height: 1;
}

.cd-lbl {
    font-size: 9px;
    color: var(--mut);
    margin-top: 1px;
}

.cd-sep {
    font-size: 18px;
    font-weight: 900;
    color: var(--mut);
    margin-bottom: 12px;
}

.status-urgent .cd-num {
    color: #C2410C
}

.status-warning .cd-num {
    color: #A16207
}

.status-healthy .cd-num {
    color: #16A34A
}

.w-expired-badge {
    font-size: 12px;
    font-weight: 700;
    color: #DC2626;
    background: rgba(239, 68, 68, .08);
    padding: 5px 12px;
    border-radius: 8px;
    display: inline-block;
    margin-top: 6px;
}

.w-actions {
    display: flex;
    flex-direction: column;
    gap: 6px;
    align-self: center;
}

/* Stats row */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 24px;
}

@media(max-width:700px) {
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
}

.stat-card {
    background: var(--surf);
    border: 1.5px solid var(--bdr);
    border-radius: 16px;
    padding: 18px 16px;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: all .3s;
}

.stat-card:hover {
    border-color: rgba(0, 108, 53, .25);
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0, 108, 53, .08);
}

.stat-icon {
    font-size: 28px;
    flex-shrink: 0;
}

.stat-num {
    font-size: 22px;
    font-weight: 900;
    color: var(--g);
}

.stat-lbl {
    font-size: 12px;
    color: var(--mut);
    margin-top: 2px;
}

/* filter */
.filter-bar {
    margin-bottom: 8px;
}

.filter-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-input {
    flex: 1;
    min-width: 180px;
}

.filter-tabs {
    display: flex;
    gap: 4px;
    background: var(--s2);
    border-radius: 10px;
    padding: 3px;
    flex-wrap: wrap;
}

.ftab {
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--mut);
    text-decoration: none;
    transition: all .2s;
    white-space: nowrap;
}

.ftab.on {
    background: var(--surf);
    color: var(--g);
    box-shadow: 0 2px 6px rgba(0, 0, 0, .06);
}
</style>

<script>
// ─── Live Countdown ──────────────────────────────────────
document.querySelectorAll('.w-countdown').forEach(el => {
    const endDate = el.dataset.end;
    const id = el.closest('.w-card').dataset.id;
    if (!endDate) return;

    // Updates.
    function update() {
        const diff = new Date(endDate + 'T23:59:59') - new Date();
        if (diff <= 0) {
            el.innerHTML = '<span style="color:#EF4444;font-weight:700;">انتهى الضمان</span>';
            return;
        }
        const d = Math.floor(diff / 86400000);
        const h = Math.floor((diff % 86400000) / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        const s = Math.floor((diff % 60000) / 1000);
        const pad = n => String(n).padStart(2, '0');
        const dEl = document.getElementById(`cd-d-${id}`);
        const hEl = document.getElementById(`cd-h-${id}`);
        const mEl = document.getElementById(`cd-m-${id}`);
        if (dEl) dEl.textContent = d;
        if (hEl) hEl.textContent = pad(h);
        if (mEl) mEl.textContent = pad(m);
    }
    update();
    setInterval(update, 1000);
});

// ─── Edit Warranty (event-delegated, data-* driven) ─────
function editWarranty(id, endDate, name) {
    document.getElementById('editId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editEnd').value = endDate;
    document.getElementById('editModal').classList.add('active');
}

document.addEventListener('click', function(ev) {
    const btn = ev.target.closest('.js-edit-warranty');
    if (!btn) return;
    editWarranty(
        parseInt(btn.dataset.id, 10),
        btn.dataset.end,
        btn.dataset.name
    );
});

// Closes the edit.
function closeEdit() {
    document.getElementById('editModal').classList.remove('active');
}

// Saves the warranty.
function saveWarranty() {
    const id = document.getElementById('editId').value;
    const end = document.getElementById('editEnd').value;
    const notes = document.getElementById('editNotes').value;
    if (!end) {
        Swal.fire('تنبيه', 'الرجاء إدخال تاريخ الانتهاء', 'warning');
        return;
    }

    mizanFetch('/api/projects.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'update_warranty',
                id,
                end_date: end,
                notes
            })
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                closeEdit();
                Swal.fire({
                        icon: 'success',
                        title: 'تم الحفظ',
                        timer: 1200,
                        showConfirmButton: false
                    })
                    .then(() => location.reload());
            } else {
                Swal.fire('خطأ', d.error || 'فشل الحفظ', 'error');
            }
        });
}

// Defines the previewFile routine.
function previewFile(url) {
    const isPdf = url.endsWith('.pdf');
    Swal.fire({
        html: isPdf ?
            `<iframe src="${url}" style="width:100%;height:500px;border:none;border-radius:8px;"></iframe>` :
            `<img src="${url}" style="max-width:100%;border-radius:8px;">`,
        width: '80%',
        showConfirmButton: false,
        showCloseButton: true,
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>