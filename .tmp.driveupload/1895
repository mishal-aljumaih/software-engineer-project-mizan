<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: files.php
 * PURPOSE: Files browser for all project-attached documents.
 * OWNER: Alwaleed Alzahrani - Development & Database Admin (Lead Programmer)
 * ========================================================================
 */
require_once '../includes/auth.php';
require_once '../config/db.php';

$pdo        = getDB();
$page_title = 'الخزنة — Mizan Vault';

// ─── Detect file_kind column availability (back-compat for old installs) ──
function vault_has_kind_column(PDO $pdo): bool {
    static $has = null;
    if ($has !== null) return $has;
    try {
        $s = $pdo->query("SHOW COLUMNS FROM files LIKE 'file_kind'");
        $has = (bool) $s->fetchColumn();
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}
$has_kind = vault_has_kind_column($pdo);

// ─── Query-string filters ─────────────────────────────────
// All filter inputs are coerced (intval / float / whitelist) — never interpolated directly into SQL
$project_id = intval($_GET['project'] ?? 0);
$search     = trim($_GET['q']   ?? '');
$sort       = $_GET['sort']     ?? 'newest';                       // newest | oldest | size | name
$tab        = strtolower((string)($_GET['tab'] ?? 'all'));         // all | invoice | warranty | contract | other
$min_price  = isset($_GET['min']) && $_GET['min'] !== '' ? (float) $_GET['min'] : null;
$max_price  = isset($_GET['max']) && $_GET['max'] !== '' ? (float) $_GET['max'] : null;

// Whitelist tab values — anything outside the allowed set silently collapses to 'all' (no SQL injection vector)
if (!in_array($tab, ['all','invoice','warranty','contract','other'], true)) {
    $tab = 'all';
}

// ─── المشاريع ─────────────────────────────────────────────
$projects_stmt = $pdo->prepare("SELECT id, name, color FROM projects WHERE user_id = ? ORDER BY name");
$projects_stmt->execute([$current_user_id]);
$projects = $projects_stmt->fetchAll();

// ─── بناء الاستعلام ───────────────────────────────────────
// derived_kind is a single expression that's usable in every install:
//   • if file_kind column exists → use it
//   • otherwise → infer from joined invoice/warranty existence + filename prefix
$derived_kind = $has_kind
    ? 'f.file_kind'
    : "CASE
          WHEN i2.id IS NOT NULL THEN 'invoice'
          WHEN w2.id IS NOT NULL THEN 'warranty'
          WHEN f.file_name LIKE '[20%' THEN 'contract'
          ELSE 'other'
       END";

$where  = "WHERE p.user_id = ?";
$params = [$current_user_id];

if ($project_id > 0) { $where .= " AND p.id = ?"; $params[] = $project_id; }
if ($search !== '')  { $where .= " AND (f.file_name LIKE ? OR e.title LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

if ($tab !== 'all') {
    $where .= " AND {$derived_kind} = ?";
    $params[] = $tab;
}
if ($min_price !== null) { $where .= " AND e.amount >= ?"; $params[] = $min_price; }
if ($max_price !== null) { $where .= " AND e.amount <= ?"; $params[] = $max_price; }

$order = match($sort) {
    'oldest' => 'f.uploaded_at ASC',
    'size'   => 'f.file_size DESC',
    'name'   => 'f.file_name ASC',
    default  => 'f.uploaded_at DESC',
};

$sql = "
    SELECT f.*,
           p.name   AS project_name,
           p.color  AS project_color,
           e.title  AS expense_title,
           e.amount AS expense_amount,
           {$derived_kind} AS derived_kind
    FROM files f
    JOIN projects p ON p.id = f.project_id
    LEFT JOIN expenses e ON e.id = f.expense_id
    " . ($has_kind ? "" : "
    LEFT JOIN invoices   i2 ON i2.file_path = f.file_path
    LEFT JOIN warranties w2 ON w2.file_path = f.file_path
    ") . "
    {$where}
    ORDER BY {$order}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$files = $stmt->fetchAll();

// ─── Per-tab counters (single aggregated query) ──────────────
$cnt_sql = "
    SELECT {$derived_kind} AS k, COUNT(*) AS c
    FROM files f
    JOIN projects p ON p.id = f.project_id
    LEFT JOIN expenses e ON e.id = f.expense_id
    " . ($has_kind ? "" : "
    LEFT JOIN invoices   i2 ON i2.file_path = f.file_path
    LEFT JOIN warranties w2 ON w2.file_path = f.file_path
    ") . "
    WHERE p.user_id = ?
    GROUP BY k
";
$cnt_stmt = $pdo->prepare($cnt_sql);
$cnt_stmt->execute([$current_user_id]);
$tab_counts = ['all' => 0, 'invoice' => 0, 'warranty' => 0, 'contract' => 0, 'other' => 0];
foreach ($cnt_stmt->fetchAll() as $row) {
    $k = $row['k'] ?: 'other';
    $tab_counts[$k]  = (int) $row['c'];
    $tab_counts['all'] += (int) $row['c'];
}

// ─── إحصائيات ─────────────────────────────────────────────
$stats = $pdo->prepare("
    SELECT COUNT(f.id) AS total,
           COALESCE(SUM(f.file_size),0) AS total_size,
           SUM(CASE WHEN f.file_path LIKE '%.pdf'                        THEN 1 ELSE 0 END) AS pdfs,
           SUM(CASE WHEN f.file_path LIKE '%.jpg' OR f.file_path LIKE '%.jpeg'
                    OR f.file_path LIKE '%.png'  OR f.file_path LIKE '%.webp' THEN 1 ELSE 0 END) AS images
    FROM files f
    JOIN projects p ON p.id = f.project_id
    WHERE p.user_id = ?
");
$stats->execute([$current_user_id]);
$st = $stats->fetch();

// Defines the fmtSize routine.
function fmtSize(int $b): string {
    if ($b >= 1073741824) return round($b/1073741824, 1) . ' GB';
    if ($b >= 1048576)    return round($b/1048576, 1)    . ' MB';
    if ($b >= 1024)       return round($b/1024, 1)       . ' KB';
    return $b . ' B';
}
// Defines the fileIcon routine.
function fileIcon(string $path): string {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return match(true) {
        in_array($ext, ['jpg','jpeg','png','gif','webp']) => '🖼️',
        $ext === 'pdf'  => '📕',
        in_array($ext, ['doc','docx']) => '📝',
        in_array($ext, ['xls','xlsx']) => '📊',
        in_array($ext, ['zip','rar'])  => '📦',
        default => '📄',
    };
}

require_once '../includes/header.php';
?>

<div class="page-header">
    <div class="ph-right">
        <h1 class="page-title">🗄️ <span data-i18n="vault_title">الخزنة</span></h1>
        <p class="page-sub" data-i18n="vault_desc">جميع مستنداتك — فواتير، ضمانات، عقود وأخرى</p>
    </div>
    <div class="ph-actions">
        <button class="btn btn-primary" onclick="openSmartUpload()">☁️ <span data-i18n="upload_file">رفع
                ملف</span></button>
    </div>
</div>

<!-- ═══ Stats ═══ -->
<div class="stats-row">
    <div class="stat-card">
        <div class="stat-icon">📁</div>
        <div class="stat-body">
            <div class="stat-num"><?= $st['total'] ?></div>
            <div class="stat-lbl" data-i18n="total_files">إجمالي الملفات</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💾</div>
        <div class="stat-body">
            <div class="stat-num"><?= fmtSize((int)$st['total_size']) ?></div>
            <div class="stat-lbl" data-i18n="total_size">الحجم الكلي</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📕</div>
        <div class="stat-body">
            <div class="stat-num"><?= $st['pdfs'] ?></div>
            <div class="stat-lbl" data-i18n="pdf_files">ملفات PDF</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🖼️</div>
        <div class="stat-body">
            <div class="stat-num"><?= $st['images'] ?></div>
            <div class="stat-lbl" data-i18n="images">صور</div>
        </div>
    </div>
</div>

<!-- ═══ Vault Tabs (top) ═══ -->
<?php
$tabs_def = [
    ['k' => 'all',      'icon' => '🗂️', 'label_ar' => 'الكل',     'i18n' => 'tab_all'],
    ['k' => 'invoice',  'icon' => '🧾', 'label_ar' => 'الفواتير', 'i18n' => 'tab_invoices'],
    ['k' => 'warranty', 'icon' => '🛡️', 'label_ar' => 'الضمانات', 'i18n' => 'tab_vault_warranties'],
    ['k' => 'contract', 'icon' => '📜', 'label_ar' => 'العقود',   'i18n' => 'tab_contracts'],
    ['k' => 'other',    'icon' => '📎', 'label_ar' => 'أخرى',     'i18n' => 'tab_other'],
];
?>
<div class="vault-tabs" role="tablist">
    <?php foreach ($tabs_def as $td):
        $active = $tab === $td['k'];
        $qs = http_build_query(array_merge($_GET, ['tab' => $td['k']]));
    ?>
    <a href="?<?= htmlspecialchars($qs) ?>" role="tab" class="vault-tab <?= $active ? 'active' : '' ?>">
        <span class="vt-ico"><?= $td['icon'] ?></span>
        <span class="vt-lbl" data-i18n="<?= $td['i18n'] ?>"><?= htmlspecialchars($td['label_ar']) ?></span>
        <span class="vt-count"><?= (int)($tab_counts[$td['k']] ?? 0) ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- ═══ Secondary Filter Row (project + price range + sort) ═══ -->
<div class="filter-bar vault-filters">
    <form method="GET" action="" class="filter-form">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">

        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="بحث في الملفات..."
            class="filter-input" data-i18n-placeholder="search_files" onchange="this.form.submit()">

        <select name="project" class="filter-select" onchange="this.form.submit()">
            <option value="0" data-i18n="all_projects">كل المشاريع</option>
            <?php foreach ($projects as $pr): ?>
            <option value="<?= $pr['id'] ?>" <?= $project_id == $pr['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($pr['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>

        <div class="price-range">
            <input type="number" step="0.01" min="0" name="min"
                value="<?= $min_price !== null ? htmlspecialchars((string)$min_price) : '' ?>"
                class="filter-input filter-sm" data-i18n-placeholder="min_price" placeholder="أدنى <?= mz_currency_symbol() ?>"
                onchange="this.form.submit()">
            <span class="pr-dash">—</span>
            <input type="number" step="0.01" min="0" name="max"
                value="<?= $max_price !== null ? htmlspecialchars((string)$max_price) : '' ?>"
                class="filter-input filter-sm" data-i18n-placeholder="max_price" placeholder="أعلى <?= mz_currency_symbol() ?>"
                onchange="this.form.submit()">
        </div>

        <select name="sort" class="filter-select" onchange="this.form.submit()">
            <option value="newest" <?= $sort==='newest'?'selected':'' ?> data-i18n="sort_newest">الأحدث أولاً</option>
            <option value="oldest" <?= $sort==='oldest'?'selected':'' ?> data-i18n="sort_oldest">الأقدم أولاً</option>
            <option value="size" <?= $sort==='size'  ?'selected':'' ?> data-i18n="sort_size">الأكبر حجماً</option>
            <option value="name" <?= $sort==='name'  ?'selected':'' ?> data-i18n="sort_name">الاسم</option>
        </select>

        <button type="submit" class="btn btn-secondary">🔍 <span data-i18n="search">بحث</span></button>
    </form>
</div>

<!-- ═══ View Toggle ═══ -->
<div class="view-toggle">
    <button class="vt-btn on" id="gridBtn" onclick="setView('grid')">⊞ <span data-i18n="view_grid">شبكة</span></button>
    <button class="vt-btn" id="listBtn" onclick="setView('list')">☰ <span data-i18n="view_list">قائمة</span></button>
    <span class="files-count"><?= count($files) ?> <span data-i18n="file_unit">ملف</span></span>
</div>

<!-- ═══ Files Grid ═══ -->
<?php if (empty($files)): ?>
<div class="empty-state">
    <div class="empty-icon">📁</div>
    <h3 data-i18n="no_files">لا توجد ملفات</h3>
    <p data-i18n="upload_first_file">ارفع أول ملف لأحد مشاريعك</p>
</div>

<?php else: ?>
<div class="files-container grid-view stagger-enter" id="filesContainer">
    <?php foreach ($files as $f):
        $ext     = strtolower(pathinfo($f['file_path'], PATHINFO_EXTENSION));
        $isImg   = in_array($ext, ['jpg','jpeg','png','gif','webp']);
        $icon    = fileIcon($f['file_path']);
        $url     = '/' . $f['file_path'];
        $kind    = $f['derived_kind'] ?? 'other';
    ?>
    <div class="file-card" data-id="<?= $f['id'] ?>" data-kind="<?= htmlspecialchars($kind) ?>">
        <!-- Preview -->
        <div class="file-thumb" onclick="previewFile('<?= $url ?>', <?= $isImg ? 'true' : 'false' ?>)">
            <?php if ($isImg): ?>
            <img src="<?= $url ?>" alt="<?= htmlspecialchars($f['file_name']) ?>" loading="lazy">
            <?php else: ?>
            <div class="file-icon-big"><?= $icon ?></div>
            <div class="file-ext-badge"><?= strtoupper($ext) ?></div>
            <?php endif; ?>
            <div class="file-hover"><span>🔍</span></div>
        </div>

        <!-- Info (grid view) -->
        <div class="file-info">
            <div class="file-name" title="<?= htmlspecialchars($f['file_name']) ?>">
                <?= $icon ?> <?= htmlspecialchars($f['file_name']) ?>
            </div>
            <div class="file-meta">
                <span class="file-proj" style="border-color:<?= htmlspecialchars($f['project_color']) ?>">
                    <?= htmlspecialchars($f['project_name']) ?>
                </span>
                <span class="file-size"><?= fmtSize((int)$f['file_size']) ?></span>
            </div>
        </div>

        <!-- List view info (hidden by default) -->
        <div class="file-list-row">
            <div class="flr-icon"><?= $icon ?></div>
            <div class="flr-name"><?= htmlspecialchars($f['file_name']) ?></div>
            <div class="flr-proj" style="border-color:<?= htmlspecialchars($f['project_color']) ?>">
                <?= htmlspecialchars($f['project_name']) ?></div>
            <div class="flr-size"><?= fmtSize((int)$f['file_size']) ?></div>
            <div class="flr-date"><?= date('Y/m/d', strtotime($f['uploaded_at'])) ?></div>
            <div class="flr-actions">
                <a href="<?= $url ?>" download class="btn-icon" title="تحميل">⬇️</a>
                <button onclick="previewFile('<?= $url ?>',<?= $isImg ? 'true' : 'false' ?>)"
                    class="btn-icon">🔍</button>
                <button onclick="deleteFile(<?= $f['id'] ?>)" class="btn-icon danger">🗑️</button>
            </div>
        </div>

        <!-- Grid Actions -->
        <div class="file-actions">
            <a href="<?= $url ?>" download class="btn-icon" title="تحميل">⬇️</a>
            <button onclick="deleteFile(<?= $f['id'] ?>)" class="btn-icon danger" title="حذف">🗑️</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>




<style>
/* ═══ Files Styles ═══ */
.view-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 16px 0 8px;
}

.vt-btn {
    padding: 6px 14px;
    border-radius: 8px;
    border: 1.5px solid var(--bdr);
    background: var(--surf);
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: var(--mut);
    transition: all .2s;
}

.vt-btn.on {
    background: var(--g);
    color: #fff;
    border-color: var(--g);
}

.files-count {
    font-size: 13px;
    color: var(--mut);
    margin-right: auto;
}

/* Grid view */
.files-container.grid-view {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 16px;
}

.grid-view .file-list-row {
    display: none !important;
}

.grid-view .file-card {
    background: var(--surf);
    border: 1.5px solid var(--bdr);
    border-radius: 16px;
    overflow: hidden;
    transition: all .3s;
    position: relative;
}

.grid-view .file-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 28px rgba(0, 0, 0, .1);
    border-color: rgba(0, 108, 53, .25);
}

.file-thumb {
    height: 140px;
    background: var(--s2);
    cursor: pointer;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.file-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .3s;
}

.file-card:hover .file-thumb img {
    transform: scale(1.08);
}

.file-icon-big {
    font-size: 46px;
}

.file-ext-badge {
    position: absolute;
    bottom: 6px;
    right: 6px;
    background: rgba(0, 0, 0, .55);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 6px;
}

.file-hover {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, .4);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    opacity: 0;
    transition: opacity .25s;
}

.file-card:hover .file-hover {
    opacity: 1;
}

.file-info {
    padding: 10px 12px 6px;
}

.file-name {
    font-size: 12px;
    font-weight: 700;
    color: var(--txt);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 6px;
}

.file-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.file-proj {
    font-size: 10px;
    padding: 2px 7px;
    border-radius: 10px;
    border: 1.5px solid;
    font-weight: 600;
    color: var(--txt);
}

.file-size {
    font-size: 10.5px;
    color: var(--mut);
}

.file-actions {
    display: flex;
    gap: 5px;
    padding: 6px 10px;
    border-top: 1px solid var(--bdr);
    background: var(--s2);
}

/* List view */
.files-container.list-view {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.list-view .file-thumb,
.list-view .file-info,
.list-view .file-actions {
    display: none !important;
}

.list-view .file-list-row {
    display: flex !important;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--bdr);
    background: var(--surf);
    transition: background .2s;
}

.list-view .file-card:hover .file-list-row {
    background: var(--s2);
}

.list-view .file-card {
    border: 1.5px solid var(--bdr);
    border-radius: 0;
}

.list-view .file-card:first-child {
    border-radius: 14px 14px 0 0;
}

.list-view .file-card:last-child {
    border-radius: 0 0 14px 14px;
}

.flr-icon {
    font-size: 20px;
    flex-shrink: 0;
}

.flr-name {
    flex: 1;
    font-size: 13px;
    font-weight: 600;
    color: var(--txt);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.flr-proj {
    font-size: 11px;
    padding: 2px 9px;
    border-radius: 10px;
    border: 1.5px solid;
    font-weight: 600;
    color: var(--txt);
    white-space: nowrap;
    flex-shrink: 0;
}

.flr-size {
    font-size: 12px;
    color: var(--mut);
    width: 60px;
    flex-shrink: 0;
}

.flr-date {
    font-size: 12px;
    color: var(--mut);
    width: 90px;
    flex-shrink: 0;
}

.flr-actions {
    display: flex;
    gap: 5px;
    flex-shrink: 0;
}

/* Shared */
.btn-icon {
    width: 30px;
    height: 30px;
    border-radius: 7px;
    border: 1px solid var(--bdr);
    background: var(--surf);
    cursor: pointer;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all .2s;
    text-decoration: none;
    flex-shrink: 0;
}

.btn-icon:hover {
    border-color: var(--g);
    transform: scale(1.1);
}

.btn-icon.danger:hover {
    border-color: #EF4444;
    background: rgba(239, 68, 68, .06);
}

/* Drop zone */
.drop-zone {
    border: 2px dashed var(--bdr);
    border-radius: 14px;
    padding: 32px 20px;
    text-align: center;
    transition: all .25s;
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

.upload-preview {
    margin-top: 12px;
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

/* Stats */
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

    .files-container.grid-view {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width:460px) {
    .files-container.grid-view {
        grid-template-columns: 1fr;
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
    min-width: 140px;
}

/* ═══ Vault Tabs (top navigation) ═══ */
.vault-tabs {
    display: flex;
    gap: 6px;
    margin: 18px 0 12px;
    padding: 6px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow-x: auto;
    scrollbar-width: thin;
}

.vault-tab {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 10px;
    text-decoration: none;
    color: var(--text);
    font-weight: 600;
    font-size: 14px;
    white-space: nowrap;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid transparent;
}

.vault-tab:hover {
    background: rgba(0, 108, 53, 0.08);
    color: var(--primary);
    transform: translateY(-1px);
}

.vault-tab.active {
    background: var(--surface);
    color: var(--primary);
    border-color: rgba(0, 108, 53, 0.25);
    box-shadow: 0 4px 14px rgba(0, 108, 53, 0.12);
}

.vt-ico {
    font-size: 18px;
    line-height: 1;
}

.vt-lbl {
    font-weight: 700;
}

.vt-count {
    margin-inline-start: 4px;
    min-width: 22px;
    padding: 1px 8px;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.06);
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 700;
    text-align: center;
}

.vault-tab.active .vt-count {
    background: var(--primary);
    color: #fff;
}

[data-theme="dark"] .vt-count {
    background: rgba(255, 255, 255, 0.08);
}

/* Price range filter */
.price-range {
    display: flex;
    align-items: center;
    gap: 6px;
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 10px;
    padding: 2px 8px;
}

.price-range .filter-input.filter-sm {
    min-width: 0;
    width: 90px;
    border: none;
    background: transparent;
    padding: 8px 4px;
}

.price-range .filter-input.filter-sm:focus {
    outline: none;
}

.pr-dash {
    color: var(--text-muted);
    font-weight: 700;
}

/* ═══ Stagger-Enter Cascade (scoped fallback — also in style.css global) ═══ */
.stagger-enter>.file-card {
    opacity: 0;
    transform: translateY(18px);
    animation: vaultCardIn 0.5s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}

.stagger-enter>.file-card:nth-child(1) {
    animation-delay: 0.02s;
}

.stagger-enter>.file-card:nth-child(2) {
    animation-delay: 0.06s;
}

.stagger-enter>.file-card:nth-child(3) {
    animation-delay: 0.10s;
}

.stagger-enter>.file-card:nth-child(4) {
    animation-delay: 0.14s;
}

.stagger-enter>.file-card:nth-child(5) {
    animation-delay: 0.18s;
}

.stagger-enter>.file-card:nth-child(6) {
    animation-delay: 0.22s;
}

.stagger-enter>.file-card:nth-child(7) {
    animation-delay: 0.26s;
}

.stagger-enter>.file-card:nth-child(8) {
    animation-delay: 0.30s;
}

.stagger-enter>.file-card:nth-child(n+9) {
    animation-delay: 0.34s;
}

.vuc-body {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.vuc-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    background: var(--grad-primary);
    color: #fff;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.22);
}

.vuc-text {
    flex: 1;
    min-width: 220px;
}

.vuc-title {
    font-weight: 800;
    font-size: 15px;
    color: var(--text);
    margin-bottom: 2px;
}

.vuc-sub {
    font-size: 12.5px;
    color: var(--text-muted);
}

@keyframes vaultCardIn {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .stagger-enter>.file-card {
        animation: none;
        opacity: 1;
        transform: none;
    }
}
</style>

<script>
// Defines the setView routine.
function setView(v) {
    const c = document.getElementById('filesContainer');
    if (!c) return;
    c.className = 'files-container ' + v + '-view';
    document.getElementById('gridBtn').classList.toggle('on', v === 'grid');
    document.getElementById('listBtn').classList.toggle('on', v === 'list');
    localStorage.setItem('mz_files_view', v);
}
// Restore view preference
const savedView = localStorage.getItem('mz_files_view') || 'grid';
setView(savedView);

// Defines the previewFile routine.
function previewFile(url, isImg) {
    Swal.fire({
        html: isImg ? `<img src="${url}" style="max-width:100%;border-radius:8px;">` :
            `<iframe src="${url}" style="width:100%;height:500px;border:none;border-radius:8px;"></iframe>`,
        width: isImg ? 'auto' : '80%',
        showConfirmButton: false,
        showCloseButton: true,
    });
}

// Deletes the file.
function deleteFile(id) {
    Swal.fire({
            title: t('delete_file_title'),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#EF4444',
            confirmButtonText: t('delete'),
            cancelButtonText: t('cancel')
        })
        .then(r => {
            if (!r.isConfirmed) return;
            mizanFetch('/api/upload.php', {
                    method: 'POST',
                    body: new URLSearchParams({
                        action: 'delete_file',
                        id
                    })
                })
                .then(r => r.json()).then(d => {
                    if (d.success) {
                        document.querySelector(`.file-card[data-id="${id}"]`)?.remove();
                        Swal.fire({
                            icon: 'success',
                            title: t('deleted_title'),
                            timer: 1200,
                            showConfirmButton: false
                        });
                    }
                });
        });
}
</script>

<?php require_once '../includes/footer.php'; ?>