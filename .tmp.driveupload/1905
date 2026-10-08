<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: notifications_helper.php
 * PURPOSE: Server-side helper to enqueue notifications for users.
 * OWNER: Alwaleed Alzahrani - Development & Database Admin (Lead Programmer)
 * ========================================================================
 */
// ============================================================
// Mizan ميزان — includes/notifications_helper.php
// ------------------------------------------------------------
// Shared notification primitives used by:
//   - api/notifications.php  (scheduled warranty scan on list)
//   - api/expenses.php       (immediate trigger on add_warranty)
//
// All writers go through push_notification() which enforces a
// 24-hour "same message + type" dedup window.
// ============================================================

declare(strict_types=1);

/**
 * Insert a notification row unless an identical one (same message + type)
 * was already created for this user within the last 24 hours.
 */
function push_notification(PDO $pdo, int $user_id, string $message, string $type): bool {
    $s = $pdo->prepare('
        SELECT 1 FROM notifications
        WHERE user_id = ? AND message = ? AND type = ?
          AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
        LIMIT 1
    ');
    $s->execute([$user_id, $message, $type]);
    if ($s->fetchColumn()) {
        return false;
    }
    $pdo->prepare('
        INSERT INTO notifications (user_id, message, type)
        VALUES (?, ?, ?)
    ')->execute([$user_id, $message, $type]);
    return true;
}

/**
 * Canonical wording for a warranty that is within 30 days of expiry.
 * MUST stay stable so the dedup window applies across callers.
 */
function warranty_warning_message(string $item_name, int $days_left, string $pretty_date): string {
    return "ضمان «{$item_name}» ينتهي خلال {$days_left} يوم ({$pretty_date})";
}

/**
 * Fire any notifications that apply to a just-created/updated warranty.
 *
 * - Always emits a confirmation (warranty_added)
 * - If within 30 days → warranty_warning / warranty_urgent
 * - If already expired → warranty_expired
 */
function setup_warranty_notifications(
    PDO $pdo,
    int $user_id,
    string $item_name,
    string $end_date,
    bool $send_email = false
): void {
    try {
        $end = new DateTimeImmutable($end_date);
    } catch (Throwable $e) {
        return;
    }
    $today  = new DateTimeImmutable('today');
    $days   = (int) $today->diff($end)->format('%r%a');
    $pretty = $end->format('Y/m/d');

    $confirm_msg = "تم تسجيل ضمان «{$item_name}» حتى {$pretty}";
    push_notification($pdo, $user_id, $confirm_msg, 'warranty_added');

    if ($days < 0) {
        $expired_msg = "ضمان «{$item_name}» منتهي منذ " . abs($days) . " يوم";
        push_notification($pdo, $user_id, $expired_msg, 'warranty_expired');
        return;
    }
    if ($days > 30) return;

    $type      = $days <= 7 ? 'warranty_urgent' : 'warranty_warning';
    $alert_msg = warranty_warning_message($item_name, $days, $pretty);
    $inserted  = push_notification($pdo, $user_id, $alert_msg, $type);

    if (!$inserted || !$send_email) return;
    if (!in_array($days, [30, 7, 1], true)) return;
    if (!function_exists('sendMail') || !function_exists('mailWarrantyAlert')) return;

    $u = $pdo->prepare('SELECT email, name FROM users WHERE id = ?');
    $u->execute([$user_id]);
    $user = $u->fetch();
    if (!$user || empty($user['email'])) return;

    $url  = defined('APP_URL') ? (APP_URL . '/pages/warranties.php') : '/pages/warranties.php';
    $html = mailWarrantyAlert($user['name'] ?? 'المستخدم', $item_name, $pretty, $days, $url);
    @sendMail($user['email'], "⚠️ ضمان ينتهي قريباً — {$item_name}", $html);
}

/**
 * Scan every warranty owned by this user that expires within 30 days and
 * create/refresh the corresponding notifications. Called on notifications
 * list() to keep the bell current without requiring a cron job.
 */
function scan_user_warranties(PDO $pdo, int $user_id): void {
    $s = $pdo->prepare('
        SELECT e.title AS item_name, w.end_date
        FROM warranties w
        JOIN expenses e ON e.id = w.expense_id
        JOIN projects p ON p.id = e.project_id
        WHERE p.user_id  = ?
          AND w.end_date >= CURDATE()
          AND DATEDIFF(w.end_date, CURDATE()) <= 30
    ');
    $s->execute([$user_id]);
    foreach ($s->fetchAll() as $row) {
        setup_warranty_notifications(
            $pdo,
            $user_id,
            (string) $row['item_name'],
            (string) $row['end_date'],
            true
        );
    }
}