<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: notifications.php
 * PURPOSE: In-app notifications: list, mark read, badge counts.
 * ========================================================================
 */
require_once __DIR__ . "/../includes/security.php";
mz_send_security_headers();
mz_session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'err_unauthorized']);
    exit;
}

// CSRF validation for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'err_csrf_invalid']);
        exit;
    }
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../includes/notifications_helper.php';

$current_user_id = (int) $_SESSION['user_id'];
$pdo             = getDB();

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// ════════════════════════════════════════════════════════════
// ROUTES
// ════════════════════════════════════════════════════════════
switch ($action) {

    // ── جلب التنبيهات ───────────────────────────────────────
    case 'list':
        scan_user_warranties($pdo, $current_user_id);

        // Hard-cap the LIMIT to 50 so a malicious client can't exfiltrate the whole table by passing limit=999999.
        // bindValue(..., PARAM_INT) is required because we disabled emulated prepares — MySQL needs a real int here.
        $limit = min((int)($_GET['limit'] ?? 15), 50);
        $stmt  = $pdo->prepare("
            SELECT id, message, type, is_read,
                   created_at,
                   TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS age_min
            FROM notifications
            WHERE user_id = :uid
            ORDER BY is_read ASC, created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':uid', $current_user_id, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $notifs = $stmt->fetchAll();

        // تحويل الوقت لنصوص عربية
        foreach ($notifs as &$n) {
            $m = (int)$n['age_min'];
            $n['ago'] = $m < 1   ? 'الآن'
                      : ($m < 60  ? "منذ {$m} دقيقة"
                      : ($m < 1440? "منذ " . intdiv($m, 60) . " ساعة"
                      :              "منذ " . intdiv($m, 1440) . " يوم"));
        }
        unset($n);

        $unread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $unread->execute([$current_user_id]);

        echo json_encode([
            'success'      => true,
            'notifications' => $notifs,
            'unread_count'  => (int)$unread->fetchColumn(),
        ]);
        break;

    // ── تحديد تنبيه كمقروء ──────────────────────────────────
    case 'read':
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")
                ->execute([$id, $current_user_id]);
        }
        echo json_encode(['success' => true]);
        break;

    // ── تحديد الكل كمقروء ───────────────────────────────────
    case 'markRead':
        $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")
                ->execute([$id, $current_user_id]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'markAllRead':
    case 'read_all':
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")
            ->execute([$current_user_id]);
        echo json_encode(['success' => true]);
        break;

    // ── حذف تنبيه ───────────────────────────────────────────
    case 'delete':
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?")
                ->execute([$id, $current_user_id]);
        }
        echo json_encode(['success' => true]);
        break;

    // ── حذف كل المقروءة ─────────────────────────────────────
    case 'clear_read':
        $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1")
            ->execute([$current_user_id]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'action غير معروف']);
}
