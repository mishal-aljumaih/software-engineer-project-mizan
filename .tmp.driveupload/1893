<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: project-detail.php
 * PURPOSE: Detailed view of a single project including budgets and files.
 * OWNER: Alwaleed Alzahrani - Development & Database Admin (Lead Programmer)
 * ========================================================================
 */
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/notifications_helper.php';

$pdo = getDB();
$project_id = (int) ($_GET['id'] ?? 0);

if ($project_id <= 0) {
    header("Location: projects.php");
    exit;
}

// IDOR guard — the AND user_id = ? ensures users can only open their own projects.
// A forged ?id= for someone else's project simply returns null and bounces home.
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
$stmt->execute([$project_id, $current_user_id]);
$project = $stmt->fetch();

if (!$project) {
    header("Location: projects.php");
    exit;
}

// Stats
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE project_id = ?");
$stmt->execute([$project_id]);
$total_cost = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE project_id = ?");
$stmt->execute([$project_id]);
$expense_count = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM warranties w JOIN expenses e ON w.expense_id = e.id WHERE e.project_id = ?");
$stmt->execute([$project_id]);
$warranty_count = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM files WHERE project_id = ?");
$stmt->execute([$project_id]);
$file_count = (int) $stmt->fetchColumn();

$profit     = $project['sell_price'] > 0 ? ($project['sell_price'] - $total_cost) : null;
$budget_pct = $project['budget'] > 0 ? min(100, round(($total_cost / $project['budget']) * 100)) : 0;

// Project nature: personal vs commercial (column may not exist on older DBs)
$project_nature = strtolower((string) ($project['nature'] ?? 'personal'));
if (!in_array($project_nature, ['personal', 'commercial'], true)) $project_nature = 'personal';
$is_commercial  = ($project_nature === 'commercial');

// Embed files for JS (no files-list API)
$stmt = $pdo->prepare("SELECT * FROM files WHERE project_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$project_id]);
$files_json = json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);

// Status update handler (traditional POST — simple redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        $new_status = $_POST['status'] ?? 'active';
        $new_sell   = (float) ($_POST['sell_price'] ?? 0);
        $pdo->prepare("UPDATE projects SET status = ?, sell_price = ? WHERE id = ? AND user_id = ?")
            ->execute([$new_status, $new_sell, $project_id, $current_user_id]);
    }
    header("Location: project-detail.php?id={$project_id}&updated=1");
    exit;
}

$page_title = $project['name'];
require_once '../includes/header.php';

$icons       = ['car'=>'🚗','house'=>'🏠','occasion'=>'🎉','work'=>'💼','devices'=>'📦','custom'=>'✏️'];
$status_map  = ['active'=>['نشط','badge-success'],'done'=>['مكتمل','badge-info'],'archived'=>['مؤرشف','badge-neutral']];
$si          = $status_map[$project['status']] ?? $status_map['active'];
// Category suggestions tailored to project type/nature.
// Commercial projects use business-oriented categories.
// Travel/house/occasion personal projects use type-specific categories.
$proj_type = strtolower((string)($project['type'] ?? ''));
if ($is_commercial) {
    $categories = ['عام','رواتب','إيجار','خدمات','تسويق','مستلزمات مكتبية','عمولات','ضرائب','فواتير','صيانة','مواصلات','طعام','أخرى'];
} elseif ($proj_type === 'travel' || stripos((string)($project['template'] ?? ''), 'travel') !== false) {
    $categories = ['عام','تذاكر طيران','فندق','مواصلات','طعام','أنشطة','تأشيرة','تأمين سفر','تسوق','أخرى'];
} elseif ($proj_type === 'house') {
    $categories = ['عام','سباكة','كهرباء','عظم','دهان','تشطيبات','أثاث','مواد','عمالة','أخرى'];
} elseif ($proj_type === 'occasion' || $proj_type === 'wedding') {
    $categories = ['عام','قاعة','ضيافة','دعوات','تصوير','ديكور','أزياء','أخرى'];
} else {
    // Default (car / work / devices / custom — personal)
    $categories = ['عام','شراء','صيانة','دهان','قطع غيار','كفرات','بطارية','تأمين','رسوم','مواد','عمالة','أخرى'];
}
$proj_color  = htmlspecialchars($project['color'] ?? '#3b82f6');
?>

<!-- Back -->
<div style="margin-bottom:8px;">
    <a href="projects.php" class="pd-back" data-i18n="back_to_projects">← المشاريع</a>
</div>

<!-- ===== HERO (Glassmorphism) ===== -->
<div class="pd-hero reveal" style="--accent:<?= $proj_color ?>;">
    <div class="pd-hero-bg" style="background:<?= $proj_color ?>15;"></div>
    <div class="pd-hero-left">
        <div class="pd-hero-icon" style="background:<?= $proj_color ?>20; border-color:<?= $proj_color ?>40;">
            <?= $icons[$project['type']] ?? '✏️' ?>
        </div>
        <div>
            <h1 class="pd-hero-name"><?= htmlspecialchars($project['name']) ?></h1>
            <div class="pd-hero-meta">
                <span class="badge <?= $si[1] ?>"><?= $si[0] ?></span>
                <span class="text-muted">📅 <?= date('d/m/Y', strtotime($project['created_at'])) ?></span>
                <?php if ($project['notes']): ?>
                <span class="text-muted">📝 <?= htmlspecialchars(mb_substr($project['notes'], 0, 60)) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="pd-hero-actions">
        <button class="btn btn-ghost btn-sm" onclick="openModal('updateStatusModal')" data-i18n="update_status">✏️ تحديث الحالة</button>
        <button class="btn btn-primary btn-sm" onclick="openSmartUpload({projectId: <?= $project_id ?>})" data-i18n="upload_file_btn">☁️ رفع ملف</button>
        <button class="btn btn-primary btn-sm" onclick="openModal('addExpenseModal')" data-i18n="add_expense_btn">＋ إضافة عملية</button>
    </div>
</div>

<!-- ===== STATS ===== -->
<div class="grid-4 reveal" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon green">💸</div>
        <div>
            <div class="stat-label" data-i18n="total_cost">إجمالي التكلفة</div>
            <div class="stat-value"><span class="count-up"
                    data-target="<?= $total_cost ?>"><?= number_format($total_cost, 0) ?></span> <small
                    style="font-size:12px;color:var(--text-muted);" class="currency-symbol"><?= mz_currency_symbol() ?></small></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">📋</div>
        <div>
            <div class="stat-label" data-i18n="expenses_count">عدد العمليات</div>
            <div class="stat-value"><?= $expense_count ?></div>
        </div>
    </div>
    <?php if ($is_commercial): ?>
    <div class="stat-card">
        <div class="stat-icon <?= $profit === null ? 'blue' : ($profit >= 0 ? 'green' : 'red') ?>">⚖️</div>
        <div>
            <div class="stat-label" data-i18n="profit_loss_title">الربح / الخسارة</div>
            <div class="stat-value <?= $profit === null ? '' : ($profit >= 0 ? 'text-success' : 'text-danger') ?>">
                <?php if ($profit === null): ?><span style="font-size:14px;color:var(--text-muted);">لم
                    يُباع</span><?php else: ?><span class="count-up"
                    data-target="<?= $profit ?>"><?= number_format($profit, 0) ?></span> <small
                    style="font-size:12px;" class="currency-symbol"><?= mz_currency_symbol() ?></small><?php endif; ?></div>
        </div>
    </div>
    <?php else: ?>
    <div class="stat-card">
        <div class="stat-icon blue">📊</div>
        <div>
            <div class="stat-label" data-i18n="avg_monthly">المتوسط الشهري</div>
            <div class="stat-value" id="pdAvgMonthly">— <small style="font-size:12px;" class="currency-symbol"><?= mz_currency_symbol() ?></small></div>
        </div>
    </div>
    <?php endif; ?>
    <div class="stat-card">
        <div class="stat-icon gold">🛡️</div>
        <div>
            <div class="stat-label" data-i18n="warranties_count">الضمانات</div>
            <div class="stat-value"><?= $warranty_count ?></div>
        </div>
    </div>
</div>

<!-- ===== BUDGET BAR ===== -->
<?php if ($project['budget'] > 0): ?>
<div class="card reveal pd-budget-card">
    <div class="pd-budget-header">
        <span style="font-weight:600;font-size:14px;" data-i18n="budget_label">الميزانية</span>
        <span style="font-size:13px;color:var(--text-muted);">
            <?= number_format($total_cost, 0) ?> / <?= number_format($project['budget'], 0) ?> <?= mz_currency_symbol() ?>
            <strong class="pd-budget-pct" data-pct="<?= $budget_pct ?>">(<?= $budget_pct ?>%)</strong>
        </span>
    </div>
    <div class="pd-bar-track">
        <div class="pd-bar-fill" data-pct="<?= $budget_pct ?>"></div>
    </div>
    <?php if ($budget_pct >= 90): ?>
    <div class="alert alert-error" style="margin-top:10px;margin-bottom:0;padding:8px 12px;font-size:13px;" data-i18n="budget_warning">⚠️ تجاوزت أو اقتربت من حد الميزانية!</div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ===== ANALYTICS / P&L PANEL ===== -->
<div class="card reveal pd-analytics" style="margin-bottom:20px;padding:18px;">
    <div class="pd-analytics-head"
        style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:20px;"><?= $is_commercial ? '💼' : '📊' ?></span>
            <strong style="font-size:15px;" data-i18n="<?= $is_commercial ? 'profit_loss_title' : 'analytics_title' ?>">
                <?= $is_commercial ? 'الربح / الخسارة' : 'تحليل المصروفات' ?>
            </strong>
        </div>
        <?php if (!$is_commercial): ?>
        <div class="pd-range" id="pdRange">
            <button class="pd-range-btn active" data-months="1" onclick="setPdRange(1)" data-i18n="last_1m">آخر
                شهر</button>
            <button class="pd-range-btn" data-months="3" onclick="setPdRange(3)" data-i18n="last_3m">آخر 3 أشهر</button>
            <button class="pd-range-btn" data-months="6" onclick="setPdRange(6)" data-i18n="last_6m">آخر 6 أشهر</button>
            <button class="pd-range-btn" data-months="12" onclick="setPdRange(12)" data-i18n="last_12m">آخر 12
                شهر</button>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($is_commercial): ?>
    <div class="grid-3" id="pdPlSummary" style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
        <div class="pl-tile">
            <div class="pl-lbl" data-i18n="total_in">الإيرادات</div>
            <div class="pl-val text-success" id="plIn">—</div>
        </div>
        <div class="pl-tile">
            <div class="pl-lbl" data-i18n="total_out">المصروفات</div>
            <div class="pl-val text-danger" id="plOut">—</div>
        </div>
        <div class="pl-tile">
            <div class="pl-lbl" data-i18n="net">الصافي</div>
            <div class="pl-val" id="plNet">—</div>
        </div>
    </div>
    <?php else: ?>
    <div style="position:relative;height:240px;">
        <canvas id="pdAnalyticsChart"></canvas>
    </div>
    <div id="pdAnalyticsSummary"
        style="display:flex;gap:14px;flex-wrap:wrap;margin-top:10px;font-size:13px;color:var(--text-muted);"></div>
    <?php endif; ?>
</div>
<style>
.pd-range {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.pd-range-btn {
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    border-radius: 8px;
    border: 1.5px solid var(--bdr);
    background: var(--surf);
    color: var(--mut);
    cursor: pointer;
    transition: all .2s;
}

.pd-range-btn:hover {
    border-color: var(--g);
    color: var(--g);
}

.pd-range-btn.active {
    background: var(--g);
    color: #fff;
    border-color: var(--g);
}

.pl-tile {
    padding: 14px;
    border-radius: 12px;
    background: var(--s2);
    border: 1px solid var(--bdr);
    text-align: center;
}

.pl-lbl {
    font-size: 12px;
    color: var(--mut);
    margin-bottom: 6px;
}

.pl-val {
    font-size: 22px;
    font-weight: 800;
}
</style>

<!-- ===== TABS ===== -->
<div class="pd-tabs-wrap reveal">
    <div class="pd-tabs">
        <button class="pd-tab active" data-tab="expenses" onclick="switchTab('expenses')"><span data-i18n="tab_expenses">📋 العمليات</span> <span class="pd-tab-count"><?= $expense_count ?></span></button>
        <button class="pd-tab" data-tab="warranties" onclick="switchTab('warranties')"><span data-i18n="tab_warranties">🛡️ الضمانات</span> <span class="pd-tab-count"><?= $warranty_count ?></span></button>
        <button class="pd-tab" data-tab="files" onclick="switchTab('files')"><span data-i18n="tab_files">📁 الملفات</span> <span class="pd-tab-count"><?= $file_count ?></span></button>
    </div>
</div>

<!-- Tab Content -->
<div id="tabContent" class="pd-tab-content reveal">
    <!-- Skeleton on first load -->
    <div class="pd-skel">
        <div class="pd-skel-row skeleton"></div>
        <div class="pd-skel-row skeleton" style="width:85%"></div>
        <div class="pd-skel-row skeleton" style="width:70%"></div>
        <div class="pd-skel-row skeleton" style="width:90%"></div>
    </div>
</div>

<!-- ===== MODAL: Add Expense ===== -->
<div class="modal-overlay" id="addExpenseModal">
    <div class="modal" style="max-width:580px;">
        <div class="modal-header">
            <span class="modal-title" data-i18n="modal_add_expense">＋ إضافة عملية / مصروف</span>
            <button class="modal-close" onclick="closeModal('addExpenseModal')">✕</button>
        </div>
        <div class="modal-body">
            <form id="expenseForm" enctype="multipart/form-data" onsubmit="submitExpense(event)">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label data-i18n="expense_name">اسم العملية *</label>
                        <input type="text" name="title" data-i18n-placeholder="expense_name_ph" placeholder="مثال: شراء كفرات جديدة" required>
                    </div>
                    <div class="form-group">
                        <label data-i18n="amount_sar">المبلغ (﷼) *</label>
                        <input type="number" name="amount" placeholder="0.00" min="0" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label data-i18n="purchase_date_field">تاريخ الشراء</label>
                        <input type="date" name="purchase_date" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label data-i18n="category">التصنيف</label>
                        <select name="category" id="expenseCategorySelect" onchange="onCategoryChange(this)">
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat === 'أخرى' ? 'other' : htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="expenseCustomCategory" name="custom_category"
                            style="display:none;margin-top:8px;"
                            data-i18n-placeholder="custom_category_ph" placeholder="اكتب التصنيف...">
                    </div>
                    <div class="form-group">
                        <label data-i18n="vendor_field">المورد / الجهة</label>
                        <input type="text" name="vendor" data-i18n-placeholder="vendor_ph" placeholder="اسم المحل أو الشركة">
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label data-i18n="upload_invoice_label">رفع الفاتورة (صورة أو PDF)</label>
                        <input type="file" name="invoice" accept="image/*,.pdf" style="padding:8px;cursor:pointer;">
                        <div class="form-hint" data-i18n="upload_hint_500">حد أقصى 500MB</div>
                    </div>
                </div>
                <div class="pd-warranty-section">
                    <div class="pd-warranty-toggle" onclick="toggleWarrantyFields()">
                        <span id="warrantyToggleIcon">▶</span> <span data-i18n="add_warranty_for_expense">إضافة ضمان لهذه العملية</span>
                    </div>
                    <div id="warrantyFields" style="display:none;margin-top:14px;">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                            <div class="form-group"><label data-i18n="warranty_start">بداية الضمان</label><input type="date" name="warranty_start"
                                    value="<?= date('Y-m-d') ?>"></div>
                            <div class="form-group"><label data-i18n="warranty_end_field">انتهاء الضمان</label><input type="date" name="warranty_end">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group" style="margin-top:16px;">
                    <label data-i18n="notes_label">ملاحظات</label>
                    <textarea name="notes" rows="2" data-i18n-placeholder="notes_ph" placeholder="أي تفاصيل إضافية..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('addExpenseModal')" data-i18n="cancel">إلغاء</button>
            <button class="btn btn-primary" id="submitExpenseBtn"
                onclick="document.getElementById('expenseForm').requestSubmit()" data-i18n="save_expense">حفظ العملية ✓</button>
        </div>
    </div>
</div>

<!-- ===== MODAL: Edit Expense ===== -->
<div class="modal-overlay" id="editExpenseModal">
    <div class="modal" style="max-width:580px;">
        <div class="modal-header">
            <span class="modal-title" data-i18n="edit_expense_title">✏️ تعديل العملية</span>
            <button class="modal-close" onclick="closeModal('editExpenseModal')">✕</button>
        </div>
        <div class="modal-body">
            <form id="editExpenseForm" onsubmit="submitEditExpense(event)">
                <input type="hidden" id="editExpenseId">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label data-i18n="expense_name">اسم العملية *</label>
                        <input type="text" id="editExpTitle" data-i18n-placeholder="expense_name_ph" placeholder="مثال: شراء كفرات جديدة" required>
                    </div>
                    <div class="form-group">
                        <label data-i18n="amount_sar">المبلغ (﷼) *</label>
                        <input type="number" id="editExpAmount" placeholder="0.00" min="0" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label data-i18n="purchase_date_field">تاريخ الشراء</label>
                        <input type="date" id="editExpDate">
                    </div>
                    <div class="form-group">
                        <label data-i18n="category">التصنيف</label>
                        <select id="editExpCat" onchange="onEditCategoryChange(this)">
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat === 'أخرى' ? 'other' : htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="editExpCustomCat"
                            style="display:none;margin-top:8px;"
                            data-i18n-placeholder="custom_category_ph" placeholder="اكتب التصنيف...">
                    </div>
                    <div class="form-group">
                        <label data-i18n="vendor_field">المورد / الجهة</label>
                        <input type="text" id="editExpVendor" data-i18n-placeholder="vendor_ph" placeholder="اسم المحل أو الشركة">
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label data-i18n="notes_label">ملاحظات</label>
                        <textarea id="editExpNotes" rows="2" data-i18n-placeholder="notes_ph" placeholder="أي تفاصيل إضافية..."></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('editExpenseModal')" data-i18n="cancel">إلغاء</button>
            <button class="btn btn-primary" id="saveEditExpenseBtn"
                onclick="document.getElementById('editExpenseForm').requestSubmit()" data-i18n="save_changes">حفظ التعديلات ✓</button>
        </div>
    </div>
</div>

<!-- ===== MODAL: Update Status ===== -->
<div class="modal-overlay" id="updateStatusModal">
    <div class="modal" style="max-width:420px;">
        <div class="modal-header">
            <span class="modal-title" data-i18n="modal_update_status">✏️ تحديث حالة المشروع</span>
            <button class="modal-close" onclick="closeModal('updateStatusModal')">✕</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="statusForm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div class="form-group">
                    <label data-i18n="status_label">الحالة</label>
                    <select name="status">
                        <option value="active" <?= $project['status']==='active'  ?'selected':'' ?> data-i18n="status_active_o">🟢 نشط</option>
                        <option value="done" <?= $project['status']==='done'    ?'selected':'' ?> data-i18n="status_done_o">✅ مكتمل</option>
                        <option value="archived" <?= $project['status']==='archived'?'selected':'' ?> data-i18n="status_archived_o">📦 مؤرشف</option>
                    </select>
                </div>
                <?php if ($is_commercial): ?>
                <div class="form-group">
                    <label data-i18n="sell_price_sar">سعر البيع (﷼)</label>
                    <input type="number" name="sell_price" value="<?= $project['sell_price'] ?>" placeholder="0.00"
                        min="0" step="0.01">
                    <div class="form-hint" data-i18n="sell_price_hint">اتركه 0 إذا لم يُباع بعد</div>
                </div>
                <?php else: ?>
                <input type="hidden" name="sell_price" value="0">
                <?php endif; ?>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('updateStatusModal')" data-i18n="cancel">إلغاء</button>
            <button class="btn btn-primary" onclick="document.getElementById('statusForm').submit()" data-i18n="save_short">حفظ ✓</button>
        </div>
    </div>
</div>

<!-- ===== MODAL: Image Preview ===== -->
<div class="modal-overlay" id="imagePreviewModal" style="background:rgba(0,0,0,0.85);">
    <div style="max-width:90vw;max-height:90vh;text-align:center;position:relative;">
        <button onclick="closeModal('imagePreviewModal')"
            style="position:absolute;top:-40px;left:0;background:none;border:none;color:#fff;font-size:28px;cursor:pointer;">✕</button>
        <img id="previewImg" src="" alt="معاينة"
            style="max-width:100%;max-height:85vh;border-radius:8px;display:block;">
    </div>
</div>

<style>
/* Back link */
.pd-back {
    color: var(--text-muted);
    font-size: 14px;
    text-decoration: none;
    transition: color .2s;
}

.pd-back:hover {
    color: var(--primary);
}

/* ── Hero (Glassmorphism) ──────────────────────────────── */
.pd-hero {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 24px;
    padding: 28px 32px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    box-shadow: var(--shadow);
}

.pd-hero-bg {
    position: absolute;
    inset: 0;
    opacity: .5;
    pointer-events: none;
}

.pd-hero-left {
    display: flex;
    align-items: center;
    gap: 16px;
    flex: 1;
    min-width: 0;
    position: relative;
    z-index: 1;
}

.pd-hero-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    flex-shrink: 0;
    border: 2px solid;
    transition: transform .3s;
}

.pd-hero:hover .pd-hero-icon {
    transform: scale(1.08) rotate(-3deg);
}

.pd-hero-name {
    font-size: 24px;
    font-weight: 700;
    color: var(--text);
}

.pd-hero-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 6px;
    font-size: 13px;
}

.pd-hero-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}

/* ── Budget Bar (Animated) ─────────────────────────────── */
.pd-budget-card {
    padding: 18px 24px;
    margin-bottom: 20px;
}

.pd-budget-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.pd-bar-track {
    height: 10px;
    background: var(--surface2);
    border-radius: 5px;
    overflow: hidden;
}

.pd-bar-fill {
    height: 100%;
    border-radius: 5px;
    width: 0;
    transition: width 1.2s cubic-bezier(.22, 1, .36, 1), background .4s;
}

.pd-budget-pct[data-pct] {
    font-weight: 700;
}

/* ── Tabs ──────────────────────────────────────────────── */
.pd-tabs-wrap {
    margin-bottom: 20px;
}

.pd-tabs {
    display: flex;
    gap: 4px;
    border-bottom: 2px solid var(--border);
    padding-bottom: 0;
    overflow-x: auto;
}

.pd-tab {
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-muted);
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    cursor: pointer;
    transition: all .2s;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 6px;
    font-family: 'Tajawal', sans-serif;
}

.pd-tab:hover {
    color: var(--text);
}

.pd-tab.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}

.pd-tab-count {
    background: var(--surface2);
    color: var(--text-muted);
    font-size: 11px;
    padding: 2px 7px;
    border-radius: 10px;
}

.pd-tab.active .pd-tab-count {
    background: rgba(0, 108, 53, .12);
    color: var(--primary);
}

.pd-tab-content {
    animation: pageFadeIn .3s ease both;
    min-height: 200px;
}

/* ── Skeleton ──────────────────────────────────────────── */
.pd-skel {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 20px 0;
}

.pd-skel-row {
    height: 60px;
    border-radius: var(--radius-sm);
    background: var(--surface2);
}

@keyframes shimmer {
    0% {
        background-position: -400px 0
    }

    100% {
        background-position: 400px 0
    }
}

.skeleton {
    background: linear-gradient(90deg, var(--surface2) 25%, var(--border) 50%, var(--surface2) 75%) !important;
    background-size: 800px 100% !important;
    animation: shimmer 1.5s infinite linear;
}

/* ── Expenses List ─────────────────────────────────────── */
.pd-expenses {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.pd-exp-item {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px 18px;
    transition: all .2s;
}

.pd-exp-item:hover {
    border-color: var(--primary);
    transform: translateX(-2px);
}

.pd-exp-main {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}

.pd-exp-info {
    flex: 1;
    min-width: 0;
}

.pd-exp-title {
    font-weight: 700;
    font-size: 15px;
    color: var(--text);
    margin-bottom: 6px;
}

.pd-exp-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    font-size: 13px;
}

.pd-exp-notes {
    font-size: 13px;
    margin-top: 6px;
    font-style: italic;
    color: var(--text-muted);
}

.pd-exp-right {
    text-align: left;
    flex-shrink: 0;
}

.pd-exp-amount {
    font-size: 18px;
    font-weight: 700;
    color: var(--text);
    white-space: nowrap;
}

.pd-exp-amount small {
    font-size: 12px;
    color: var(--text-muted);
    font-weight: 500;
}

.pd-exp-badges {
    display: flex;
    gap: 6px;
    justify-content: flex-end;
    margin-top: 6px;
    flex-wrap: wrap;
}

.pd-exp-actions {
    display: flex;
    gap: 6px;
    justify-content: flex-end;
    margin-top: 8px;
}

.pd-exp-actions .btn {
    padding: 5px 10px;
    font-size: 13px;
    line-height: 1;
}

.pd-exp-warranty {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed var(--border);
}

.pd-file-badge {
    background: rgba(3, 105, 161, .08);
    border: 1px solid rgba(3, 105, 161, .2);
    color: var(--secondary);
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 12px;
    text-decoration: none;
    transition: all .2s;
}

.pd-file-badge:hover {
    background: rgba(3, 105, 161, .15);
}

/* Categories summary */
.pd-cats {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.pd-cat-chip {
    display: flex;
    gap: 6px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 5px 14px;
    font-size: 13px;
    align-items: center;
}

.pd-cat-chip strong {
    color: var(--primary);
}

/* ── Warranties ────────────────────────────────────────── */
.pd-warranties {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.pd-w-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 18px 20px;
    transition: all .2s;
}

.pd-w-card:hover {
    border-color: var(--primary);
}

.pd-w-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
    gap: 12px;
}

.pd-w-title {
    font-weight: 700;
    font-size: 15px;
    margin-bottom: 4px;
}

.pd-w-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* ── Files Grid ────────────────────────────────────────── */
.pd-files {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 14px;
}

.pd-f-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 14px;
    transition: all .2s;
}

.pd-f-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
}

.pd-f-preview {
    height: 110px;
    background: var(--surface2);
    border-radius: var(--radius-sm);
    overflow: hidden;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pd-f-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    cursor: pointer;
    transition: transform .2s;
}

.pd-f-preview img:hover {
    transform: scale(1.05);
}

.pd-f-name {
    font-size: 12px;
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pd-f-size {
    font-size: 11px;
    margin-top: 2px;
    color: var(--text-muted);
}

/* ── Warranty toggle in modal ──────────────────────────── */
.pd-warranty-section {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 14px 16px;
    margin-top: 4px;
}

.pd-warranty-toggle {
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: var(--secondary);
    display: flex;
    align-items: center;
    gap: 8px;
    user-select: none;
}

.pd-warranty-toggle:hover {
    color: var(--primary);
}

/* ── Empty State ───────────────────────────────────────── */
.pd-empty {
    text-align: center;
    padding: 48px;
    color: var(--text-muted);
}

.pd-empty-icon {
    font-size: 48px;
    margin-bottom: 12px;
    opacity: .6;
}

.pd-empty h3 {
    font-size: 17px;
    margin-bottom: 6px;
    color: var(--text);
}

.pd-empty p {
    font-size: 14px;
}

/* ── Mobile ────────────────────────────────────────────── */
@media(max-width:768px) {
    .pd-hero {
        padding: 20px 16px;
    }

    .pd-hero-name {
        font-size: 18px;
    }

    .pd-hero-icon {
        width: 48px;
        height: 48px;
        font-size: 24px;
    }

    .pd-hero-actions {
        width: 100%;
        justify-content: stretch;
    }

    .pd-hero-actions .btn {
        flex: 1;
    }

    .pd-exp-main {
        flex-direction: column;
    }

    .pd-exp-right {
        text-align: right;
    }

    .pd-files {
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    }
}
</style>

<script>
(() => {
    'use strict';

    const PROJECT_ID = <?= $project_id ?>;
    const FILES_DATA = <?= $files_json ?>;
    const IS_COMMERCIAL = <?= $is_commercial ? 'true' : 'false' ?>;
    const SELL_PRICE = <?= (float) $project['sell_price'] ?>;
    let expensesCache = null;
    let pdRangeMonths = 1;
    let pdChart = null;

    // ── Tab switching ───────────────────────────────────────
    window.switchTab = function(tab) {
        document.querySelectorAll('.pd-tab').forEach(t => t.classList.remove('active'));
        document.querySelector(`.pd-tab[data-tab="${tab}"]`)?.classList.add('active');
        const el = document.getElementById('tabContent');
        el.innerHTML = buildSkeleton();
        switch (tab) {
            case 'expenses':
                loadExpenses();
                break;
            case 'warranties':
                loadWarranties();
                break;
            case 'files':
                renderFiles();
                break;
        }
    };

    // Builds the skeleton.
    function buildSkeleton() {
        return `<div class="pd-skel">
        <div class="pd-skel-row skeleton"></div>
        <div class="pd-skel-row skeleton" style="width:85%"></div>
        <div class="pd-skel-row skeleton" style="width:70%"></div>
    </div>`;
    }

    // ── Expenses ────────────────────────────────────────────
    function loadExpenses() {
        if (expensesCache) {
            renderExpenses(expensesCache);
            return;
        }
        mizanFetch(`/api/expenses.php?action=list&project_id=${PROJECT_ID}`)
            .then(r => r.json())
            .then(d => {
                if (!d.success) {
                    showToast(d.error || t('error_title'), 'error');
                    return;
                }
                expensesCache = d.expenses;
                renderExpenses(d.expenses);
                updateAnalyticsPanel();
            })
            .catch(() => showToast(t('load_expenses_failed'), 'error'));
    }

    // ── Analytics / P&L panel (uses MizanMath helpers) ──────
    window.setPdRange = function(months) {
        pdRangeMonths = months;
        document.querySelectorAll('#pdRange .pd-range-btn').forEach(b => {
            b.classList.toggle('active', parseInt(b.dataset.months) === months);
        });
        updateAnalyticsPanel();
    };

    // Updates the analytics panel.
    function updateAnalyticsPanel() {
        if (!expensesCache || !window.MizanMath) return;
        const M = window.MizanMath;

        if (IS_COMMERCIAL) {
            const out = M.sum(expensesCache.map(e => parseFloat(e.amount) || 0));
            const inn = SELL_PRICE;
            const net = inn - out;
            const elIn = document.getElementById('plIn');
            const elOut = document.getElementById('plOut');
            const elNet = document.getElementById('plNet');
            if (elIn) elIn.textContent = M.formatCurrency(inn);
            if (elOut) elOut.textContent = M.formatCurrency(out);
            if (elNet) {
                elNet.textContent = M.formatCurrency(net);
                elNet.classList.toggle('text-success', net >= 0);
                elNet.classList.toggle('text-danger', net < 0);
            }
        } else {
            const filtered = M.filterByMonthsBack(expensesCache, 'purchase_date', pdRangeMonths);
            const monthly = M.monthlyTotals(filtered, 'purchase_date', 'amount');
            const labels = monthly.map(m => m.month);
            const values = monthly.map(m => m.total);
            const total = M.sum(values);
            const avg = values.length ? M.mean(values) : 0;

            const sumEl = document.getElementById('pdAnalyticsSummary');
            if (sumEl) sumEl.innerHTML =
                `<span>📊 ${t('analytics_title')}: <strong>${M.formatCurrency(total)}</strong></span>` +
                `<span>📈 ${t('avg_monthly')}: <strong>${M.formatCurrency(avg)}</strong></span>` +
                `<span>🧮 n = <strong>${filtered.length}</strong></span>`;

            const avgEl = document.getElementById('pdAvgMonthly');
            if (avgEl) avgEl.innerHTML =
                `${M.formatCurrency(avg).replace(/[^0-9,.\s]/g,'').trim() || 0} <small style="font-size:12px;">${getCurrencySymbol()}</small>`;

            renderAnalyticsChart(labels, values);
        }
    }

    // Renders the analytics chart.
    function renderAnalyticsChart(labels, values) {
        const cv = document.getElementById('pdAnalyticsChart');
        if (!cv || typeof Chart === 'undefined') return;
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        const grid = dark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.08)';
        const txt = dark ? '#cbd5e1' : '#475569';
        if (pdChart) pdChart.destroy();
        pdChart = new Chart(cv, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: t('total_out') || 'مصروفات',
                    data: values,
                    backgroundColor: 'rgba(0,108,53,0.55)',
                    borderColor: 'rgba(0,108,53,1)',
                    borderWidth: 1.5,
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: grid
                        },
                        ticks: {
                            color: txt
                        }
                    },
                    y: {
                        grid: {
                            color: grid
                        },
                        ticks: {
                            color: txt
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Renders the expenses.
    function renderExpenses(expenses) {
        const el = document.getElementById('tabContent');
        if (!expenses.length) {
            el.innerHTML =
                `<div class="pd-empty"><div class="pd-empty-icon">📋</div><h3>${t('pd_no_expenses_title')}</h3><p>${t('pd_no_expenses_desc')}</p><button class="btn btn-primary" style="margin-top:16px;" onclick="openModal('addExpenseModal')">${t('pd_add_expense')}</button></div>`;
            return;
        }

        // Category summary
        const byCat = {};
        expenses.forEach(e => {
            const c = e.category || 'عام';
            byCat[c] = (byCat[c] || 0) + parseFloat(e.amount);
        });
        const sorted = Object.entries(byCat).sort((a, b) => b[1] - a[1]).slice(0, 5);
        let catHtml = '<div class="pd-cats">' + sorted.map(([c, s]) =>
            `<div class="pd-cat-chip"><span>${esc(c)}</span><strong>${fmtNum(s)} ${getCurrencySymbol()}</strong></div>`
        ).join('') + '</div>';

        // Expenses list
        let html = catHtml + '<div class="pd-expenses">';
        expenses.forEach(e => {
            const ws = warrantyStatus(e.warranty_days_left, e.warranty_id);
            // CRITICAL: Every row must carry a real PK in data-id and pass it
            // explicitly to the action handlers (no closure capture, no index).
            const eid = parseInt(e.id, 10);
            html += `<div class="pd-exp-item" data-id="${eid}">
            <div class="pd-exp-main">
                <div class="pd-exp-info">
                    <div class="pd-exp-title">${esc(e.title)}</div>
                    <div class="pd-exp-meta">
                        ${e.category ? `<span class="badge badge-neutral">${esc(e.category)}</span>` : ''}
                        ${e.vendor ? `<span class="text-muted">🏪 ${esc(e.vendor)}</span>` : ''}
                        ${e.purchase_date ? `<span class="text-muted">📅 ${fmtDate(e.purchase_date)}</span>` : ''}
                    </div>
                    ${e.notes ? `<div class="pd-exp-notes">${esc(e.notes)}</div>` : ''}
                </div>
                <div class="pd-exp-right">
                    <div class="pd-exp-amount">${fmtNum(e.amount)} <small>${getCurrencySymbol()}</small></div>
                    <div class="pd-exp-badges">
                        ${e.invoice_file ? `<a href="/${esc(e.invoice_file)}" target="_blank" class="pd-file-badge">${e.invoice_type === 'image' ? '🖼️' : '📄'} ${t('invoice_label')}</a>` : ''}
                        ${e.warranty_file ? `<a href="/${esc(e.warranty_file)}" target="_blank" class="pd-file-badge">🛡️ ${t('warranty_label')}</a>` : ''}
                        ${ws ? `<span class="badge ${ws.cls}">${ws.label}</span>` : ''}
                    </div>
                    <div class="pd-exp-actions">
                        <div class="pd-exp-menu-wrap">
                            <button type="button" class="pd-exp-menu-trigger js-exp-menu" data-eid="${eid}" title="إجراءات" aria-haspopup="menu" aria-expanded="false" onclick="toggleExpMenu(event, ${eid})">⋮</button>
                            <div class="pd-exp-menu" id="expMenu-${eid}" role="menu">
                                <button type="button" class="pd-exp-menu-item" onclick="closeAllExpMenus(); window.editExpense(${eid});">✏️ <span data-i18n="edit">تعديل</span></button>
                                <button type="button" class="pd-exp-menu-item" onclick="closeAllExpMenus(); attachToExpense(${eid}, 'invoice');">📄 <span data-i18n="add_invoice">إضافة فاتورة</span></button>
                                <button type="button" class="pd-exp-menu-item" onclick="closeAllExpMenus(); attachToExpense(${eid}, 'warranty');">🛡️ <span data-i18n="add_warranty">إضافة ضمان</span></button>
                                <div class="pd-exp-menu-sep"></div>
                                <button type="button" class="pd-exp-menu-item pd-exp-menu-danger" onclick="closeAllExpMenus(); window.deleteExpense(${eid});">🗑️ <span data-i18n="delete">حذف</span></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            ${e.warranty_id && e.warranty_end ? `<div class="pd-exp-warranty">🛡️ ${t('pd_warranty_ends_in')}: ${fmtDate(e.warranty_end)} — ${t('pd_remaining')} ${Math.max(0, e.warranty_days_left)} ${t('pd_days')}</div>` : ''}
        </div>`;
        });
        html += '</div>';
        el.innerHTML = html;
        if (typeof window.applyLang === 'function') {
            window.applyLang(localStorage.getItem('mizan_lang') || 'ar');
        }
    }

    // ── Direct, foolproof handlers (exposed on window so inline onclick
    //    in the rendered template can reach them). The action buttons
    //    (Edit / Delete / Attach) wire onclick="..." directly so a
    //    broken delegation can't kill them. We still keep a tiny
    //    document listener purely to close menus on outside-click.
    window.closeAllExpMenus = function closeAllExpMenus() {
        document.querySelectorAll('.pd-exp-menu.open').forEach(m => m.classList.remove('open'));
        document.querySelectorAll('.js-exp-menu[aria-expanded="true"]')
            .forEach(b => b.setAttribute('aria-expanded', 'false'));
    };

    window.toggleExpMenu = function toggleExpMenu(ev, eid) {
        if (ev) { ev.preventDefault(); ev.stopPropagation(); }
        const targetId = `expMenu-${eid}`;
        document.querySelectorAll('.pd-exp-menu.open').forEach(m => {
            if (m.id !== targetId) m.classList.remove('open');
        });
        const menu = document.getElementById(targetId);
        const trigger = document.querySelector(`.js-exp-menu[data-eid="${eid}"]`);
        if (menu) {
            const isOpen = menu.classList.toggle('open');
            if (trigger) trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }
    };

    window.attachToExpense = function attachToExpense(eid, type) {
        if (typeof window.openSmartUpload !== 'function') return;
        window.openSmartUpload({
            projectId: PROJECT_ID,
            expenseId: parseInt(eid, 10),
            type: type,
            onSuccess: () => { expensesCache = null; loadExpenses(); },
        });
    };

    // Outside-click + Escape close any open expense menu.
    document.addEventListener('click', function(ev) {
        if (!ev.target.closest('.pd-exp-menu-wrap')) window.closeAllExpMenus();
    });
    document.addEventListener('keydown', function(ev) {
        if (ev.key === 'Escape') window.closeAllExpMenus();
    });

    // ── Expense Edit / Delete (PK-driven, attached to every row) ──
    window.deleteExpense = function(id) {
        const eid = parseInt(id, 10);
        if (!eid) {
            showToast(t('invalid_id'), 'error');
            return;
        }
        Swal.fire({
            title: t('delete_expense_confirm'),
            text: t('delete_expense_msg'),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: t('delete'),
            cancelButtonText: t('cancel'),
        }).then(r => {
            if (!r.isConfirmed) return;
            const fd = new FormData();
            fd.append('expense_id', eid);
            mizanFetch('/api/projects.php?action=delete_expense', {
                    method: 'POST',
                    body: fd
                })
                .then(rr => rr.json())
                .then(d => {
                    if (!d.success) {
                        showToast(d.error || t('error_title'), 'error');
                        return;
                    }
                    expensesCache = (expensesCache || []).filter(x => parseInt(x.id, 10) !== eid);
                    renderExpenses(expensesCache);
                    updateAnalyticsPanel();
                    showToast(t('deleted_ok'), 'success');
                })
                .catch(() => showToast(t('cannot_delete_short'), 'error'));
        });
    };

    window.editExpense = function(id) {
        const eid = parseInt(id, 10);
        const e = (expensesCache || []).find(x => parseInt(x.id, 10) === eid);
        if (!e) { showToast(t('expense_not_found'), 'error'); return; }

        // Populate the edit modal with existing data
        document.getElementById('editExpenseId').value  = eid;
        document.getElementById('editExpTitle').value   = e.title || '';
        document.getElementById('editExpAmount').value  = e.amount || '';
        document.getElementById('editExpDate').value    = e.purchase_date ? e.purchase_date.substring(0, 10) : '';
        document.getElementById('editExpVendor').value  = e.vendor || '';
        document.getElementById('editExpNotes').value   = e.notes || '';

        // Set category: match existing value, fall back to custom input
        const catSel = document.getElementById('editExpCat');
        const catCustom = document.getElementById('editExpCustomCat');
        let matched = false;
        for (let i = 0; i < catSel.options.length; i++) {
            if (catSel.options[i].value === e.category || catSel.options[i].text === e.category) {
                catSel.selectedIndex = i;
                matched = true;
                break;
            }
        }
        if (!matched) {
            catSel.value = 'other';
            catCustom.style.display = 'block';
            catCustom.value = e.category || '';
        } else {
            catCustom.style.display = catSel.value === 'other' ? 'block' : 'none';
        }

        openModal('editExpenseModal');
    };

    window.onEditCategoryChange = function(sel) {
        const custom = document.getElementById('editExpCustomCat');
        if (custom) custom.style.display = sel.value === 'other' ? 'block' : 'none';
    };

    window.submitEditExpense = async function(e) {
        e.preventDefault();
        const btn = document.getElementById('saveEditExpenseBtn');
        btn.disabled = true;
        const origText = btn.textContent;
        btn.textContent = t('saving') || 'جاري الحفظ...';

        const eid = parseInt(document.getElementById('editExpenseId').value, 10);
        const catSel = document.getElementById('editExpCat');
        const catVal = catSel.value === 'other' && document.getElementById('editExpCustomCat').value.trim()
            ? document.getElementById('editExpCustomCat').value.trim()
            : catSel.options[catSel.selectedIndex]?.text || catSel.value;

        const fd = new FormData();
        fd.append('id', eid);
        fd.append('title', document.getElementById('editExpTitle').value.trim());
        fd.append('amount', document.getElementById('editExpAmount').value);
        fd.append('category', catVal);
        fd.append('vendor', document.getElementById('editExpVendor').value.trim());
        fd.append('purchase_date', document.getElementById('editExpDate').value);
        fd.append('notes', document.getElementById('editExpNotes').value.trim());

        try {
            const res  = await mizanFetch('/api/expenses.php?action=update', { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.success) { showToast(data.error || t('error_title'), 'error'); return; }

            // Update cache
            const cached = (expensesCache || []).find(x => parseInt(x.id, 10) === eid);
            if (cached) {
                cached.title = document.getElementById('editExpTitle').value.trim();
                cached.amount = parseFloat(document.getElementById('editExpAmount').value) || 0;
                cached.category = catVal;
                cached.vendor = document.getElementById('editExpVendor').value.trim();
                cached.purchase_date = document.getElementById('editExpDate').value;
                cached.notes = document.getElementById('editExpNotes').value.trim();
            }
            renderExpenses(expensesCache);
            updateAnalyticsPanel();
            closeModal('editExpenseModal');
            showToast(t('saved_ok'), 'success');
        } catch { showToast(t('cannot_save'), 'error'); }
        finally { btn.disabled = false; btn.textContent = origText; }
    };

    // ── Warranties ──────────────────────────────────────────
    function loadWarranties() {
        if (expensesCache) {
            renderWarranties(expensesCache);
            return;
        }
        mizanFetch(`/api/expenses.php?action=list&project_id=${PROJECT_ID}`)
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;
                expensesCache = d.expenses;
                renderWarranties(d.expenses);
            })
            .catch(() => showToast(t('load_failed_short'), 'error'));
    }

    // Renders the warranties.
    function renderWarranties(expenses) {
        const el = document.getElementById('tabContent');
        const wlist = expenses.filter(e => e.warranty_id);
        if (!wlist.length) {
            el.innerHTML =
                `<div class="pd-empty"><div class="pd-empty-icon">🛡️</div><h3>${t('pd_no_warranties_title')}</h3><p>${t('pd_no_warranties_desc')}</p></div>`;
            return;
        }
        let html = '<div class="pd-warranties">';
        wlist.forEach(w => {
            const ws = warrantyStatus(w.warranty_days_left, true);
            const days = parseInt(w.warranty_days_left);
            html += `<div class="pd-w-card">
            <div class="pd-w-header">
                <div>
                    <div class="pd-w-title">${esc(w.title)}</div>
                    <div class="text-muted" style="font-size:13px;">من ${fmtDate(w.warranty_start)} حتى ${fmtDate(w.warranty_end)}</div>
                </div>
                <span class="badge ${ws.cls}">${ws.label}</span>
            </div>
            <div class="pd-w-bar">
                <span style="font-size:13px;color:${ws.color};font-weight:700;">
                    ${days < 0 ? `${t('pd_expired_since')} ${Math.abs(days)} ${t('pd_days')}` : `${t('pd_remaining')} ${days} ${t('pd_days')}`}
                </span>
                ${w.warranty_file ? `<a href="/${esc(w.warranty_file)}" target="_blank" class="btn btn-ghost btn-sm">📄 ملف الضمان</a>` : ''}
            </div>
        </div>`;
        });
        html += '</div>';
        el.innerHTML = html;
    }

    // ── Files ───────────────────────────────────────────────
    function renderFiles() {
        const el = document.getElementById('tabContent');
        if (!FILES_DATA.length) {
            el.innerHTML =
                `<div class="pd-empty"><div class="pd-empty-icon">📁</div><h3>${t('pd_no_files_title')}</h3><p>${t('pd_no_files_desc')}</p></div>`;
            return;
        }
        const imgExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        let html = '<div class="pd-files">';
        FILES_DATA.forEach(f => {
            const ext = (f.file_path || '').split('.').pop().toLowerCase();
            const isImg = imgExts.includes(ext);
            html += `<div class="pd-f-card">
            ${isImg
                ? `<div class="pd-f-preview"><img src="/${esc(f.file_path)}" alt="${esc(f.file_name)}" onclick="previewImage(this.src)"></div>`
                : `<div class="pd-f-preview"><span style="font-size:36px;">📄</span></div>`}
            <div class="pd-f-name" title="${esc(f.file_name)}">${esc((f.file_name || '').substring(0, 24))}</div>
            <div class="pd-f-size">${fmtSize(f.file_size)}</div>
            <a href="/${esc(f.file_path)}" download class="btn btn-ghost btn-sm" style="width:100%;margin-top:8px;">⬇️ تحميل</a>
        </div>`;
        });
        html += '</div>';
        el.innerHTML = html;
    }

    // ── Add Expense via mizanFetch ──────────────────────────
    window.submitExpense = async function(e) {
        e.preventDefault();
        const form = document.getElementById('expenseForm');
        const btn = document.getElementById('submitExpenseBtn');
        btn.disabled = true;
        btn.textContent = 'جاري الحفظ...';

        try {
            const fd = new FormData();
            fd.append('action', 'create');
            fd.append('project_id', PROJECT_ID);
            fd.append('title', form.title.value.trim());
            fd.append('amount', form.amount.value);
            const catSel = form.category;
            const catVal = catSel.value === 'other' && form.custom_category?.value.trim()
                ? form.custom_category.value.trim()
                : catSel.options[catSel.selectedIndex]?.text || catSel.value;
            fd.append('category', catVal);
            fd.append('vendor', form.vendor.value.trim());
            fd.append('purchase_date', form.purchase_date.value);
            fd.append('notes', form.notes.value.trim());
            fd.append('warranty_start', form.warranty_start?.value || '');
            fd.append('warranty_end', form.warranty_end?.value || '');

            const res = await mizanFetch('/api/expenses.php', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            if (!data.success) {
                showToast(t(data.error) || t('generic_error'), 'error');
                return;
            }

            const expenseId = data.data?.id;

            // Upload invoice if file selected
            const fileInput = form.querySelector('input[name="invoice"]');
            if (fileInput?.files[0] && expenseId) {
                const f = fileInput.files[0];
                const MAX = 500 * 1024 * 1024;
                if (f.size > MAX) {
                    showToast(`${t('file_too_large')} (${(f.size/1048576).toFixed(1)} MB)`, 'error');
                    return;
                }
                const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
                if (f.type && !allowed.includes(f.type)) {
                    showToast(t('file_type_unsupported'), 'error');
                    return;
                }
                const ufd = new FormData();
                ufd.append('file', f);
                ufd.append('type', 'invoice');
                ufd.append('project_id', PROJECT_ID);
                ufd.append('expense_id', expenseId);
                await mizanFetch('/api/upload.php', {
                    method: 'POST',
                    body: ufd
                });
            }

            closeModal('addExpenseModal');
            form.reset();
            form.purchase_date.value = new Date().toISOString().slice(0, 10);
            showToast(t('expense_added_ok'), 'success');
            expensesCache = null;
            switchTab('expenses');
            // Update stat counts
            setTimeout(() => location.reload(), 800);
        } catch (err) {
            showToast(t('expense_add_failed') + ': ' + err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = t('save_expense');
        }
    };

    // ── Helpers ──────────────────────────────────────────────
    function warrantyStatus(days, hasId) {
        if (!hasId) return null;
        days = parseInt(days);
        if (days < 0) return {
            label: t('w_status_expired'),
            cls: 'badge-danger',
            color: 'var(--danger)'
        };
        if (days <= 7) return {
            label: t('w_status_expiring_days'),
            cls: 'badge-danger',
            color: 'var(--danger)'
        };
        if (days <= 30) return {
            label: t('w_status_expiring_soon'),
            cls: 'badge-warning',
            color: 'var(--warning)'
        };
        return {
            label: t('w_status_active'),
            cls: 'badge-success',
            color: 'var(--success)'
        };
    }

    // Defines the esc routine.
    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    // Defines the fmtNum routine.
    function fmtNum(n) {
        return Number(n).toLocaleString('en-US', {
            maximumFractionDigits: 0
        });
    }

    // Defines the fmtDate routine.
    function fmtDate(d) {
        if (!d) return '';
        const p = d.split('-');
        return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : d;
    }

    // Defines the fmtSize routine.
    function fmtSize(b) {
        b = parseInt(b) || 0;
        if (b > 1048576) return (b / 1048576).toFixed(1) + ' MB';
        if (b > 1024) return (b / 1024).toFixed(1) + ' KB';
        return b + ' B';
    }

    window.previewImage = function(src) {
        document.getElementById('previewImg').src = src;
        openModal('imagePreviewModal');
    };

    // ── Category context-awareness ──────────────────────────────
    const CATEGORIES_COMMERCIAL = [
        'عام','رواتب','إيجار','خدمات','تسويق','مستلزمات مكتبية','عمولات','ضرائب','فواتير','صيانة','مواصلات','طعام','other'
    ];
    const CATEGORIES_PERSONAL = <?= json_encode(array_map(
        fn($c) => $c === 'أخرى' ? 'other' : $c,
        $categories
    )) ?>;

    // Builds the category options.
    function buildCategoryOptions(selectEl, cats) {
        const cur = selectEl.value;
        selectEl.innerHTML = '';
        const arabicOther = 'أخرى';
        cats.forEach(v => {
            const opt = document.createElement('option');
            opt.value   = v;
            opt.textContent = v === 'other' ? arabicOther : v;
            selectEl.appendChild(opt);
        });
        // Restore previous selection if still present
        if ([...selectEl.options].some(o => o.value === cur)) selectEl.value = cur;
    }

    (function initCategorySelects() {
        const addSel    = document.getElementById('expenseCategorySelect');
        const addCustom = document.getElementById('expenseCustomCategory');
        const editSel   = document.getElementById('editExpCat');
        const editCustom = document.getElementById('editExpCustomCat');
        const cats      = IS_COMMERCIAL ? CATEGORIES_COMMERCIAL : CATEGORIES_PERSONAL;

        if (addSel) {
            buildCategoryOptions(addSel, cats);
            addSel.addEventListener('change', e => {
                if (addCustom) addCustom.style.display = e.target.value === 'other' ? 'block' : 'none';
            });
        }
        if (editSel) {
            buildCategoryOptions(editSel, cats);
            editSel.addEventListener('change', e => {
                if (editCustom) editCustom.style.display = e.target.value === 'other' ? 'block' : 'none';
            });
        }
    })();

    window.onCategoryChange = function(sel) {
        const custom = document.getElementById('expenseCustomCategory');
        if (custom) custom.style.display = sel.value === 'other' ? 'block' : 'none';
    };

    window.toggleWarrantyFields = function() {
        const f = document.getElementById('warrantyFields');
        const i = document.getElementById('warrantyToggleIcon');
        const open = f.style.display === 'none';
        f.style.display = open ? 'block' : 'none';
        i.textContent = open ? '▼' : '▶';
    };

    // ── Init ────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        // Animate budget bar
        document.querySelectorAll('.pd-bar-fill').forEach(bar => {
            const pct = parseInt(bar.dataset.pct) || 0;
            bar.style.background = pct >= 90 ? 'var(--danger)' : pct >= 75 ? 'var(--warning)' :
                'var(--primary)';
            setTimeout(() => bar.style.width = pct + '%', 100);
        });

        // Budget percentage color
        document.querySelectorAll('.pd-budget-pct').forEach(el => {
            const pct = parseInt(el.dataset.pct) || 0;
            el.style.color = pct >= 90 ? 'var(--danger)' : pct >= 75 ? 'var(--warning)' :
                'var(--success)';
        });

        // Count-up
        document.querySelectorAll('.count-up').forEach(el => {
            const target = parseFloat(el.dataset.target) || 0;
            const start = performance.now();
            (function step(now) {
                const p = Math.min((now - start) / 1200, 1);
                el.textContent = Math.round(target * (1 - Math.pow(1 - p, 4))).toLocaleString(
                    'en');
                if (p < 1) requestAnimationFrame(step);
            })(start);
        });

        // Load first tab
        switchTab('expenses');

        // When the user toggles language from the navbar, re-render the
        // active tab so JS-built strings (empty states, badges, warranty
        // text) follow the new locale alongside data-i18n nodes.
        window.addEventListener('mizan:lang-change', () => {
            const active = document.querySelector('.pd-tab.active')?.dataset.tab || 'expenses';
            switchTab(active);
        });

        // Toast on status update
        <?php if (isset($_GET['updated'])): ?>
        showToast(t('project_status_updated'), 'success');
        <?php endif; ?>
    });

})();
</script>

<?php require_once '../includes/footer.php'; ?>