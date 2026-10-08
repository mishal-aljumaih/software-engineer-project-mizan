<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: projects.php
 * PURPOSE: Project CRUD, share-link issuance, and budget endpoints.
 * ========================================================================
 */
// ============================================================
// Mizan ميزان — api/projects.php
// ------------------------------------------------------------
// Central JSON API for projects, expenses listing, expense
// deletion, project status updates, project summary, and
// warranty updates.
//
// All responses are JSON except the browser-link delete flow,
// which issues a 302 redirect back to pages/projects.php.
//
// Response conventions (matched to existing frontend):
//   - Default:              { success, message, data }
//   - action=expenses:      { success, expenses, error? }
//   - action=update_warranty: { success, error? }
//
// Auth: inline session check — NOT includes/auth.php, because
// that helper redirects to register.php (HTML), which would
// break fetch()/AJAX callers expecting JSON.
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';

require_once __DIR__ . "/../includes/security.php";
mz_send_security_headers();
mz_session_start();

// ── Helpers ─────────────────────────────────────────────────

/** Issue a 302 redirect and terminate. */
function redirect_out(string $url): void {
    header('Location: ' . $url);
    exit;
}

/** True when the caller is an AJAX request that expects JSON. */
function expects_json(): bool {
    $accept = $_SERVER['HTTP_ACCEPT']          ?? '';
    $xrw    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return stripos($accept, 'application/json') !== false
        || strcasecmp($xrw, 'XMLHttpRequest')  === 0;
}

/**
 * Safely delete a file referenced by a relative path inside /uploads.
 * Uses realpath() + str_starts_with($root) to defeat path-traversal — even if the
 * DB contained "../../config/db.php" the resolved path would fail the prefix check.
 */
function unlink_rel(string $rel_path): void {
    if ($rel_path === '') return;
    $full = realpath(__DIR__ . '/../' . ltrim($rel_path, '/\\'));
    $root = realpath(__DIR__ . '/../uploads');
    if ($full && $root && str_starts_with($full, $root) && is_file($full)) {
        @unlink($full);
    }
}

/**
 * Ownership check — every write/delete endpoint MUST call this first.
 * Prevents Insecure Direct Object Reference (IDOR): a logged-in user supplying
 * another user's project_id cannot modify it because the query is scoped by user_id.
 */
function owns_project(PDO $pdo, int $project_id, int $user_id): bool {
    $s = $pdo->prepare('SELECT 1 FROM projects WHERE id = ? AND user_id = ?');
    $s->execute([$project_id, $user_id]);
    return (bool) $s->fetchColumn();
}

/** Ownership: does expense belong to a project owned by user? */
function owns_expense(PDO $pdo, int $expense_id, int $user_id): bool {
    $s = $pdo->prepare('
        SELECT 1 FROM expenses e
        JOIN projects p ON p.id = e.project_id
        WHERE e.id = ? AND p.user_id = ?
    ');
    $s->execute([$expense_id, $user_id]);
    return (bool) $s->fetchColumn();
}

/** Ownership: does warranty belong (via expense→project) to user? */
function owns_warranty(PDO $pdo, int $warranty_id, int $user_id): bool {
    $s = $pdo->prepare('
        SELECT 1 FROM warranties w
        JOIN expenses  e ON e.id = w.expense_id
        JOIN projects  p ON p.id = e.project_id
        WHERE w.id = ? AND p.user_id = ?
    ');
    $s->execute([$warranty_id, $user_id]);
    return (bool) $s->fetchColumn();
}

// ── Auth gate (API-friendly, returns JSON 401) ──────────────
if (empty($_SESSION['user_id'])) {
    json_out(['success' => false, 'error' => 'err_unauthorized'], 401);
}

// CSRF validation for state-changing requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        json_out(['success' => false, 'error' => 'err_csrf_invalid'], 403);
    }
}

$current_user_id = (int) $_SESSION['user_id'];
$pdo             = getDB();
$action          = $_GET['action'] ?? $_POST['action'] ?? '';

/** Detect optional column once (cached). */
function projects_has_nature_column(PDO $pdo): bool {
    static $cached = null;
    if ($cached !== null) return $cached;
    try {
        $r = $pdo->query("SHOW COLUMNS FROM projects LIKE 'nature'")->fetch();
        $cached = (bool) $r;
    } catch (Throwable $e) { $cached = false; }
    return $cached;
}

// ── Route ───────────────────────────────────────────────────
try {
    switch ($action) {

        // ══════════════════════════════════════════════════════
        // LIST ALL PROJECTS for the current user
        // Returns aggregated expense totals + warranty counts
        // ══════════════════════════════════════════════════════
        case 'list': {
            $s = $pdo->prepare('
                SELECT p.*,
                       COALESCE(SUM(e.amount), 0) AS total_spent,
                       COUNT(DISTINCT e.id)        AS expense_count,
                       COUNT(DISTINCT w.id)         AS warranty_count,
                       SUM(CASE WHEN w.end_date BETWEEN CURDATE()
                           AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                           THEN 1 ELSE 0 END)       AS expiring_warranties
                FROM projects p
                LEFT JOIN expenses   e ON e.project_id = p.id
                LEFT JOIN warranties w ON w.expense_id = e.id
                WHERE p.user_id = ?
                GROUP BY p.id
                ORDER BY p.created_at DESC
            ');
            $s->execute([$current_user_id]);
            $projects = $s->fetchAll();

            // Cast numeric fields
            foreach ($projects as &$p) {
                $p['budget']      = (float) $p['budget'];
                $p['sell_price']  = (float) $p['sell_price'];
                $p['total_spent'] = (float) $p['total_spent'];
                $p['expense_count']        = (int) $p['expense_count'];
                $p['warranty_count']       = (int) $p['warranty_count'];
                $p['expiring_warranties']  = (int) $p['expiring_warranties'];
                $p['budget_pct'] = $p['budget'] > 0
                    ? min(100, (int) round(($p['total_spent'] / $p['budget']) * 100))
                    : 0;
            }
            unset($p);

            json_out(['success' => true, 'projects' => $projects]);
        }

        // ══════════════════════════════════════════════════════
        // CREATE a new project
        // ══════════════════════════════════════════════════════
        case 'create': {
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

            $name   = trim((string) ($input['name'] ?? ''));
            $type   = (string) ($input['type']   ?? 'custom');
            $nature = strtolower((string) ($input['nature'] ?? 'personal'));
            if (!in_array($nature, ['personal','commercial'], true)) $nature = 'personal';
            $budget = (float)  ($input['budget'] ?? 0);
            $color  = trim((string) ($input['color']  ?? '#3b82f6'));
            $notes  = trim((string) ($input['notes']  ?? ''));

            if ($name === '') {
                json_out(['success' => false, 'error' => 'اسم المشروع مطلوب'], 400);
            }
            if (mb_strlen($name) > 200) {
                $name = mb_substr($name, 0, 200);
            }

            $allowed_types = ['car', 'house', 'occasion', 'work', 'devices', 'custom'];
            if (!in_array($type, $allowed_types, true)) $type = 'custom';
            if ($budget < 0) $budget = 0;
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#3b82f6';
            if (mb_strlen($notes) > 5000) $notes = mb_substr($notes, 0, 5000);

            $template       = (string) ($input['template'] ?? '');
            $template_items = is_array($input['template_items'] ?? null)
                ? array_values(array_filter(array_map('trim', $input['template_items']), fn($v) => $v !== ''))
                : [];

            $pdo->beginTransaction();

            if (projects_has_nature_column($pdo)) {
                $s = $pdo->prepare('
                    INSERT INTO projects (user_id, name, type, nature, budget, color, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ');
                $s->execute([$current_user_id, $name, $type, $nature, $budget, $color, $notes]);
            } else {
                $s = $pdo->prepare('
                    INSERT INTO projects (user_id, name, type, budget, color, notes)
                    VALUES (?, ?, ?, ?, ?, ?)
                ');
                $s->execute([$current_user_id, $name, $type, $budget, $color, $notes]);
            }
            $new_id = (int) $pdo->lastInsertId();

            // Smart templates — starter expenses with amount=0.
            // If the client sent an explicit checklist (template_items[]),
            // insert only those. Otherwise fall back to the full preset
            // that matches the `template` key (back-compat).
            $templates = [
                'car'      => ['صيانة', 'تأمين', 'فحص', 'استبدال إطارات', 'تغيير زيت'],
                'house'    => ['سباكة', 'كهرباء', 'عظم', 'دهان', 'تشطيبات', 'أثاث'],
                'software' => ['سيرفرات', 'دومين', 'تصميم UI/UX', 'تسويق'],
                'wedding'  => ['القاعة', 'الضيافة', 'الدعوات', 'التصوير'],
                'travel'   => ['تذاكر طيران', 'فندق', 'مواصلات', 'أنشطة'],
            ];

            $items_to_insert = [];
            if (!empty($template_items)) {
                // Trust only items that exist in the matching preset.
                $allowed = $templates[$template] ?? array_merge(...array_values($templates));
                $items_to_insert = array_values(array_intersect($template_items, $allowed));
            } elseif (isset($templates[$template])) {
                $items_to_insert = $templates[$template];
            }

            if (!empty($items_to_insert)) {
                $ins = $pdo->prepare('
                    INSERT INTO expenses (project_id, title, amount, category, purchase_date)
                    VALUES (?, ?, 0, ?, CURDATE())
                ');
                foreach ($items_to_insert as $cat) {
                    $ins->execute([$new_id, $cat, $cat]);
                }
            }

            $pdo->commit();

            logActivity($pdo, $current_user_id, 'create_project', $name);
            json_out(['success' => true, 'message' => 'تم إنشاء المشروع', 'data' => ['id' => $new_id]]);
        }

        // ══════════════════════════════════════════════════════
        // UPDATE PROJECT (name, type, nature, budget, color, notes)
        // ══════════════════════════════════════════════════════
        case 'update': {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                json_out(['success' => false, 'error' => 'Method Not Allowed'], 405);
            }
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

            $id     = (int)    ($input['id']     ?? 0);
            $name   = trim((string) ($input['name']   ?? ''));
            $type   = (string) ($input['type']   ?? 'custom');
            $nature = strtolower((string) ($input['nature'] ?? 'personal'));
            $budget = (float)  ($input['budget'] ?? 0);
            $color  = trim((string) ($input['color']  ?? '#3b82f6'));
            $notes  = trim((string) ($input['notes']  ?? ''));

            if ($id <= 0 || !owns_project($pdo, $id, $current_user_id)) {
                json_out(['success' => false, 'error' => 'err_unauthorized'], 403);
            }
            if ($name === '') {
                json_out(['success' => false, 'error' => 'اسم المشروع مطلوب'], 400);
            }
            if (mb_strlen($name) > 200) $name = mb_substr($name, 0, 200);

            $allowed_types = ['car', 'house', 'occasion', 'work', 'devices', 'custom'];
            if (!in_array($type, $allowed_types, true)) $type = 'custom';
            if (!in_array($nature, ['personal', 'commercial'], true)) $nature = 'personal';
            if ($budget < 0) $budget = 0;
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#3b82f6';
            if (mb_strlen($notes) > 5000) $notes = mb_substr($notes, 0, 5000);

            if (projects_has_nature_column($pdo)) {
                $pdo->prepare('
                    UPDATE projects SET name=?, type=?, nature=?, budget=?, color=?, notes=?
                    WHERE id=? AND user_id=?
                ')->execute([$name, $type, $nature, $budget, $color, $notes, $id, $current_user_id]);
            } else {
                $pdo->prepare('
                    UPDATE projects SET name=?, type=?, budget=?, color=?, notes=?
                    WHERE id=? AND user_id=?
                ')->execute([$name, $type, $budget, $color, $notes, $id, $current_user_id]);
            }

            logActivity($pdo, $current_user_id, 'update_project', $name);
            json_out(['success' => true, 'message' => 'تم تحديث المشروع', 'data' => [
                'id' => $id, 'name' => $name, 'type' => $type,
                'nature' => $nature, 'budget' => $budget, 'color' => $color, 'notes' => $notes,
            ]]);
        }

        // ══════════════════════════════════════════════════════
        // DELETE PROJECT — dual mode (browser link OR AJAX)
        // ══════════════════════════════════════════════════════
        case 'delete': {
            $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

            if ($id <= 0 || !owns_project($pdo, $id, $current_user_id)) {
                if (expects_json()) {
                    json_out(['success' => false, 'error' => 'err_unauthorized'], 403);
                }
                redirect_out('../pages/projects.php?error=unauthorized');
            }

            // Collect every physical file path under this project BEFORE
            // the CASCADE removes the rows.
            $paths = [];

            $s = $pdo->prepare('SELECT file_path FROM files WHERE project_id = ?');
            $s->execute([$id]);
            foreach ($s->fetchAll() as $r) $paths[] = $r['file_path'];

            $s = $pdo->prepare('
                SELECT i.file_path FROM invoices i
                JOIN expenses e ON e.id = i.expense_id
                WHERE e.project_id = ?
            ');
            $s->execute([$id]);
            foreach ($s->fetchAll() as $r) $paths[] = $r['file_path'];

            $s = $pdo->prepare('
                SELECT w.file_path FROM warranties w
                JOIN expenses e ON e.id = w.expense_id
                WHERE e.project_id = ?
                  AND w.file_path IS NOT NULL
                  AND w.file_path <> ""
            ');
            $s->execute([$id]);
            foreach ($s->fetchAll() as $r) $paths[] = $r['file_path'];

            // FK ON DELETE CASCADE handles expenses/invoices/warranties/files rows.
            $s = $pdo->prepare('DELETE FROM projects WHERE id = ? AND user_id = ?');
            $s->execute([$id, $current_user_id]);

            foreach (array_unique(array_filter($paths)) as $p) {
                unlink_rel((string) $p);
            }

            if (expects_json()) {
                json_out(['success' => true, 'message' => 'تم حذف المشروع']);
            }
            redirect_out('../pages/projects.php?deleted=1');
        }

        // ══════════════════════════════════════════════════════
        // PROJECT SUMMARY — totals, profit, budget usage
        // ══════════════════════════════════════════════════════
        case 'summary': {
            $id = (int) ($_GET['id'] ?? 0);

            if ($id <= 0 || !owns_project($pdo, $id, $current_user_id)) {
                json_out(['success' => false, 'error' => 'err_unauthorized'], 403);
            }

            $s = $pdo->prepare('
                SELECT p.*,
                       COALESCE(SUM(e.amount), 0) AS total_cost,
                       COUNT(DISTINCT e.id)       AS expense_count
                FROM projects p
                LEFT JOIN expenses e ON e.project_id = p.id
                WHERE p.id = ?
                GROUP BY p.id
            ');
            $s->execute([$id]);
            $data = $s->fetch() ?: [];

            $sell       = (float) ($data['sell_price'] ?? 0);
            $budget     = (float) ($data['budget']     ?? 0);
            $total_cost = (float) ($data['total_cost'] ?? 0);

            $data['profit']     = $sell > 0 ? ($sell - $total_cost) : null;
            $data['profit_pct'] = ($sell > 0 && $total_cost > 0)
                ? round((($sell - $total_cost) / $total_cost) * 100, 2)
                : null;
            $data['budget_pct'] = calc_budget_pct($total_cost, $budget);
            $data['nature'] = $data['nature'] ?? 'personal';

            json_out(['success' => true, 'data' => $data]);
        }

        // ══════════════════════════════════════════════════════
        // LIST EXPENSES OF A PROJECT (special response shape —
        // frontend reads d.expenses directly)
        // ══════════════════════════════════════════════════════
        case 'expenses': {
            $pid = (int) ($_GET['project_id'] ?? 0);

            if ($pid <= 0 || !owns_project($pdo, $pid, $current_user_id)) {
                json_out([
                    'success'  => false,
                    'error'    => 'err_unauthorized',
                    'expenses' => [],
                ], 403);
            }

            $s = $pdo->prepare('
                SELECT id, title, amount, category, vendor, purchase_date, notes, created_at
                FROM expenses
                WHERE project_id = ?
                ORDER BY purchase_date DESC, created_at DESC
            ');
            $s->execute([$pid]);

            json_out([
                'success'  => true,
                'expenses' => $s->fetchAll(),
            ]);
        }

        // ══════════════════════════════════════════════════════
        // UPDATE PROJECT STATUS + SELL PRICE
        // ══════════════════════════════════════════════════════
        case 'update_status': {
            $id     = (int)    ($_POST['id']         ?? 0);
            $status = (string) ($_POST['status']     ?? 'active');
            $sell   = (float)  ($_POST['sell_price'] ?? 0);

            if (!in_array($status, ['active', 'done', 'archived'], true)) {
                json_out(['success' => false, 'error' => 'حالة غير صالحة']);
            }
            if ($id <= 0 || !owns_project($pdo, $id, $current_user_id)) {
                json_out(['success' => false, 'error' => 'err_unauthorized'], 403);
            }
            if ($sell < 0) $sell = 0.0;

            $s = $pdo->prepare('
                UPDATE projects
                SET status = ?, sell_price = ?
                WHERE id = ? AND user_id = ?
            ');
            $s->execute([$status, $sell, $id, $current_user_id]);

            json_out(['success' => true, 'message' => 'تم التحديث']);
        }

        // ══════════════════════════════════════════════════════
        // DELETE EXPENSE (cascades invoices/warranties/files rows)
        // ══════════════════════════════════════════════════════
        case 'delete_expense': {
            $eid = (int) ($_POST['expense_id'] ?? 0);

            if ($eid <= 0 || !owns_expense($pdo, $eid, $current_user_id)) {
                json_out(['success' => false, 'error' => 'err_unauthorized'], 403);
            }

            // Collect physical paths before cascade
            $paths = [];

            $s = $pdo->prepare('SELECT file_path FROM invoices WHERE expense_id = ?');
            $s->execute([$eid]);
            foreach ($s->fetchAll() as $r) $paths[] = $r['file_path'];

            $s = $pdo->prepare('SELECT file_path FROM files WHERE expense_id = ?');
            $s->execute([$eid]);
            foreach ($s->fetchAll() as $r) $paths[] = $r['file_path'];

            $s = $pdo->prepare('
                SELECT file_path FROM warranties
                WHERE expense_id = ? AND file_path IS NOT NULL AND file_path <> ""
            ');
            $s->execute([$eid]);
            foreach ($s->fetchAll() as $r) $paths[] = $r['file_path'];

            $pdo->prepare('DELETE FROM expenses WHERE id = ?')->execute([$eid]);

            foreach (array_unique(array_filter($paths)) as $p) {
                unlink_rel((string) $p);
            }

            json_out(['success' => true, 'message' => 'تم الحذف']);
        }

        // ══════════════════════════════════════════════════════
        // UPDATE WARRANTY (end date + notes)
        // frontend reads d.error on failure
        // ══════════════════════════════════════════════════════
        case 'update_warranty': {
            $id    = (int)    ($_POST['id']       ?? 0);
            $end   = trim((string) ($_POST['end_date'] ?? ''));
            $notes = trim((string) ($_POST['notes']    ?? ''));

            if ($id <= 0 || !owns_warranty($pdo, $id, $current_user_id)) {
                json_out(['success' => false, 'error' => 'err_unauthorized'], 403);
            }

            // Validate YYYY-MM-DD
            $d = DateTime::createFromFormat('Y-m-d', $end);
            if (!$d || $d->format('Y-m-d') !== $end) {
                json_out(['success' => false, 'error' => 'تاريخ غير صالح']);
            }

            // notes column is VARCHAR(255) — trim defensively
            if (mb_strlen($notes) > 255) {
                $notes = mb_substr($notes, 0, 255);
            }

            $s = $pdo->prepare('
                UPDATE warranties
                SET end_date = ?, notes = ?
                WHERE id = ?
            ');
            $s->execute([$end, $notes, $id]);

            json_out(['success' => true, 'message' => 'تم الحفظ']);
        }

        // ══════════════════════════════════════════════════════
        // GLOBAL SEARCH (projects + expenses)
        // ══════════════════════════════════════════════════════
        case 'search': {
            $q = trim((string) ($_GET['q'] ?? ''));
            if (mb_strlen($q) < 2) {
                json_out(['success' => true, 'results' => []]);
            }
            $like = '%' . $q . '%';
            $results = [];

            // Search projects
            $s = $pdo->prepare('
                SELECT id, name, type, status, created_at
                FROM projects WHERE user_id = ? AND name LIKE ?
                LIMIT 8
            ');
            $s->execute([$current_user_id, $like]);
            $icons = ['car'=>'🚗','house'=>'🏠','occasion'=>'🎉','work'=>'💼','devices'=>'📦','custom'=>'✏️'];
            foreach ($s->fetchAll() as $p) {
                $results[] = [
                    'title' => $p['name'],
                    'icon'  => $icons[$p['type']] ?? '✏️',
                    'meta'  => 'مشروع — ' . date('Y/m/d', strtotime($p['created_at'])),
                    'url'   => '/pages/project-detail.php?id=' . $p['id'],
                ];
            }

            // Search expenses
            $s = $pdo->prepare('
                SELECT e.id, e.title, e.amount, e.project_id, p.name AS pname
                FROM expenses e
                JOIN projects p ON p.id = e.project_id
                WHERE p.user_id = ? AND (e.title LIKE ? OR e.vendor LIKE ?)
                LIMIT 8
            ');
            $s->execute([$current_user_id, $like, $like]);
            foreach ($s->fetchAll() as $e) {
                $results[] = [
                    'kind'  => 'expense',
                    'title' => $e['title'],
                    'icon'  => '💸',
                    'meta'  => $e['pname'] . ' — ' . mz_format_money((float) $e['amount'], 0),
                    'url'   => '/pages/project-detail.php?id=' . $e['project_id'],
                ];
            }

            // Search files
            $s = $pdo->prepare('
                SELECT f.id, f.file_name, f.file_path, p.id AS pid, p.name AS pname
                FROM files f
                JOIN projects p ON p.id = f.project_id
                WHERE p.user_id = ? AND f.file_name LIKE ?
                LIMIT 8
            ');
            $s->execute([$current_user_id, $like]);
            foreach ($s->fetchAll() as $f) {
                $ext = strtolower(pathinfo((string)$f['file_path'], PATHINFO_EXTENSION));
                $icon = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? '🖼️'
                      : ($ext === 'pdf' ? '📕'
                      : (in_array($ext, ['doc','docx']) ? '📝' : '📄'));
                $results[] = [
                    'kind'  => 'file',
                    'title' => $f['file_name'],
                    'icon'  => $icon,
                    'meta'  => $f['pname'] . ' — ' . strtoupper($ext),
                    'url'   => '/' . $f['file_path'],
                ];
            }

            // Tag projects too so the client can group
            foreach ($results as &$r) {
                if (!isset($r['kind'])) $r['kind'] = 'project';
            }
            unset($r);

            json_out(['success' => true, 'results' => $results]);
        }

        // ══════════════════════════════════════════════════════
        // ISSUE SECURE SHARE LINK TOKEN (random + expiry)
        // ══════════════════════════════════════════════════════
        case 'create_share_link': {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                json_out(['success' => false, 'error' => 'Method Not Allowed'], 405);
            }
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $pid = (int) ($input['project_id'] ?? 0);
            if ($pid <= 0 || !owns_project($pdo, $pid, $current_user_id)) {
                json_out(['success' => false, 'error' => 'err_unauthorized'], 403);
            }

            // Revoke previous active tokens for this project to keep a single live link.
            $pdo->prepare('
                UPDATE project_share_tokens
                SET revoked_at = NOW()
                WHERE project_id = ? AND user_id = ? AND revoked_at IS NULL
            ')->execute([$pid, $current_user_id]);

            $token = bin2hex(random_bytes(32)); // 256-bit random token
            $expires_at = (new DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s');

            $pdo->prepare('
                INSERT INTO project_share_tokens (project_id, user_id, token, expires_at)
                VALUES (?, ?, ?, ?)
            ')->execute([$pid, $current_user_id, $token, $expires_at]);

            // Derive base URL from current request so local dev works without reconfiguring APP_URL
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
            $baseUrl = ($isHttps ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? parse_url(APP_URL, PHP_URL_HOST));
            $url = $baseUrl . '/share.php?t=' . rawurlencode($token);

            json_out([
                'success' => true,
                'url' => $url,
                'expires_at' => $expires_at,
            ]);
        }

        // ══════════════════════════════════════════════════════
        // REVOKE SHARE LINKS (single or all for project)
        // ══════════════════════════════════════════════════════
        case 'revoke_share_links': {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                json_out(['success' => false, 'error' => 'Method Not Allowed'], 405);
            }
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $pid = (int) ($input['project_id'] ?? 0);
            if ($pid <= 0 || !owns_project($pdo, $pid, $current_user_id)) {
                json_out(['success' => false, 'error' => 'err_unauthorized'], 403);
            }

            $token = trim((string) ($input['token'] ?? ''));
            if ($token !== '') {
                $pdo->prepare('
                    UPDATE project_share_tokens
                    SET revoked_at = NOW()
                    WHERE project_id = ? AND user_id = ? AND token = ? AND revoked_at IS NULL
                ')->execute([$pid, $current_user_id, $token]);
            } else {
                $pdo->prepare('
                    UPDATE project_share_tokens
                    SET revoked_at = NOW()
                    WHERE project_id = ? AND user_id = ? AND revoked_at IS NULL
                ')->execute([$pid, $current_user_id]);
            }

            json_out(['success' => true, 'message' => 'تم إلغاء روابط المشاركة']);
        }

        // ══════════════════════════════════════════════════════
        // CLONE PROJECT (copy project + all expenses, no files)
        // ══════════════════════════════════════════════════════
        case 'clone_project': {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                json_out(['success' => false, 'error' => 'Method Not Allowed'], 405);
            }
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $pid   = (int) ($input['project_id'] ?? 0);
            if ($pid <= 0 || !owns_project($pdo, $pid, $current_user_id)) {
                json_out(['success' => false, 'error' => 'err_unauthorized'], 403);
            }

            // Fetch source project
            $src = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
            $src->execute([$pid, $current_user_id]);
            $proj = $src->fetch();
            if (!$proj) {
                json_out(['success' => false, 'error' => 'المشروع غير موجود'], 404);
            }

            $new_name = (isset($input['new_name']) && trim($input['new_name']) !== '')
                ? mb_substr(trim($input['new_name']), 0, 200)
                : 'نسخة من ' . $proj['name'];

            $pdo->prepare("
                INSERT INTO projects (user_id, name, type, nature, status, color, budget, notes)
                VALUES (?, ?, ?, ?, 'active', ?, ?, ?)
            ")->execute([
                $current_user_id,
                $new_name,
                $proj['type']   ?? 'custom',
                $proj['nature'] ?? 'personal',
                $proj['color']  ?? '#3b82f6',
                $proj['budget'] ?? 0,
                $proj['notes']  ?? '',
            ]);
            $new_pid = (int) $pdo->lastInsertId();

            // Copy expenses (no invoices/files — user will re-attach)
            $expenses = $pdo->prepare("SELECT * FROM expenses WHERE project_id = ?");
            $expenses->execute([$pid]);
            $ins = $pdo->prepare("
                INSERT INTO expenses (project_id, title, amount, category, purchase_date, vendor, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($expenses->fetchAll() as $exp) {
                $ins->execute([
                    $new_pid,
                    $exp['title'],
                    $exp['amount'],
                    $exp['category']      ?? '',
                    $exp['purchase_date'] ?? null,
                    $exp['vendor']        ?? '',
                    $exp['notes']         ?? '',
                ]);
            }

            logActivity($pdo, $current_user_id, 'clone_project', $new_name);
            json_out([
                'success'    => true,
                'message'    => 'تم نسخ المشروع بنجاح',
                'new_project_id' => $new_pid,
                'new_name'   => $new_name,
            ]);
        }

        // ══════════════════════════════════════════════════════
        default:
            json_out([
                'success' => false,
                'error'   => 'action غير معروف: ' . htmlspecialchars((string) $action),
            ], 400);
    }
} catch (Throwable $e) {
    json_out([
        'success' => false,
        'error'   => 'خطأ في الخادم: ' . $e->getMessage(),
    ], 500);
}
