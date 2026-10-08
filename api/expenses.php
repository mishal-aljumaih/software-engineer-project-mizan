<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: expenses.php
 * PURPOSE: Expense CRUD endpoints and aggregation queries.
 * ========================================================================
 */
// ============================================================
// Mizan ميزان — api/expenses.php
// ------------------------------------------------------------
// Full CRUD for expenses, plus attaching warranty data to an
// expense. Every id the caller supplies is verified through a
// JOIN back to projects.user_id so users can only touch their
// own data.
//
// Response shape:
//   - Default:           { success, message?, data?, error? }
//   - action=list:       { success, expenses:[...], error? }
//
// Auth: inline session check (returns JSON 401 — does NOT use
// includes/auth.php, which redirects to HTML).
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/notifications_helper.php';
require_once __DIR__ . '/../includes/api_helpers.php';

require_once __DIR__ . "/../includes/security.php";
mz_send_security_headers();
mz_session_start();

// Defines the unlink rel routine.
function unlink_rel(string $rel_path): void {
    if ($rel_path === '') return;
    $full = realpath(__DIR__ . '/../' . ltrim($rel_path, '/\\'));
    $root = realpath(__DIR__ . '/../uploads');
    if ($full && $root && str_starts_with($full, $root) && is_file($full)) {
        @unlink($full);
    }
}

// Ownership guard — joins back to projects.user_id so users can never write expenses into another user's project (IDOR defense)
function owns_project(PDO $pdo, int $project_id, int $user_id): bool {
    $s = $pdo->prepare('SELECT 1 FROM projects WHERE id = ? AND user_id = ?');
    $s->execute([$project_id, $user_id]);
    return (bool) $s->fetchColumn();
}

/**
 * Returns the full expense row (including project owner) if the caller owns it,
 * or null otherwise. Single lookup — avoids a round-trip just to ownership-check.
 */
function fetch_owned_expense(PDO $pdo, int $expense_id, int $user_id): ?array {
    $s = $pdo->prepare('
        SELECT e.*, p.user_id AS owner_id, p.name AS project_name, p.budget, p.id AS pid
        FROM expenses e
        JOIN projects p ON p.id = e.project_id
        WHERE e.id = ? AND p.user_id = ?
    ');
    $s->execute([$expense_id, $user_id]);
    $row = $s->fetch();
    return $row ?: null;
}

/** Valid YYYY-MM-DD? */
function valid_date(string $d): bool {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

/** Check budget after a new expense; push a "budget exceeded" notification if needed. */
function check_budget(PDO $pdo, int $user_id, int $project_id, string $project_name, float $budget): void {
    if ($budget <= 0) return;

    $s = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE project_id = ?');
    $s->execute([$project_id]);
    $total = (float) $s->fetchColumn();

    if ($total > $budget) {
        $over = $total - $budget;
        $msg  = "⚠️ تجاوزت ميزانية مشروع «{$project_name}» بمقدار " . mz_format_money($over, 0);
        push_notification($pdo, $user_id, $msg, 'budget');
    }
}

// ── Auth gate ───────────────────────────────────────────────
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

// ── Route ───────────────────────────────────────────────────
try {
    switch ($action) {

        // ══════════════════════════════════════════════════════
        // LIST EXPENSES FOR A PROJECT
        // ══════════════════════════════════════════════════════
        case 'list': {
            $pid = (int) ($_GET['project_id'] ?? 0);

            if ($pid <= 0 || !owns_project($pdo, $pid, $current_user_id)) {
                json_out([
                    'success'  => false,
                    'error'    => 'err_unauthorized',
                    'expenses' => [],
                ], 403);
            }

            // Subqueries pick the LATEST invoice + warranty per expense so
            // each expense row is unique — even if multiple files are attached.
            // A single expense may carry one invoice AND one warranty simultaneously.
            $s = $pdo->prepare('
                SELECT e.id, e.title, e.amount, e.category, e.vendor,
                       e.purchase_date, e.notes, e.created_at,
                       w.id        AS warranty_id,
                       w.start_date AS warranty_start,
                       w.end_date  AS warranty_end,
                       w.file_path AS warranty_file,
                       DATEDIFF(w.end_date, CURDATE()) AS warranty_days_left,
                       i.id        AS invoice_id,
                       i.file_path AS invoice_file,
                       i.file_type AS invoice_type
                FROM expenses e
                LEFT JOIN warranties w
                       ON w.id = (SELECT id FROM warranties
                                   WHERE expense_id = e.id
                                   ORDER BY id DESC LIMIT 1)
                LEFT JOIN invoices i
                       ON i.id = (SELECT id FROM invoices
                                   WHERE expense_id = e.id
                                   ORDER BY id DESC LIMIT 1)
                WHERE e.project_id = ?
                ORDER BY e.purchase_date DESC, e.created_at DESC
            ');
            $s->execute([$pid]);

            json_out([
                'success'  => true,
                'expenses' => $s->fetchAll(),
            ]);
        }

        // ══════════════════════════════════════════════════════
        // CREATE EXPENSE
        // Optionally attaches a warranty in the same call if
        // warranty_start / warranty_end are provided.
        // ══════════════════════════════════════════════════════
        case 'create': {
            $project_id    = (int)    ($_POST['project_id']    ?? 0);
            $title         = trim((string) ($_POST['title']    ?? ''));
            $amount        = (float)  ($_POST['amount']        ?? 0);
            $category      = trim((string) ($_POST['category'] ?? 'عام'));
            $vendor        = trim((string) ($_POST['vendor']   ?? ''));
            $purchase_date = (string) ($_POST['purchase_date'] ?? date('Y-m-d'));
            $notes         = trim((string) ($_POST['notes']    ?? ''));

            // Validation
            if ($title === '') {
                json_out(['success' => false, 'error' => 'العنوان مطلوب']);
            }
            if ($amount <= 0) {
                json_out(['success' => false, 'error' => 'المبلغ يجب أن يكون أكبر من صفر']);
            }
            if (!valid_date($purchase_date)) {
                $purchase_date = date('Y-m-d');
            }
            if ($project_id <= 0 || !owns_project($pdo, $project_id, $current_user_id)) {
                json_out(['success' => false, 'error' => 'المشروع غير موجود أو غير مصرح'], 403);
            }

            // Fetch project meta once (budget check uses it)
            $ps = $pdo->prepare('SELECT id, name, budget FROM projects WHERE id = ?');
            $ps->execute([$project_id]);
            $project = $ps->fetch();

            $pdo->beginTransaction();
            try {
                $pdo->prepare('
                    INSERT INTO expenses (project_id, title, amount, category, vendor, purchase_date, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ')->execute([$project_id, $title, $amount, $category, $vendor, $purchase_date, $notes]);

                $expense_id = (int) $pdo->lastInsertId();

                // Optional inline warranty
                $w_start = (string) ($_POST['warranty_start'] ?? '');
                $w_end   = (string) ($_POST['warranty_end']   ?? '');
                $wid     = 0;

                if ($w_end !== '' && valid_date($w_end)) {
                    if ($w_start === '' || !valid_date($w_start)) {
                        $w_start = date('Y-m-d');
                    }
                    $pdo->prepare('
                        INSERT INTO warranties (expense_id, start_date, end_date)
                        VALUES (?, ?, ?)
                    ')->execute([$expense_id, $w_start, $w_end]);
                    $wid = (int) $pdo->lastInsertId();
                }

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            // Post-commit notification work (outside the transaction so it
            // can't cause a rollback on the user's actual expense)
            if ($wid > 0) {
                setup_warranty_notifications($pdo, $current_user_id, $title, $w_end);
            }
            check_budget($pdo, $current_user_id, $project_id, (string) $project['name'], (float) $project['budget']);

            json_out([
                'success' => true,
                'message' => 'تم إضافة العملية',
                'data'    => [
                    'id'          => $expense_id,
                    'warranty_id' => $wid > 0 ? $wid : null,
                ],
            ]);
        }

        // ══════════════════════════════════════════════════════
        // UPDATE EXPENSE
        // ══════════════════════════════════════════════════════
        case 'update': {
            $id = (int) ($_POST['id'] ?? 0);
            $expense = fetch_owned_expense($pdo, $id, $current_user_id);
            if (!$expense) {
                json_out(['success' => false, 'error' => 'err_unauthorized'], 403);
            }

            // Any field may be omitted — fall back to current value.
            $title         = trim((string) ($_POST['title']    ?? $expense['title']));
            $amount        = isset($_POST['amount']) ? (float) $_POST['amount'] : (float) $expense['amount'];
            $category      = trim((string) ($_POST['category'] ?? $expense['category']));
            $vendor        = trim((string) ($_POST['vendor']   ?? (string) $expense['vendor']));
            $purchase_date = (string) ($_POST['purchase_date']  ?? $expense['purchase_date']);
            $notes         = trim((string) ($_POST['notes']    ?? (string) $expense['notes']));

            if ($title === '') {
                json_out(['success' => false, 'error' => 'العنوان مطلوب']);
            }
            if ($amount <= 0) {
                json_out(['success' => false, 'error' => 'المبلغ يجب أن يكون أكبر من صفر']);
            }
            if (!valid_date($purchase_date)) {
                json_out(['success' => false, 'error' => 'تاريخ الشراء غير صالح']);
            }

            $pdo->prepare('
                UPDATE expenses
                SET title = ?, amount = ?, category = ?, vendor = ?, purchase_date = ?, notes = ?
                WHERE id = ?
            ')->execute([$title, $amount, $category, $vendor, $purchase_date, $notes, $id]);

            // Re-check budget in case the edit pushed the project over
            check_budget(
                $pdo,
                $current_user_id,
                (int) $expense['pid'],
                (string) $expense['project_name'],
                (float) $expense['budget']
            );

            json_out(['success' => true, 'message' => 'تم التحديث']);
        }

        // ══════════════════════════════════════════════════════
        // DELETE EXPENSE (+ physical cleanup of attached files)
        // ══════════════════════════════════════════════════════
        case 'delete': {
            $id = (int) ($_POST['id'] ?? 0);
            $expense = fetch_owned_expense($pdo, $id, $current_user_id);
            if (!$expense) {
                json_out(['success' => false, 'error' => 'err_unauthorized'], 403);
            }

            // Collect file paths before cascade removes the rows
            $paths = [];

            $s = $pdo->prepare('SELECT file_path FROM invoices WHERE expense_id = ?');
            $s->execute([$id]);
            foreach ($s->fetchAll() as $r) $paths[] = $r['file_path'];

            $s = $pdo->prepare('SELECT file_path FROM files WHERE expense_id = ?');
            $s->execute([$id]);
            foreach ($s->fetchAll() as $r) $paths[] = $r['file_path'];

            $s = $pdo->prepare('
                SELECT file_path FROM warranties
                WHERE expense_id = ? AND file_path IS NOT NULL AND file_path <> ""
            ');
            $s->execute([$id]);
            foreach ($s->fetchAll() as $r) $paths[] = $r['file_path'];

            $pdo->prepare('DELETE FROM expenses WHERE id = ?')->execute([$id]);

            foreach (array_unique(array_filter($paths)) as $p) {
                unlink_rel((string) $p);
            }

            json_out(['success' => true, 'message' => 'تم حذف العملية']);
        }

        // ══════════════════════════════════════════════════════
        // ADD / UPDATE WARRANTY FOR AN EXPENSE
        // Triggers notification setup logic on success.
        // ══════════════════════════════════════════════════════
        case 'add_warranty': {
            $expense_id = (int) ($_POST['expense_id'] ?? 0);
            $start_date = (string) ($_POST['start_date'] ?? date('Y-m-d'));
            $end_date   = (string) ($_POST['end_date']   ?? '');
            $notes      = trim((string) ($_POST['notes']    ?? ''));

            $expense = fetch_owned_expense($pdo, $expense_id, $current_user_id);
            if (!$expense) {
                json_out(['success' => false, 'error' => 'err_unauthorized'], 403);
            }
            if (!valid_date($end_date)) {
                json_out(['success' => false, 'error' => 'تاريخ انتهاء الضمان مطلوب وصالح']);
            }
            if (!valid_date($start_date)) {
                $start_date = date('Y-m-d');
            }
            if ($end_date < $start_date) {
                json_out(['success' => false, 'error' => 'تاريخ الانتهاء قبل تاريخ البداية']);
            }
            if (mb_strlen($notes) > 255) {
                $notes = mb_substr($notes, 0, 255);
            }

            // Upsert: one warranty row per expense (schema allows many, but
            // the rest of the app treats it as 1:1 — keep it consistent).
            $s = $pdo->prepare('SELECT id FROM warranties WHERE expense_id = ? LIMIT 1');
            $s->execute([$expense_id]);
            $existing = (int) $s->fetchColumn();

            if ($existing > 0) {
                $pdo->prepare('
                    UPDATE warranties
                    SET start_date = ?, end_date = ?, notes = ?
                    WHERE id = ?
                ')->execute([$start_date, $end_date, $notes, $existing]);
                $warranty_id = $existing;
            } else {
                $pdo->prepare('
                    INSERT INTO warranties (expense_id, start_date, end_date, notes)
                    VALUES (?, ?, ?, ?)
                ')->execute([$expense_id, $start_date, $end_date, $notes]);
                $warranty_id = (int) $pdo->lastInsertId();
            }

            // Notification trigger — always runs, dedup is inside push_notification()
            setup_warranty_notifications(
                $pdo,
                $current_user_id,
                (string) $expense['title'],
                $end_date
            );

            json_out([
                'success' => true,
                'message' => 'تم حفظ الضمان',
                'data'    => [
                    'warranty_id' => $warranty_id,
                    'end_date'    => $end_date,
                    'start_date'  => $start_date,
                ],
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
