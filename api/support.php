<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: support.php
 * PURPOSE: Support tickets API: create, list, reply, status.
 * ========================================================================
 */
declare(strict_types=1);

require_once __DIR__ . "/../includes/security.php";
mz_send_security_headers();
mz_session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'err_unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
@require_once __DIR__ . '/../config/mail.php';
$pdo     = getDB();
$user_id = (int) $_SESSION['user_id'];

// ── Auto-migrate new schema elements (silent on duplicate) ────
try { $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    sender_type ENUM('user','admin') NOT NULL DEFAULT 'user',
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tr_ticket (ticket_id)
)"); } catch (\Throwable $e) {}

try { $pdo->exec("ALTER TABLE support_tickets ADD COLUMN user_rating TINYINT NULL COMMENT '1-5 star ticket satisfaction'"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE support_tickets ADD COLUMN rating_comment VARCHAR(500) NULL"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN site_rating TINYINT NULL"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN last_rating_prompt_date DATE NULL"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN rating_email_sent TINYINT NOT NULL DEFAULT 0"); } catch (\Throwable $e) {}

$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($data['action'] ?? ($_GET['action'] ?? ''));

switch ($action) {

    // ── Submit a new ticket ─────────────────────────────────
    case 'submit_ticket': {
        $tok = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        // CSRF guard — constant-time compare so timing analysis can't leak the token
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $tok)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'CSRF']);
            exit;
        }

        $subject = mb_substr(trim($data['subject'] ?? ''), 0, 200);
        $message = mb_substr(trim($data['message'] ?? ''), 0, 2000);

        if (empty($subject) || empty($message)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'الموضوع والرسالة مطلوبان']);
            exit;
        }

        // Limit: 5 open tickets per user at a time
        $ck = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id = ? AND status = 'open'");
        $ck->execute([$user_id]);
        if ((int) $ck->fetchColumn() >= 5) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'لديك تذاكر مفتوحة بالفعل. انتظر رد المدير أولاً.']);
            exit;
        }

        $pdo->prepare("INSERT INTO support_tickets (user_id, subject, message, status) VALUES (?, ?, ?, 'open')")
            ->execute([$user_id, $subject, $message]);

        logActivity($pdo, $user_id, 'submit_ticket', $subject);
        echo json_encode(['success' => true, 'message' => 'تم إرسال تذكرتك بنجاح. سنرد عليك قريباً.']);
        break;
    }

    // ── List the current user's tickets with full reply thread ─
    case 'list_tickets': {
        $stmt = $pdo->prepare("
            SELECT id, subject, message, status,
                   COALESCE(admin_reply, '') AS admin_reply,
                   replied_at, created_at,
                   user_rating, rating_comment
            FROM   support_tickets
            WHERE  user_id = ?
            ORDER  BY created_at DESC
            LIMIT  50
        ");
        $stmt->execute([$user_id]);
        $tickets = $stmt->fetchAll();

        foreach ($tickets as &$ticket) {
            $rs = $pdo->prepare("
                SELECT sender_type, message, created_at
                FROM   ticket_replies
                WHERE  ticket_id = ?
                ORDER  BY created_at ASC
            ");
            $rs->execute([$ticket['id']]);
            $replies = $rs->fetchAll();

            // Backward compat: synthesize from admin_reply if ticket_replies is empty
            if (empty($replies) && !empty($ticket['admin_reply'])) {
                $replies = [[
                    'sender_type' => 'admin',
                    'message'     => $ticket['admin_reply'],
                    'created_at'  => $ticket['replied_at'] ?: $ticket['created_at'],
                ]];
            }
            $ticket['replies'] = $replies;
        }
        unset($ticket);

        echo json_encode(['success' => true, 'tickets' => $tickets]);
        break;
    }

    // ── User adds a reply to a ticket ─────────────────────────
    case 'add_reply': {
        $tok = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $tok)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'CSRF']);
            exit;
        }

        $ticket_id = (int) ($data['ticket_id'] ?? 0);
        $message   = mb_substr(trim($data['message'] ?? ''), 0, 2000);

        if ($ticket_id <= 0 || empty($message)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'بيانات ناقصة']);
            exit;
        }

        // Verify ticket belongs to this user
        $chk = $pdo->prepare("SELECT id FROM support_tickets WHERE id = ? AND user_id = ?");
        $chk->execute([$ticket_id, $user_id]);
        if (!$chk->fetchColumn()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'err_unauthorized']);
            exit;
        }

        $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, sender_type, message) VALUES (?, ?, 'user', ?)")
            ->execute([$ticket_id, $user_id, $message]);

        // Reopen ticket so admin sees the new message
        $pdo->prepare("UPDATE support_tickets SET status = 'open' WHERE id = ? AND status = 'closed'")
            ->execute([$ticket_id]);

        echo json_encode([
            'success' => true,
            'reply'   => ['sender_type' => 'user', 'message' => $message, 'created_at' => date('Y-m-d H:i:s')]
        ]);
        break;
    }

    // ── Rate a closed ticket (1–5 stars) ─────────────────────
    case 'rate_ticket': {
        $tok = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $tok)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'CSRF']);
            exit;
        }

        $ticket_id = (int) ($data['ticket_id'] ?? 0);
        $rating    = (int) ($data['rating'] ?? 0);
        $comment   = mb_substr(trim($data['comment'] ?? ''), 0, 500);

        if ($rating < 1 || $rating > 5) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'التقييم يجب أن يكون بين 1 و 5']);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE support_tickets
            SET user_rating = ?, rating_comment = ?
            WHERE id = ? AND user_id = ? AND status = 'closed' AND user_rating IS NULL
        ");
        $stmt->execute([$rating, $comment ?: null, $ticket_id, $user_id]);

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'error' => 'التذكرة غير موجودة أو تم تقييمها مسبقاً']);
            exit;
        }

        echo json_encode(['success' => true]);
        break;
    }

    // ── Global site rating (1–5 stars) ───────────────────────
    case 'rate_site': {
        $tok = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $tok)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'CSRF']);
            exit;
        }

        $rating  = (int) ($data['rating'] ?? 0);
        $comment = mb_substr(trim($data['comment'] ?? ''), 0, 500);

        if ($rating < 1 || $rating > 5) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'التقييم يجب أن يكون بين 1 و 5']);
            exit;
        }

        $urow = $pdo->prepare("SELECT site_rating, rating_email_sent, name, email FROM users WHERE id = ?");
        $urow->execute([$user_id]);
        $u        = $urow->fetch();
        $is_first = ($u && $u['site_rating'] === null);

        $pdo->prepare("UPDATE users SET site_rating = ?, last_rating_prompt_date = CURDATE() WHERE id = ?")
            ->execute([$rating, $user_id]);

        // Send thank-you email on first rating
        if ($is_first && !empty($u['email']) && function_exists('sendMail')) {
            try {
                $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
                $html  = '<div style="font-family:Tahoma,Arial,sans-serif;max-width:560px;margin:auto;padding:24px;background:#f7f9fc;border-radius:12px;color:#1f2937;direction:rtl;">'
                       . '<h2 style="margin:0 0 12px;color:#1d4ed8;">⚖️ ميزان — شكراً لتقييمك!</h2>'
                       . '<p>مرحباً ' . htmlspecialchars((string)($u['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '،</p>'
                       . '<p>شكراً لتقييمك تجربتك مع ميزان بـ <strong>' . $stars . ' (' . $rating . '/5)</strong></p>'
                       . ($comment ? '<p style="color:#6b7280;font-size:13px;font-style:italic;">"' . htmlspecialchars($comment, ENT_QUOTES, 'UTF-8') . '"</p>' : '')
                       . '<p>نقدّر وقتك ورأيك — ملاحظاتك تساعدنا على تطوير ميزان باستمرار.</p>'
                       . '<p style="margin-top:18px;"><a href="' . (defined('APP_URL') ? APP_URL : '') . '/pages/support.php" style="background:#1d4ed8;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;">زيارة ميزان</a></p>'
                       . '</div>';
                @sendMail((string)$u['email'], 'ميزان — شكراً لتقييمك ⭐', $html);
                $pdo->prepare("UPDATE users SET rating_email_sent = 1 WHERE id = ?")->execute([$user_id]);
            } catch (\Throwable $e) { /* silent */ }
        }

        echo json_encode(['success' => true, 'is_first' => $is_first]);
        break;
    }

    // ── Dismiss rating prompt — show again tomorrow ───────────
    case 'dismiss_rating_prompt': {
        $tok = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $tok)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'CSRF']);
            exit;
        }
        $pdo->prepare("UPDATE users SET last_rating_prompt_date = CURDATE() WHERE id = ?")
            ->execute([$user_id]);
        echo json_encode(['success' => true]);
        break;
    }

    // ── Check whether the rating popup should be shown ────────
    case 'check_rating_prompt': {
        $stmt = $pdo->prepare("
            SELECT site_rating, last_rating_prompt_date,
                   TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS minutes_reg
            FROM   users WHERE id = ?
        ");
        $stmt->execute([$user_id]);
        $u = $stmt->fetch();

        // Must have been registered for at least 60 minutes
        if (!$u || (int)$u['minutes_reg'] < 60) {
            echo json_encode(['show' => false]);
            exit;
        }

        $today    = date('Y-m-d');
        $lastDate = $u['last_rating_prompt_date'];

        if ($u['site_rating'] !== null) {
            // Already rated: show again only after 30-day cooldown
            if ($lastDate && strtotime($lastDate) > strtotime('-30 days')) {
                echo json_encode(['show' => false]);
                exit;
            }
        } else {
            // Not yet rated: skip today if already dismissed
            if ($lastDate && $lastDate >= $today) {
                echo json_encode(['show' => false]);
                exit;
            }
        }

        echo json_encode(['show' => true]);
        break;
    }

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'إجراء غير معروف']);
}
exit;
