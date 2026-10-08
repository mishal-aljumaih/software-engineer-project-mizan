<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: invoices.php
 * PURPOSE: Invoices management UI: create, list, export.
 * ========================================================================
 */
require_once '../includes/auth.php';
require_once '../config/db.php';

$pdo        = getDB();
$page_title = 'الفواتير';

// ─── Query-string filters ─────────────────────────────────
// All filter inputs are sanitised before use; every query joins on projects.user_id for ownership
$project_id = intval($_GET['project'] ?? 0);
$search     = trim($_GET['q'] ?? '');
$type_f     = $_GET['type'] ?? 'all'; // all | image | pdf

// ─── جلب المشاريع للقائمة ─────────────────────────────────
$projects = $pdo->prepare("
    SELECT id, name, color FROM projects
    WHERE user_id = ? ORDER BY name
");
$projects->execute([$current_user_id]);
$projects = $projects->fetchAll();

// ─── بناء الاستعلام ───────────────────────────────────────
$where  = "WHERE p.user_id = ?";
$params = [$current_user_id];

if ($project_id > 0) {
    $where   .= " AND p.id = ?";
    $params[] = $project_id;
}
if ($search !== '') {
    $where   .= " AND (e.title LIKE ? OR e.vendor LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($type_f !== 'all') {
    $where   .= " AND i.file_type = ?";
    $params[] = $type_f;
}

$stmt = $pdo->prepare("
    SELECT i.id, i.file_path, i.file_type, i.uploaded_at,
           e.title    AS expense_title,
           e.amount,
           e.purchase_date,
           e.vendor,
           p.id       AS project_id,
           p.name     AS project_name,
           p.color    AS project_color
    FROM invoices i
    JOIN expenses e ON e.id = i.expense_id
    JOIN projects p ON p.id = e.project_id
    {$where}
    ORDER BY i.uploaded_at DESC
");
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// ─── إحصائيات ─────────────────────────────────────────────
$stats = $pdo->prepare("
    SELECT
        COUNT(i.id)                              AS total,
        SUM(CASE WHEN i.file_type='pdf'   THEN 1 ELSE 0 END) AS pdfs,
        SUM(CASE WHEN i.file_type='image' THEN 1 ELSE 0 END) AS images,
        COALESCE(SUM(e.amount),0)                AS total_amount
    FROM invoices i
    JOIN expenses e ON e.id = i.expense_id
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
        <h1 class="page-title">📄 <span data-i18n="invoices">الفواتير</span></h1>
        <p class="page-sub" data-i18n="invoices_desc">أرشيف فواتير وإيصالات مشاريعك</p>
    </div>
    <div class="ph-actions">
        <button class="btn btn-primary" onclick="openSmartUpload({ type: 'invoice' })">
            ⬆️ <span data-i18n="add_invoice">رفع فاتورة</span>
        </button>
    </div>
</div>

<!-- ═══ Stats ═══ -->
<div class="stats-row">
    <div class="stat-card">
        <div class="stat-icon">📄</div>
        <div class="stat-body">
            <div class="stat-num"><?= $st['total'] ?></div>
            <div class="stat-lbl" data-i18n="invoice_total">إجمالي الفواتير</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🖼️</div>
        <div class="stat-body">
            <div class="stat-num"><?= $st['images'] ?></div>
            <div class="stat-lbl">صور</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📕</div>
        <div class="stat-body">
            <div class="stat-num"><?= $st['pdfs'] ?></div>
            <div class="stat-lbl">ملفات PDF</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-body">
            <div class="stat-num"><?= number_format($st['total_amount'], 0) ?> <small><?= mz_currency_symbol() ?></small></div>
            <div class="stat-lbl">إجمالي المبالغ</div>
        </div>
    </div>
</div>

<!-- ═══ Filters ═══ -->
<div class="filter-bar">
    <form method="GET" action="" class="filter-form">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="بحث في الفواتير..."
            class="filter-input" onchange="this.form.submit()">

        <select name="project" class="filter-select" onchange="this.form.submit()">
            <option value="0">كل المشاريع</option>
            <?php foreach ($projects as $pr): ?>
            <option value="<?= $pr['id'] ?>" <?= $project_id == $pr['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($pr['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>

        <div class="filter-tabs">
            <a href="?type=all&project=<?= $project_id ?>&q=<?= urlencode($search) ?>"
                class="ftab <?= $type_f==='all' ? 'on' : '' ?>">الكل</a>
            <a href="?type=image&project=<?= $project_id ?>&q=<?= urlencode($search) ?>"
                class="ftab <?= $type_f==='image' ? 'on' : '' ?>">🖼️ صور</a>
            <a href="?type=pdf&project=<?= $project_id ?>&q=<?= urlencode($search) ?>"
                class="ftab <?= $type_f==='pdf' ? 'on' : '' ?>">📕 PDF</a>
        </div>

        <button type="submit" class="btn btn-secondary">🔍 بحث</button>
    </form>
</div>

<!-- ═══ Invoices Grid ═══ -->
<?php if (empty($invoices)): ?>
<div class="empty-state">
    <div class="empty-icon">📄</div>
    <h3 data-i18n="no_invoices">لا توجد فواتير</h3>
    <p>ارفع أول فاتورة لأحد مشاريعك</p>
    <button class="btn btn-primary" onclick="openSmartUpload({ type: 'invoice' })">⬆️ <span data-i18n="add_invoice">رفع فاتورة</span></button>
</div>

<?php else: ?>
<div class="invoices-grid" id="invoicesGrid">
    <?php foreach ($invoices as $inv): ?>
    <div class="inv-card" data-id="<?= $inv['id'] ?>">
        <!-- Preview -->
        <div class="inv-preview"
            onclick="previewInvoice('<?= htmlspecialchars('/' . $inv['file_path']) ?>', '<?= htmlspecialchars($inv['file_type'], ENT_QUOTES) ?>')">
            <?php if ($inv['file_type'] === 'image'): ?>
            <img src="/<?= htmlspecialchars($inv['file_path']) ?>" alt="فاتورة" loading="lazy"
                onerror="this.src='/assets/img/invoice-placeholder.png'">
            <?php else: ?>
            <div class="pdf-thumb">
                <div class="pdf-icon">📕</div>
                <div class="pdf-label">PDF</div>
            </div>
            <?php endif; ?>
            <div class="inv-hover-overlay">
                <span>🔍 معاينة</span>
            </div>
        </div>

        <!-- Info -->
        <div class="inv-info">
            <div class="inv-title"><?= htmlspecialchars($inv['expense_title']) ?></div>
            <div class="inv-meta">
                <span class="inv-proj" style="border-color:<?= htmlspecialchars($inv['project_color']) ?>">
                    <?= htmlspecialchars($inv['project_name']) ?>
                </span>
                <?php if ($inv['vendor']): ?>
                <span class="inv-vendor">🏪 <?= htmlspecialchars($inv['vendor']) ?></span>
                <?php endif; ?>
            </div>
            <div class="inv-row">
                <span class="inv-amount"><?= mz_format_money($inv['amount'], 0) ?></span>
                <span
                    class="inv-date"><?= date('Y/m/d', strtotime($inv['purchase_date'] ?? $inv['uploaded_at'])) ?></span>
            </div>
        </div>

        <!-- Actions -->
        <div class="inv-actions">
            <a href="/<?= htmlspecialchars($inv['file_path']) ?>" download class="btn-icon" title="تحميل">⬇️</a>
            <button
                onclick="previewInvoice('/<?= htmlspecialchars($inv['file_path']) ?>','<?= $inv['file_type'] ?>')"
                class="btn-icon" title="معاينة">🔍</button>
            <button onclick="deleteInvoice(<?= $inv['id'] ?>)" class="btn-icon danger" title="حذف">🗑️</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Legacy upload modal removed — all uploads now flow through openSmartUpload({type:'invoice'}) -->

<!-- ═══ Preview Modal ═══ -->
<div class="modal" id="previewModal">
    <div class="modal-overlay" onclick="closePreview()"></div>
    <div class="modal-box modal-large">
        <div class="modal-header">
            <h3>🔍 معاينة الفاتورة</h3>
            <button class="modal-close" onclick="closePreview()">✕</button>
        </div>
        <div class="modal-body" id="previewBody"
            style="min-height:400px;display:flex;align-items:center;justify-content:center;">
        </div>
        <div class="modal-footer">
            <a id="previewDownload" href="#" download class="btn btn-primary">⬇️ تحميل</a>
            <button class="btn btn-ghost" onclick="closePreview()">إغلاق</button>
        </div>
    </div>
</div>

<style>
/* ═══ Invoices Specific Styles ═══ */
.invoices-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 18px;
    margin-top: 24px;
}

.inv-card {
    background: var(--surf);
    border: 1.5px solid var(--bdr);
    border-radius: 16px;
    overflow: hidden;
    transition: all .3s cubic-bezier(.22, 1, .36, 1);
}

.inv-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 36px rgba(0, 0, 0, .1);
    border-color: rgba(0, 108, 53, .25);
}

.inv-preview {
    position: relative;
    height: 160px;
    background: var(--s2);
    cursor: pointer;
    overflow: hidden;
}

.inv-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .35s;
}

.inv-card:hover .inv-preview img {
    transform: scale(1.06);
}

.pdf-thumb {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.pdf-icon {
    font-size: 48px;
}

.pdf-label {
    font-size: 13px;
    font-weight: 700;
    color: var(--mut);
    background: var(--bdr);
    padding: 3px 12px;
    border-radius: 20px;
}

.inv-hover-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, .45);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity .25s;
    color: #fff;
    font-weight: 700;
    font-size: 15px;
}

.inv-card:hover .inv-hover-overlay {
    opacity: 1;
}

.inv-info {
    padding: 12px 14px 8px;
}

.inv-title {
    font-weight: 700;
    font-size: 13.5px;
    color: var(--txt);
    margin-bottom: 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.inv-meta {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 8px;
}

.inv-proj {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 12px;
    border: 1.5px solid;
    color: var(--txt);
    font-weight: 600;
}

.inv-vendor {
    font-size: 11px;
    color: var(--mut);
}

.inv-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.inv-amount {
    font-size: 13px;
    font-weight: 900;
    color: var(--g);
}

.inv-date {
    font-size: 11px;
    color: var(--mut);
}

.inv-actions {
    display: flex;
    gap: 6px;
    padding: 8px 12px;
    border-top: 1px solid var(--bdr);
    background: var(--s2);
}

.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid var(--bdr);
    background: var(--surf);
    cursor: pointer;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all .2s;
    text-decoration: none;
}

.btn-icon:hover {
    border-color: var(--g);
    transform: scale(1.1);
}

.btn-icon.danger:hover {
    border-color: #EF4444;
    background: rgba(239, 68, 68, .06);
}

/* Drop Zone */
.drop-zone {
    border: 2px dashed var(--bdr);
    border-radius: 14px;
    padding: 32px 20px;
    text-align: center;
    transition: all .25s;
    cursor: pointer;
}

.drop-zone.drag-over {
    border-color: var(--g);
    background: var(--glow);
}

.dz-icon {
    font-size: 36px;
    margin-bottom: 8px;
}

.dz-text {
    font-size: 14px;
    color: var(--mut);
    margin-bottom: 10px;
}

.dz-hint {
    font-size: 11.5px;
    color: var(--mut);
    margin-top: 8px;
}

/* Progress */
.upload-progress {
    margin-top: 14px;
}

.up-bar {
    height: 8px;
    background: var(--s2);
    border-radius: 4px;
    overflow: hidden;
}

.up-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--g), var(--gl));
    width: 0%;
    transition: width .3s;
    border-radius: 4px;
}

.up-text {
    font-size: 12px;
    color: var(--mut);
    margin-top: 6px;
    text-align: center;
}

/* Upload preview */
.upload-preview {
    margin-top: 12px;
    text-align: center;
}

.upload-preview img {
    max-height: 160px;
    border-radius: 10px;
    border: 1px solid var(--bdr);
}

.up-file-info {
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--s2);
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 13px;
}

/* Modal large */
.modal-large {
    max-width: 800px;
    width: 95%;
}

.modal-large .modal-body img {
    max-width: 100%;
    border-radius: 10px;
}

.modal-large .modal-body iframe {
    width: 100%;
    height: 500px;
    border: none;
    border-radius: 10px;
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

.stat-num small {
    font-size: 13px;
    font-weight: 400;
    color: var(--mut);
}

.stat-lbl {
    font-size: 12px;
    color: var(--mut);
    margin-top: 2px;
}

/* Filter bar */
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

.filter-select {
    min-width: 160px;
}

.filter-tabs {
    display: flex;
    gap: 4px;
    background: var(--s2);
    border-radius: 10px;
    padding: 3px;
}

.ftab {
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: var(--mut);
    text-decoration: none;
    transition: all .2s;
}

.ftab.on {
    background: var(--surf);
    color: var(--g);
    box-shadow: 0 2px 6px rgba(0, 0, 0, .06);
}
</style>

<script>
// ─── Preview ─────────────────────────────────────────────
function previewInvoice(url, type) {
    const body = document.getElementById('previewBody');
    document.getElementById('previewDownload').href = url;
    body.innerHTML = '';
    if (type === 'pdf') {
        const frame = document.createElement('iframe');
        frame.setAttribute('src', url);
        frame.style.cssText = 'width:100%;height:500px;border:none;border-radius:10px;';
        body.appendChild(frame);
    } else {
        const img = document.createElement('img');
        img.setAttribute('src', url);
        img.style.cssText = 'max-width:100%;border-radius:10px;';
        img.onerror = function() { this.src = '/assets/img/invoice-placeholder.png'; };
        body.appendChild(img);
    }
    document.getElementById('previewModal').classList.add('active');
}

// Closes the preview.
function closePreview() {
    document.getElementById('previewModal').classList.remove('active');
    document.getElementById('previewBody').innerHTML = '';
}

// ─── Delete ──────────────────────────────────────────────
function deleteInvoice(id) {
    Swal.fire({
        title: 'حذف الفاتورة؟',
        text: 'سيتم حذف الملف نهائياً',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        confirmButtonText: 'حذف',
        cancelButtonText: 'إلغاء'
    }).then(r => {
        if (!r.isConfirmed) return;
        mizanFetch('/api/upload.php', {
                method: 'POST',
                body: new URLSearchParams({
                    action: 'delete_invoice',
                    id
                })
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    document.querySelector(`.inv-card[data-id="${id}"]`)?.remove();
                    Swal.fire({
                        icon: 'success',
                        title: 'تم الحذف',
                        timer: 1200,
                        showConfirmButton: false
                    });
                }
            });
    });
}

</script>

<?php require_once '../includes/footer.php'; ?>
