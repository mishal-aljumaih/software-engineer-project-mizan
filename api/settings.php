<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: settings.php
 * PURPOSE: User settings endpoints: profile, password, preferences.
 * ========================================================================
 */
require_once __DIR__ . "/../includes/security.php";
mz_send_security_headers();
mz_session_start();
header('Content-Type: application/json; charset=utf-8');

// Auth gate — unauthenticated callers get a JSON 401 instead of an HTML redirect
// (this endpoint is consumed by fetch()/AJAX, which would choke on an HTML response)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'err_unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

// CSRF guard for every state-changing verb — token is sent either via X-CSRF-Token header (fetch) or JSON body
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($csrf) && ($raw = json_decode(file_get_contents('php://input'), true))) {
        $csrf = $raw['csrf_token'] ?? '';
    }
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'err_csrf_invalid']);
        exit;
    }
}

$user_id = (int) $_SESSION['user_id'];
$pdo     = getDB();

// Defines the json out routine.
function json_out(bool $success, string $msg = '', $data = null, int $code = 200): void {
    http_response_code($code);
    $r = ['success' => $success];
    if ($success) {
        if ($msg)  $r['message'] = $msg;
        if ($data !== null) $r['data'] = $data;
    } else {
        $r['error'] = $msg ?: 'err_generic_server';
    }
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}

// Accept JSON body
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ============================================================
    // 1. UPDATE PROFILE
    // ============================================================
    case 'update_profile':
        $name  = trim($input['name']  ?? '');
        $email = trim($input['email'] ?? '');

        if ($name === '' || $email === '') {
            json_out(false, 'err_name_email_required', null, 400);
        }
        if (!mz_validate_email($email)) {
            json_out(false, 'err_invalid_email_api', null, 400);
        }
        if (mb_strlen($name) > 100) {
            json_out(false, 'err_name_too_long', null, 400);
        }

        // Check email uniqueness (excluding current user)
        $s = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $s->execute([$email, $user_id]);
        if ($s->fetch()) {
            json_out(false, 'err_email_taken', null, 409);
        }

        $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?")
            ->execute([$name, $email, $user_id]);

        $_SESSION['user_name'] = $name;
        json_out(true, 'profile_updated');
        break;

    // ============================================================
    // 2. UPDATE PASSWORD
    // ============================================================
    case 'update_password':
        $current = $input['current_password'] ?? '';
        $new     = $input['new_password']      ?? '';

        if ($current === '' || $new === '') {
            json_out(false, 'err_all_fields_api', null, 400);
        }
        if (mb_strlen($new) < 6) {
            json_out(false, 'pass_min_6', null, 400);
        }

        $s = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $s->execute([$user_id]);
        $hash = $s->fetchColumn();

        if (!password_verify($current, $hash)) {
            json_out(false, 'err_wrong_current_pass', null, 403);
        }

        try {
            $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?")
                ->execute([password_hash($new, PASSWORD_DEFAULT), $user_id]);
        } catch (\Throwable $e) {
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                ->execute([password_hash($new, PASSWORD_DEFAULT), $user_id]);
        }
        $_SESSION['must_change_password'] = 0;

        json_out(true, 'password_changed');
        break;

    // ============================================================
    // 3. UPDATE PREFERENCES
    // ============================================================
    case 'update_preferences':
        $allowed_currencies = ['SAR','USD','EUR','GBP','AED','KWD','BHD','QAR','OMR','EGP'];
        $allowed_themes     = ['light','dark'];
        $allowed_langs      = ['ar','en'];

        $currency       = in_array($input['currency'] ?? '', $allowed_currencies) ? $input['currency'] : 'SAR';
        $theme          = in_array($input['theme']    ?? '', $allowed_themes)     ? $input['theme']    : 'dark';
        $language        = in_array($input['language']  ?? '', $allowed_langs)      ? $input['language']  : 'ar';
        $notif_email    = !empty($input['notif_email'])    ? 1 : 0;
        $notif_warranty = !empty($input['notif_warranty']) ? 1 : 0;
        $notif_budget   = !empty($input['notif_budget'])   ? 1 : 0;

        $pdo->prepare("UPDATE users SET currency = ?, theme = ?, language = ?, notif_email = ?, notif_warranty = ?, notif_budget = ? WHERE id = ?")
            ->execute([$currency, $theme, $language, $notif_email, $notif_warranty, $notif_budget, $user_id]);

        $_SESSION['currency'] = strtoupper($currency);
        json_out(true, 'preferences_saved', ['currency' => $_SESSION['currency']]);
        break;

    // ============================================================
    // 4. EXPORT DATA
    // ============================================================
    case 'export_data':
        $data = ['exported_at' => date('Y-m-d H:i:s')];

        // User info (no password)
        $s = $pdo->prepare("SELECT id, name, email, currency, theme, language, created_at FROM users WHERE id = ?");
        $s->execute([$user_id]);
        $data['user'] = $s->fetch();

        // Projects
        $s = $pdo->prepare("SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC");
        $s->execute([$user_id]);
        $data['projects'] = $s->fetchAll();

        // Expenses (via projects)
        $s = $pdo->prepare("
            SELECT e.* FROM expenses e
            JOIN projects p ON e.project_id = p.id
            WHERE p.user_id = ?
            ORDER BY e.created_at DESC
        ");
        $s->execute([$user_id]);
        $data['expenses'] = $s->fetchAll();

        // Warranties
        $s = $pdo->prepare("
            SELECT w.* FROM warranties w
            JOIN expenses e ON w.expense_id = e.id
            JOIN projects p ON e.project_id = p.id
            WHERE p.user_id = ?
        ");
        $s->execute([$user_id]);
        $data['warranties'] = $s->fetchAll();

        // Invoices
        $s = $pdo->prepare("
            SELECT i.* FROM invoices i
            JOIN expenses e ON i.expense_id = e.id
            JOIN projects p ON e.project_id = p.id
            WHERE p.user_id = ?
        ");
        $s->execute([$user_id]);
        $data['invoices'] = $s->fetchAll();

        // Files
        $s = $pdo->prepare("SELECT * FROM files WHERE project_id IN (SELECT id FROM projects WHERE user_id = ?)");
        $s->execute([$user_id]);
        $data['files'] = $s->fetchAll();

        // Notifications
        $s = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
        $s->execute([$user_id]);
        $data['notifications'] = $s->fetchAll();

        json_out(true, '', $data);
        break;

    // ============================================================
    // 5. DELETE ACCOUNT
    // ============================================================
    case 'delete_account':
        // Collect upload paths before cascade delete
        $paths = [];

        $s = $pdo->prepare("
            SELECT i.file_path FROM invoices i
            JOIN expenses e ON i.expense_id = e.id
            JOIN projects p ON e.project_id = p.id
            WHERE p.user_id = ?
        ");
        $s->execute([$user_id]);
        while ($r = $s->fetch()) {
            if ($r['file_path']) $paths[] = $r['file_path'];
        }

        $s = $pdo->prepare("
            SELECT w.file_path FROM warranties w
            JOIN expenses e ON w.expense_id = e.id
            JOIN projects p ON e.project_id = p.id
            WHERE p.user_id = ?
        ");
        $s->execute([$user_id]);
        while ($r = $s->fetch()) {
            if ($r['file_path']) $paths[] = $r['file_path'];
        }

        $s = $pdo->prepare("SELECT file_path FROM files WHERE project_id IN (SELECT id FROM projects WHERE user_id = ?)");
        $s->execute([$user_id]);
        while ($r = $s->fetch()) {
            if ($r['file_path']) $paths[] = $r['file_path'];
        }

        // Delete user (CASCADE handles projects→expenses→warranties/invoices, files, notifications)
        $pdo->prepare("DELETE FROM password_resets WHERE email = (SELECT email FROM users WHERE id = ?)")
            ->execute([$user_id]);
        $pdo->prepare("DELETE FROM users WHERE id = ?")
            ->execute([$user_id]);

        // Sandboxed file cleanup
        $base = realpath(__DIR__ . '/../uploads');
        foreach ($paths as $rel) {
            $full = realpath(__DIR__ . '/../' . $rel);
            if ($full && $base && strpos($full, $base) === 0 && is_file($full)) {
                @unlink($full);
            }
        }

        // Destroy session
        session_destroy();
        json_out(true, 'تم حذف الحساب بنجاح');
        break;

    default:
        json_out(false, 'إجراء غير معروف', null, 400);
}
