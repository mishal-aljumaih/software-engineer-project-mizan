<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: admin_actions.php
 * PURPOSE: Admin-only API endpoints: user reset, announcements, ticket triage.
 * OWNER: Mishal Al-jumaih - ScrumMaster & Development Member (Full Stack All Rounder)
 * ========================================================================
 */
require_once __DIR__ . "/../includes/security.php";
mz_send_security_headers();
mz_session_start();

header('Content-Type: application/json; charset=utf-8');

// Privilege gate — caller must be both authenticated AND flagged is_admin=1
// (anonymous users get 403 here, not 401, to avoid leaking that the endpoint exists)
if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'err_unauthorized']);
    exit;
}

// CSRF validation for all state-changing requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    // Also accept token embedded in JSON body (read without consuming the stream)
    $raw_body = file_get_contents('php://input');
    $body_decoded = json_decode($raw_body, true) ?? [];
    $csrf = $csrf_header !== '' ? $csrf_header : ($body_decoded['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'err_csrf_invalid']);
        exit;
    }
} else {
    $raw_body    = file_get_contents('php://input');
    $body_decoded = json_decode($raw_body, true) ?? [];
}

require_once __DIR__ . '/../config/db.php';
@require_once __DIR__ . '/../config/mail.php';
$pdo = getDB();

/**
 * Send an automated email when admin replies to a ticket.
 * Best-effort: caller wraps in try/catch; this also swallows internally.
 */
if (!function_exists('mz_notify_ticket_reply')) {
    // Helper: the notify ticket reply.
    function mz_notify_ticket_reply(PDO $pdo, int $ticket_id, string $reply, bool $closed): void {
        if (!function_exists('sendMail')) return;
        $stmt = $pdo->prepare("
            SELECT t.id, t.subject, u.email, u.name
            FROM support_tickets t
            JOIN users u ON u.id = t.user_id
            WHERE t.id = ?
            LIMIT 1
        ");
        $stmt->execute([$ticket_id]);
        $row = $stmt->fetch();
        if (!$row || empty($row['email'])) return;

        $subject_text = (string) ($row['subject'] ?? '');
        $preview      = mb_substr($reply, 0, 280);
        $status_line  = $closed ? 'تم إغلاق التذكرة بعد الرد.' : 'التذكرة لا تزال مفتوحة — يمكنك الرد عليها.';
        $html = '<div style="font-family:Tahoma,Arial,sans-serif;max-width:560px;margin:auto;padding:24px;background:#f7f9fc;border-radius:12px;color:#1f2937;direction:rtl;">'
              . '<h2 style="margin:0 0 12px;color:#1d4ed8;">ميزان — رد على تذكرة الدعم</h2>'
              . '<p>مرحباً ' . htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '،</p>'
              . '<p>تذكرة #' . (int) $row['id'] . ' — <strong>' . htmlspecialchars($subject_text, ENT_QUOTES, 'UTF-8') . '</strong></p>'
              . '<p>تم وصول رد جديد من فريق الدعم:</p>'
              . '<blockquote style="margin:8px 0;padding:12px 16px;background:#fff;border-right:3px solid #1d4ed8;border-radius:6px;white-space:pre-wrap;">'
              . htmlspecialchars($preview, ENT_QUOTES, 'UTF-8')
              . '</blockquote>'
              . '<p style="color:#6b7280;font-size:13px;">' . $status_line . '</p>'
              . '<p style="margin-top:18px;"><a href="' . (defined('APP_URL') ? APP_URL : '') . '/pages/support.php" style="background:#1d4ed8;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;">عرض التذكرة</a></p>'
              . '</div>';
        @sendMail((string) $row['email'], 'ميزان — رد على تذكرة الدعم #' . (int) $row['id'], $html);
    }
}

$data   = $body_decoded;
// GET requests pass action via query string (read-only actions like get_logs)
$action = trim($data['action'] ?? '') ?: trim($_GET['action'] ?? '');

switch ($action) {

    // ── Reply to a ticket and close it ─────────────────────
    case 'reply_ticket':
    case 'close_ticket': {
        $ticket_id = (int) ($data['ticket_id'] ?? 0);
        if ($ticket_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'معرّف التذكرة غير صالح']);
            exit;
        }

        $reply    = mb_substr(trim($data['reply'] ?? ''), 0, 2000);
        $admin_id = (int) $_SESSION['user_id'];

        if ($reply !== '') {
            $stmt = $pdo->prepare("
                UPDATE support_tickets
                SET status = 'closed', admin_reply = ?, replied_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$reply, $ticket_id]);

            // Insert into conversation thread table
            try {
                $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, sender_type, message) VALUES (?, ?, 'admin', ?)")
                    ->execute([$ticket_id, $admin_id, $reply]);
            } catch (\Throwable $e) { /* ticket_replies may not exist yet */ }
        } else {
            $stmt = $pdo->prepare("UPDATE support_tickets SET status = 'closed' WHERE id = ?");
            $stmt->execute([$ticket_id]);
        }

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'error' => 'التذكرة غير موجودة أو مغلقة بالفعل']);
            exit;
        }

        // Best-effort email notification (failure must not break API response)
        try { mz_notify_ticket_reply($pdo, $ticket_id, $reply, true); } catch (\Throwable $e) { /* silent */ }

        echo json_encode(['success' => true, 'message' => 'تم إرسال الرد وإغلاق التذكرة']);
        break;
    }

    // ── Reply to a ticket but keep it OPEN ─────────────────
    case 'admin_reply_open': {
        $ticket_id = (int) ($data['ticket_id'] ?? 0);
        if ($ticket_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'معرّف التذكرة غير صالح']);
            exit;
        }
        $reply    = mb_substr(trim($data['reply'] ?? ''), 0, 2000);
        $admin_id = (int) $_SESSION['user_id'];
        if ($reply === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'نص الرد مطلوب']);
            exit;
        }
        $stmt = $pdo->prepare("
            UPDATE support_tickets
            SET admin_reply = ?, replied_at = NOW(), status = 'open'
            WHERE id = ?
        ");
        $stmt->execute([$reply, $ticket_id]);

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'error' => 'التذكرة غير موجودة']);
            exit;
        }

        // Insert into conversation thread table
        try {
            $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, sender_type, message) VALUES (?, ?, 'admin', ?)")
                ->execute([$ticket_id, $admin_id, $reply]);
        } catch (\Throwable $e) { /* ticket_replies may not exist yet */ }

        try { mz_notify_ticket_reply($pdo, $ticket_id, $reply, false); } catch (\Throwable $e) { /* silent */ }

        echo json_encode(['success' => true, 'message' => 'تم إرسال الرد مع إبقاء التذكرة مفتوحة']);
        break;
    }

    // ── Create / update a global announcement ──────────────
    case 'broadcast': {
        $msg_ar = mb_substr(trim($data['message_ar'] ?? ''), 0, 500);
        $msg_en = mb_substr(trim($data['message_en'] ?? ''), 0, 500);
        if (empty($msg_ar)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'نص الإعلان مطلوب']);
            exit;
        }
        $expires_raw = trim($data['expires_at'] ?? '');
        $expires = null;
        if ($expires_raw && strtotime($expires_raw)) {
            $expires = date('Y-m-d H:i:s', strtotime($expires_raw));
        }

        $bg_color   = mb_substr(trim($data['bg_color']   ?? ''), 0, 10);
        $text_color = mb_substr(trim($data['text_color'] ?? ''), 0, 10);
        $font_size  = max(11, min(20, (int)($data['font_size'] ?? 14)));

        // Deactivate previous announcements then create a new one
        $pdo->exec("UPDATE announcements SET is_active = 0");
        try {
            $pdo->prepare("
                INSERT INTO announcements (message_ar, message_en, is_active, expires_at, bg_color, text_color, font_size)
                VALUES (?, ?, 1, ?, ?, ?, ?)
            ")->execute([$msg_ar, $msg_en ?: $msg_ar, $expires, $bg_color, $text_color, $font_size]);
        } catch (\Throwable $e) {
            // Fallback: style columns may not exist yet
            $pdo->prepare("
                INSERT INTO announcements (message_ar, message_en, is_active, expires_at)
                VALUES (?, ?, 1, ?)
            ")->execute([$msg_ar, $msg_en ?: $msg_ar, $expires]);
        }

        echo json_encode(['success' => true, 'message' => 'تم نشر الإعلان']);
        break;
    }

    // ── Deactivate all announcements ────────────────────────
    case 'clear_broadcast': {
        $pdo->exec("UPDATE announcements SET is_active = 0");
        echo json_encode(['success' => true]);
        break;
    }

    // ── Promote a user to admin (super_admin only) ────────────
    case 'promote_user': {
        // Verify caller is super_admin by querying DB (session only stores is_admin)
        $me_stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $me_stmt->execute([(int) $_SESSION['user_id']]);
        $me_row = $me_stmt->fetch();
        if (!$me_row || ($me_row['role'] ?? '') !== 'super_admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'صلاحية المدير الأعلى مطلوبة']);
            exit;
        }

        $target_id = (int) ($data['user_id'] ?? 0);
        if ($target_id <= 0 || $target_id === (int) $_SESSION['user_id']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'معرّف المستخدم غير صالح']);
            exit;
        }

        $chk = $pdo->prepare("SELECT role, is_admin FROM users WHERE id = ? LIMIT 1");
        $chk->execute([$target_id]);
        $target = $chk->fetch();
        if (!$target) {
            echo json_encode(['success' => false, 'error' => 'المستخدم غير موجود']);
            exit;
        }
        if (($target['role'] ?? '') === 'super_admin') {
            echo json_encode(['success' => false, 'error' => 'لا يمكن تعديل صلاحيات المدير الأعلى']);
            exit;
        }

        $pdo->prepare("UPDATE users SET is_admin = 1, role = 'admin' WHERE id = ?")
            ->execute([$target_id]);
        session_regenerate_id(true);
        echo json_encode(['success' => true, 'message' => 'تمت ترقية المستخدم إلى مدير']);
        break;
    }

    // ── Demote an admin back to user (super_admin only) ───────
    case 'demote_user': {
        $me_stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $me_stmt->execute([(int) $_SESSION['user_id']]);
        $me_row = $me_stmt->fetch();
        if (!$me_row || ($me_row['role'] ?? '') !== 'super_admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'صلاحية المدير الأعلى مطلوبة']);
            exit;
        }

        $target_id = (int) ($data['user_id'] ?? 0);
        if ($target_id <= 0 || $target_id === (int) $_SESSION['user_id']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'معرّف المستخدم غير صالح']);
            exit;
        }

        $chk = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $chk->execute([$target_id]);
        $target = $chk->fetch();
        if (!$target) {
            echo json_encode(['success' => false, 'error' => 'المستخدم غير موجود']);
            exit;
        }
        if (($target['role'] ?? '') === 'super_admin') {
            echo json_encode(['success' => false, 'error' => 'لا يمكن إزالة صلاحيات المدير الأعلى']);
            exit;
        }

        $pdo->prepare("UPDATE users SET is_admin = 0, role = 'user' WHERE id = ?")
            ->execute([$target_id]);
        session_regenerate_id(true);
        echo json_encode(['success' => true, 'message' => 'تم إزالة صلاحية المدير عن المستخدم']);
        break;
    }

    // ── Fetch activity logs with optional filters (GET) ────────
    case 'get_logs': {
        $userId = (int) ($_GET['user_id'] ?? 0);
        $date   = trim((string) ($_GET['date'] ?? ''));
        $limit  = max(1, min(100, (int) ($_GET['limit'] ?? 50)));
        $offset = max(0, (int) ($_GET['offset'] ?? 0));

        $where = [];
        $params = [];
        if ($userId > 0) { $where[] = 'a.user_id = ?'; $params[] = $userId; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $where[] = 'DATE(a.created_at) = ?'; $params[] = $date; }
        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs a $whereSQL");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT a.action_type, a.details, a.ip_address, a.created_at, u.name, u.email
            FROM activity_logs a JOIN users u ON a.user_id = u.id
            $whereSQL ORDER BY a.created_at DESC LIMIT ? OFFSET ?
        ");
        $stmt->execute(array_merge($params, [$limit, $offset]));
        echo json_encode(['success' => true, 'logs' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total]);
        exit;
    }

    // ── Reset user password (T5) ──────────────────────────────
    case 'reset_user_password': {
        if (!$_SESSION['is_admin']) { echo json_encode(['success'=>false,'error'=>'err_unauthorized']); exit; }
        $uid = (int)($body_decoded['user_id'] ?? 0);
        if ($uid <= 0) { echo json_encode(['success'=>false,'error'=>'معرّف مستخدم غير صالح']); exit; }

        $row = $pdo->prepare("SELECT name, email FROM users WHERE id=? LIMIT 1");
        $row->execute([$uid]);
        $user = $row->fetch();
        if (!$user) { echo json_encode(['success'=>false,'error'=>'المستخدم غير موجود']); exit; }

        // Generate secure 8-char alphanumeric password
        $chars    = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $newPass  = '';
        for ($i = 0; $i < 8; $i++) $newPass .= $chars[random_int(0, strlen($chars)-1)];
        $hash = password_hash($newPass, PASSWORD_BCRYPT);

        // Auto-migrate flag column (no-op if it already exists)
        try { $pdo->exec("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0"); }
        catch (\Throwable $e) { /* column exists */ }

        try {
            $pdo->prepare("UPDATE users SET password=?, must_change_password=1 WHERE id=?")
                ->execute([$hash, $uid]);
        } catch (\Throwable $e) {
            // Fallback if migration could not run (e.g. permissions)
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $uid]);
        }

        // Send email using dedicated template if available
        if (function_exists('sendMail')) {
            $emailBody = '';
            if (function_exists('mailAdminResetPassword')) {
                $emailBody = mailAdminResetPassword($user['name'], $newPass);
            } elseif (function_exists('mailWrap')) {
                $emailBody = mailWrap("
                    <p>مرحباً {$user['name']}،</p>
                    <p>تم إعادة تعيين كلمة مرورك من قبل مدير النظام.</p>
                    <p>كلمة مرورك الجديدة: <strong style='font-size:18px;letter-spacing:2px'>{$newPass}</strong></p>
                    <p>يُرجى تغييرها فور تسجيل الدخول.</p>
                ", 'إعادة تعيين كلمة المرور');
            }
            if ($emailBody !== '') {
                sendMail($user['email'], 'إعادة تعيين كلمة المرور — ميزان', $emailBody);
            }
        }

        if (function_exists('logActivity')) {
            logActivity($pdo, (int)$_SESSION['user_id'], 'admin_reset_pw', $user['email']);
        }
        echo json_encode(['success'=>true, 'message'=>'تم إرسال كلمة المرور الجديدة']);
        exit;
    }

    // ── Send custom email to a user (T5) ──────────────────────
    case 'send_custom_email': {
        if (!$_SESSION['is_admin']) { echo json_encode(['success'=>false,'error'=>'err_unauthorized']); exit; }
        $uid     = (int)($body_decoded['user_id'] ?? 0);
        $subject = trim((string)($body_decoded['subject'] ?? ''));
        $body_txt = trim((string)($body_decoded['body'] ?? ''));
        if ($uid <= 0 || $subject === '' || $body_txt === '') {
            echo json_encode(['success'=>false,'error'=>'بيانات ناقصة']); exit;
        }

        $row = $pdo->prepare("SELECT name, email FROM users WHERE id=? LIMIT 1");
        $row->execute([$uid]);
        $user = $row->fetch();
        if (!$user) { echo json_encode(['success'=>false,'error'=>'المستخدم غير موجود']); exit; }

        if (!function_exists('sendMail')) {
            echo json_encode(['success'=>false,'error'=>'نظام البريد غير متاح']); exit;
        }

        $safeBody = nl2br(htmlspecialchars($body_txt, ENT_QUOTES, 'UTF-8'));
        if (function_exists('mailCustomAdmin')) {
            $htmlBody = mailCustomAdmin($user['name'], $subject, $safeBody);
        } elseif (function_exists('mailWrap')) {
            $htmlBody = mailWrap("<p>مرحباً {$user['name']}،</p><p>{$safeBody}</p>", $subject);
        } else {
            echo json_encode(['success'=>false,'error'=>'نظام البريد غير متاح']); exit;
        }
        $sent = sendMail($user['email'], $subject, $htmlBody);
        if (function_exists('logActivity')) {
            logActivity($pdo, (int)$_SESSION['user_id'], 'admin_custom_email', $user['email']);
        }
        echo json_encode(['success' => $sent, 'error' => $sent ? '' : 'فشل الإرسال']);
        exit;
    }

    // ── Send test email (T8) ───────────────────────────────────
    case 'send_test_email': {
        if (!$_SESSION['is_admin']) { echo json_encode(['success'=>false,'error'=>'err_unauthorized']); exit; }
        $tpl    = trim((string)($body_decoded['template'] ?? ''));
        $lang   = trim((string)($body_decoded['lang'] ?? 'ar')) === 'en' ? 'en' : 'ar';
        $target = trim((string)($body_decoded['target'] ?? ''));
        if (!filter_var($target, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success'=>false,'error'=>'بريد إلكتروني غير صالح']); exit;
        }
        if (!function_exists('sendMail') || !function_exists('mailWrap')) {
            echo json_encode(['success'=>false,'error'=>'نظام البريد غير متاح']); exit;
        }

        $dummy_name  = $lang === 'ar' ? 'مستخدم اختباري' : 'Test User';
        $dummy_pass  = 'Test@1234';
        $dummy_proj  = $lang === 'ar' ? 'مشروع اختباري' : 'Test Project';
        $dummy_date  = date('Y-m-d');
        $dummy_pct   = 92.0;
        $appUrl      = defined('APP_URL') ? APP_URL : 'https://mizan-iau.site';

        $htmlBody = '';
        $subject  = '';

        switch ($tpl) {
            case 'welcome':
                $subject  = $lang === 'ar' ? 'مرحباً في ميزان' : 'Welcome to Mizan';
                $htmlBody = function_exists('mailWelcome')
                    ? mailWelcome($dummy_name, $appUrl)
                    : mailWrap("<p>مرحباً {$dummy_name}،</p><p>تم تسجيلك في ميزان!</p>", $subject);
                break;

            case 'reset_password':
                $subject  = $lang === 'ar' ? 'إعادة تعيين كلمة المرور — ميزان' : 'Password Reset — Mizan';
                $htmlBody = function_exists('mailAdminResetPassword')
                    ? mailAdminResetPassword($dummy_name, $dummy_pass)
                    : mailWrap("<p>كلمة مرورك الجديدة: <strong>{$dummy_pass}</strong></p>", $subject);
                break;

            case 'warranty_alert':
                $subject  = $lang === 'ar' ? 'تنبيه: ضمان على وشك الانتهاء' : 'Warranty Expiring Soon';
                $htmlBody = function_exists('mailWarrantyAlert')
                    ? mailWarrantyAlert($dummy_name, $dummy_proj, $dummy_date, 5, $appUrl)
                    : mailWrap("<p>ضمان <strong>{$dummy_proj}</strong> ينتهي بتاريخ {$dummy_date}.</p>", $subject);
                break;

            case 'budget_alert':
                $subject  = $lang === 'ar' ? 'تنبيه: اقتربت من حد الميزانية' : 'Budget Alert';
                $htmlBody = function_exists('mailBudgetAlert')
                    ? mailBudgetAlert($dummy_name, $dummy_proj, 10000, 9200, $appUrl)
                    : mailWrap("<p>مشروع <strong>{$dummy_proj}</strong> استهلك {$dummy_pct}% من الميزانية.</p>", $subject);
                break;

            default:
                echo json_encode(['success'=>false,'error'=>'قالب غير موجود']); exit;
        }

        $sent = sendMail($target, $subject, $htmlBody);
        echo json_encode(['success' => $sent, 'error' => $sent ? '' : 'فشل الإرسال']);
        exit;
    }

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'إجراء غير معروف']);
}
exit;
