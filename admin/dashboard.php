<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: dashboard.php
 * PURPOSE: Admin control panel: analytics, tickets, announcements, feedback.
 * OWNER: Mishal Al-jumaih - ScrumMaster & Development Member (Full Stack All Rounder)
 * ========================================================================
 */
require_once __DIR__ . '/../includes/security.php';
mz_send_security_headers();
mz_session_start();

// Privilege gate — admin panel is reachable ONLY to is_admin=1 users; everyone else is bounced to the admin login form
if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /admin_login.php');
    exit;
}

// CSRF token — required for all admin AJAX actions
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

require_once __DIR__ . '/../config/db.php';
$pdo = getDB();

// ── Auto-migrations ────────────────────────────────────────────
try { $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user'"); } catch (\Throwable $e) {}
try {
    // Self-healing role bootstrap: guarantees the system always has exactly one super_admin
    // (lowest-id existing admin, falling back to the lowest-id user on a brand-new install)
    $has_sa = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin'")->fetchColumn();
    if ($has_sa === 0) {
        $sa_id = $pdo->query("SELECT id FROM users WHERE is_admin = 1 ORDER BY id ASC LIMIT 1")->fetchColumn()
               ?: $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($sa_id) {
            $pdo->prepare("UPDATE users SET role = 'super_admin', is_admin = 1 WHERE id = ?")->execute([$sa_id]);
        }
    }
    $pdo->exec("UPDATE users SET role = 'admin' WHERE is_admin = 1 AND role = 'user'");
} catch (\Throwable $e) {}

// Determine if current admin is super_admin
$is_super_admin = false;
try {
    $me_stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $me_stmt->execute([(int) $_SESSION['user_id']]);
    $me_row = $me_stmt->fetch();
    $is_super_admin = $me_row && ($me_row['role'] ?? '') === 'super_admin';
} catch (\Throwable $e) {}

$admin_name = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');

// ── System Stats ─────────────────────────────────────────────
$total_users    = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_projects = (int) $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
$total_expenses = (int) $pdo->query("SELECT COUNT(*) FROM expenses")->fetchColumn();
$total_files    = (int) $pdo->query("SELECT COUNT(*) FROM files")->fetchColumn();

// Support tickets
$stmt = $pdo->query("
    SELECT t.id, t.subject, t.message, t.status, t.created_at,
           COALESCE(t.admin_reply,'') AS admin_reply,
           u.name AS user_name, u.email AS user_email
    FROM support_tickets t
    JOIN users u ON t.user_id = u.id
    ORDER BY
        CASE t.status WHEN 'open' THEN 0 ELSE 1 END,
        t.created_at DESC
    LIMIT 200
");
$tickets = $stmt->fetchAll();
$open_tickets = array_filter($tickets, fn($t) => $t['status'] === 'open');

// Active announcement — replaced below with style-column version

// Recent users (include role for promote button logic)
try {
    $recent_users = $pdo->query("
        SELECT id, name, email, created_at, is_admin,
               COALESCE(role, 'user') AS role
        FROM users
        ORDER BY created_at DESC
        LIMIT 20
    ")->fetchAll();
} catch (\Throwable $e) {
    // Fallback: role column not yet migrated
    $recent_users = $pdo->query("
        SELECT id, name, email, created_at, is_admin,
               IF(is_admin = 1, 'admin', 'user') AS role
        FROM users
        ORDER BY created_at DESC
        LIMIT 20
    ")->fetchAll();
}

// All users for log filter dropdown
$all_users = $pdo->query("SELECT id, name, email FROM users ORDER BY name ASC")->fetchAll();

// Activity logs replaced by pagination below

// Average ticket satisfaction score
$avg_satisfaction = null;
$satisfaction_count = 0;
try {
    $sat = $pdo->query("SELECT ROUND(AVG(user_rating), 1) AS avg_r, COUNT(user_rating) AS cnt FROM support_tickets WHERE user_rating IS NOT NULL")->fetch();
    if ($sat && (int)$sat['cnt'] > 0) {
        $avg_satisfaction = (float) $sat['avg_r'];
        $satisfaction_count = (int) $sat['cnt'];
    }
} catch (\Exception $e) { /* user_rating column not yet applied */ }

// ── Storage Metrics (T3) ──────────────────────────────────
$uploads_size_bytes = 0;
try {
    $uploads_dir = __DIR__ . '/../uploads';
    if (is_dir($uploads_dir)) {
        $rit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads_dir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($rit as $f) { if ($f->isFile()) $uploads_size_bytes += $f->getSize(); }
    }
} catch (\Throwable $e) {}

$db_size_bytes = 0;
try {
    $r = $pdo->query("SELECT SUM(data_length + index_length) AS sz FROM information_schema.tables WHERE table_schema = DATABASE()")->fetch();
    $db_size_bytes = (float)($r['sz'] ?? 0);
} catch (\Throwable $e) {}

// Defines the adm fmt bytes routine.
function adm_fmt_bytes(float $b): string {
    if ($b >= 1073741824) return round($b / 1073741824, 2) . ' GB';
    if ($b >= 1048576)    return round($b / 1048576, 2) . ' MB';
    if ($b >= 1024)       return round($b / 1024, 2) . ' KB';
    return (int)$b . ' B';
}
$uploads_fmt = adm_fmt_bytes($uploads_size_bytes);
$db_size_fmt  = adm_fmt_bytes($db_size_bytes);

// ── Analytics Data (T4) ───────────────────────────────────
$rated_count = 0; $unrated_count = 0;
$star_dist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
try {
    $rs = $pdo->query("SELECT COUNT(*) tot, SUM(user_rating IS NOT NULL) rated FROM support_tickets")->fetch();
    $rated_count   = (int)($rs['rated'] ?? 0);
    $unrated_count = (int)($rs['tot'] ?? 0) - $rated_count;
    foreach ($pdo->query("SELECT user_rating, COUNT(*) c FROM support_tickets WHERE user_rating IS NOT NULL GROUP BY user_rating")->fetchAll() as $r2) {
        if (isset($star_dist[(int)$r2['user_rating']])) $star_dist[(int)$r2['user_rating']] = (int)$r2['c'];
    }
} catch (\Throwable $e) {}

$proj_personal = $total_projects; $proj_commercial = 0;
try {
    $proj_personal = 0;
    foreach ($pdo->query("SELECT COALESCE(nature,'personal') n, COUNT(*) c FROM projects GROUP BY n")->fetchAll() as $r3) {
        if ($r3['n'] === 'commercial') $proj_commercial = (int)$r3['c'];
        else $proj_personal += (int)$r3['c'];
    }
} catch (\Throwable $e) {}

$top_users = [];
try {
    $top_users = $pdo->query("
        SELECT u.name, COUNT(DISTINCT p.id) pc, COUNT(DISTINCT e.id) ec,
               COALESCE(SUM(e.amount),0) ts
        FROM users u
        LEFT JOIN projects p ON p.user_id = u.id
        LEFT JOIN expenses e ON e.project_id = p.id
        GROUP BY u.id, u.name ORDER BY ec DESC LIMIT 5
    ")->fetchAll();
} catch (\Throwable $e) {}

$feedback_list = [];
try {
    $feedback_list = $pdo->query("
        SELECT t.user_rating, t.satisfaction_comment, t.created_at, u.name un
        FROM support_tickets t JOIN users u ON t.user_id = u.id
        WHERE t.satisfaction_comment IS NOT NULL AND t.satisfaction_comment <> ''
        ORDER BY t.created_at DESC LIMIT 20
    ")->fetchAll();
} catch (\Throwable $e) {}

// ── Announcement style columns auto-migrate (T6) ──────────
try { $pdo->exec("ALTER TABLE announcements ADD COLUMN bg_color VARCHAR(10) DEFAULT ''"); }   catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE announcements ADD COLUMN text_color VARCHAR(10) DEFAULT ''"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE announcements ADD COLUMN font_size TINYINT UNSIGNED DEFAULT 14"); } catch (\Throwable $e) {}

// Reload announcement with style fields
$active_ann = null;
try {
    $active_ann = $pdo->query("
        SELECT id, message_ar, message_en,
               COALESCE(bg_color,'')  bg_color,
               COALESCE(text_color,'') text_color,
               COALESCE(font_size,14)  font_size
        FROM announcements
        WHERE is_active=1 AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY created_at DESC LIMIT 1
    ")->fetch();
} catch (\Throwable $e) {}

// ── Financial Velocity (T4) ───────────────────────────────
$total_budgets = 0.0;
try {
    $total_budgets = (float) $pdo->query("SELECT COALESCE(SUM(budget),0) FROM projects WHERE budget > 0")->fetchColumn();
} catch (\Throwable $e) {}
$total_budgets_fmt = mz_format_money($total_budgets, 0);

// ── Support SLA (T4) ──────────────────────────────────────
$sla_open = 0; $sla_closed = 0; $avg_resolution_hours = null;
try {
    $sla = $pdo->query("
        SELECT
            SUM(status='open')   open_c,
            SUM(status='closed') closed_c,
            AVG(CASE WHEN replied_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, replied_at) END) avg_hrs
        FROM support_tickets
    ")->fetch();
    $sla_open   = (int)($sla['open_c']   ?? 0);
    $sla_closed = (int)($sla['closed_c'] ?? 0);
    if ($sla['avg_hrs'] !== null) {
        $avg_resolution_hours = round((float)$sla['avg_hrs'], 1);
    }
} catch (\Throwable $e) {}

// ── Rating Tracker (T4) ──────────────────────────────────
$rating_tracker = [];
try {
    $rating_tracker = $pdo->query("
        SELECT u.id, u.name, u.email,
               COUNT(t.id) AS ticket_count,
               SUM(t.user_rating IS NOT NULL) AS rated_count,
               ROUND(AVG(t.user_rating), 1) AS avg_rating
        FROM users u
        LEFT JOIN support_tickets t ON t.user_id = u.id
        GROUP BY u.id, u.name, u.email
        HAVING ticket_count > 0
        ORDER BY rated_count DESC, ticket_count DESC
        LIMIT 40
    ")->fetchAll();
} catch (\Throwable $e) {}

// ── Monthly Expense Trend — last 12 months (T4 initiative) ─
$monthly_expenses = [];
try {
    $monthly_expenses = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') ym,
               DATE_FORMAT(created_at, '%b %Y') lbl,
               ROUND(SUM(amount), 0) total
        FROM expenses
        GROUP BY ym, lbl ORDER BY ym ASC
        LIMIT 12
    ")->fetchAll();
} catch (\Throwable $e) {}

// ── Log pagination (T7) ───────────────────────────────────
$logs_per_page = 25;
$logs_total    = 0;
$initial_logs  = [];
try {
    $logs_total   = (int) $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
    $initial_logs = $pdo->query("
        SELECT a.action_type, a.details, a.ip_address, a.created_at, u.name, u.email
        FROM activity_logs a JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC LIMIT {$logs_per_page}
    ")->fetchAll();
} catch (\Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم — ميزان Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
    /* ── Admin Dashboard Layout ── */
    body {
        background: var(--bg);
        font-family: 'Tajawal', system-ui, sans-serif;
        min-height: 100vh;
        margin: 0;
    }

    #admin-bg {
        position: fixed;
        inset: 0;
        width: 100%;
        height: 100%;
        z-index: 0;
        pointer-events: none;
        opacity: .25;
    }

    /* Top bar */
    .admin-topbar {
        position: sticky;
        top: 0;
        z-index: 100;
        background: var(--surface);
        border-bottom: 1px solid var(--border);
        padding: 0 28px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        backdrop-filter: blur(10px);
    }

    .admin-topbar-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 17px;
        font-weight: 900;
        color: var(--primary);
        text-decoration: none;
    }

    .admin-topbar-brand img { height: 28px; width: auto; }

    .admin-badge-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: rgba(239,68,68,.12);
        color: var(--danger);
        border: 1px solid rgba(239,68,68,.22);
        padding: 3px 10px;
        border-radius: 16px;
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .4px;
    }

    .admin-topbar-right {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .admin-user-chip {
        font-size: 13px;
        color: var(--text-muted);
        font-weight: 500;
    }

    .admin-logout {
        font-size: 12.5px;
        color: var(--danger);
        text-decoration: none;
        font-weight: 600;
        padding: 5px 12px;
        border: 1px solid rgba(239,68,68,.3);
        border-radius: 8px;
        transition: background .2s;
    }

    .admin-logout:hover { background: rgba(239,68,68,.08); }

    /* Page wrapper — full bleed, no max-width cap */
    .admin-page {
        position: relative;
        z-index: 2;
        width: 100%;
        box-sizing: border-box;
        padding: 28px 32px 60px;
    }

    .admin-page-title {
        font-size: 22px;
        font-weight: 800;
        color: var(--text);
        margin: 0 0 6px;
    }

    .admin-page-sub {
        font-size: 13px;
        color: var(--text-muted);
        margin: 0 0 28px;
    }

    /* Stats grid — asymmetric Bento (6-col at desktop) */
    .admin-stats {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 24px;
    }

    /* Hero card — spans full row */
    .admin-stat-hero {
        grid-column: 1 / -1;
        background: linear-gradient(135deg, rgba(3,105,161,.07) 0%, var(--surface) 100%);
        border-color: rgba(3,105,161,.22);
    }
    .admin-stat-hero .admin-stat-val { font-size: 24px; }

    /* Wide card — spans 2 columns */
    .admin-stat-wide { grid-column: span 2; }

    @media (max-width: 1200px) {
        .admin-stats { grid-template-columns: repeat(3, 1fr); }
        .admin-stat-wide { grid-column: span 1; }
    }
    @media (max-width: 480px) {
        .admin-stats { grid-template-columns: 1fr; }
    }

    .admin-stat {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: transform .25s, box-shadow .25s;
        animation: fadeUp .5s both;
    }

    .admin-stat:hover { transform: translateY(-2px); box-shadow: var(--gs-hover); }

    .admin-stat-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }

    .s-blue   { background: rgba(59,130,246,.12);  }
    .s-green  { background: rgba(16,185,129,.12);  }
    .s-amber  { background: rgba(245,158,11,.12);  }
    .s-violet { background: rgba(139,92,246,.12);  }
    .s-red    { background: rgba(239,68,68,.12);   }

    .admin-stat-val {
        font-size: 20px;
        font-weight: 800;
        color: var(--text);
        line-height: 1;
    }

    .admin-stat-lbl {
        font-size: 11px;
        color: var(--text-muted);
        margin-top: 3px;
    }

    /* Section cards */
    .admin-section {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        margin-bottom: 24px;
        overflow: hidden;
        animation: fadeUp .55s both;
    }

    .admin-section:nth-child(2) { animation-delay: .05s; }
    .admin-section:nth-child(3) { animation-delay: .10s; }
    .admin-section:nth-child(4) { animation-delay: .15s; }

    .admin-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 24px 16px;
        border-bottom: 1px solid var(--border);
    }

    .admin-section-title {
        font-size: 15px;
        font-weight: 700;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 9px;
        margin: 0;
    }

    .admin-count-badge {
        background: var(--surface2);
        color: var(--text-muted);
        border: 1px solid var(--border);
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 700;
    }

    .open-badge {
        background: rgba(239,68,68,.10);
        color: var(--danger);
        border-color: rgba(239,68,68,.2);
    }

    /* Ticket list */
    .ticket-table {
        width: 100%;
        border-collapse: collapse;
    }

    .ticket-table th {
        padding: 10px 24px;
        text-align: right;
        font-size: 11.5px;
        font-weight: 700;
        color: var(--text-muted);
        background: var(--surface2);
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }

    [dir="ltr"] .ticket-table th,
    [dir="ltr"] .ticket-table td { text-align: left; }

    .ticket-table td {
        padding: 13px 24px;
        font-size: 13px;
        color: var(--text);
        border-bottom: 1px solid var(--border);
        vertical-align: top;
    }

    .ticket-table tr:last-child td { border-bottom: none; }

    .ticket-table tr:hover td { background: var(--surface2); }

    .t-status {
        display: inline-flex;
        align-items: center;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }

    .t-open   { background: rgba(239,68,68,.10); color: var(--danger); }
    .t-closed { background: rgba(16,185,129,.10); color: var(--success); }

    .t-msg {
        max-width: 320px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        color: var(--text-muted);
        font-size: 12px;
        margin-top: 3px;
    }

    .t-user { font-weight: 600; }
    .t-email { font-size: 11.5px; color: var(--text-muted); }

    .btn-reply {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 14px;
        background: var(--primary);
        border: none;
        border-radius: 8px;
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        font-family: inherit;
        cursor: pointer;
        transition: opacity .2s, transform .15s;
        white-space: nowrap;
    }

    .btn-reply:hover   { opacity: .9; transform: translateY(-1px); }
    .btn-reply:disabled { opacity: .5; cursor: not-allowed; transform: none; }

    .btn-closed-tag {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 12px;
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-muted);
        font-size: 12px;
        font-weight: 600;
    }

    /* Inline reply form */
    .reply-form {
        margin-top: 8px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .reply-textarea {
        width: 100%;
        padding: 9px 12px;
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text);
        font-size: 13px;
        font-family: inherit;
        resize: vertical;
        min-height: 72px;
        box-sizing: border-box;
        transition: border-color .2s;
    }

    .reply-textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(3,105,161,.1);
    }

    .reply-hint {
        font-size: 11px;
        color: var(--text-muted);
    }

    .reply-actions { display: flex; gap: 8px; align-items: center; }

    .btn-reply-send {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 6px 16px; background: var(--success); border: none;
        border-radius: 8px; color: #fff; font-size: 12px; font-weight: 700;
        font-family: inherit; cursor: pointer; transition: opacity .2s;
    }

    .btn-reply-send:hover { opacity: .9; }
    .btn-reply-send:disabled { opacity: .5; cursor: not-allowed; }

    .btn-cancel-reply {
        background: none; border: 1px solid var(--border); border-radius: 8px;
        padding: 5px 12px; font-size: 12px; font-weight: 600; cursor: pointer;
        color: var(--text-muted); font-family: inherit; transition: border-color .2s;
    }

    .btn-cancel-reply:hover { border-color: var(--primary); color: var(--primary); }

    /* Prev reply preview in closed tickets */
    .prev-reply {
        margin-top: 6px;
        font-size: 12px;
        color: var(--text-muted);
        background: var(--surface2);
        border-right: 2px solid var(--primary);
        padding: 6px 10px;
        border-radius: 0 6px 6px 0;
    }

    [dir="ltr"] .prev-reply { border-right: none; border-left: 2px solid var(--primary); }

    /* Broadcast section */
    .broadcast-form { padding: 22px 24px; }

    .broadcast-input {
        width: 100%; padding: 10px 14px; background: var(--surface2);
        border: 1px solid var(--border); border-radius: var(--radius-sm);
        color: var(--text); font-size: 14px; font-family: inherit;
        transition: border-color .2s; box-sizing: border-box;
        margin-bottom: 10px;
    }

    .broadcast-input:focus {
        outline: none; border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(3,105,161,.1);
    }

    .broadcast-current {
        padding: 14px 20px;
        background: rgba(59,130,246,.07);
        border: 1px solid rgba(59,130,246,.2);
        border-radius: var(--radius-sm);
        font-size: 13px;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
    }

    .broadcast-current-text { flex: 1; color: var(--text); line-height: 1.65; }
    .broadcast-current-label { font-size: 11px; font-weight: 700; color: var(--primary); margin-bottom: 4px; }

    .btn-clear-broadcast {
        padding: 4px 12px; background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.2);
        border-radius: 8px; color: var(--danger); font-size: 12px; font-weight: 600;
        cursor: pointer; font-family: inherit; white-space: nowrap;
        transition: background .2s;
    }

    .btn-clear-broadcast:hover { background: rgba(239,68,68,.18); }

    /* Log list */
    .log-table {
        width: 100%;
        border-collapse: collapse;
    }

    .log-table th {
        padding: 10px 24px;
        text-align: right;
        font-size: 11.5px;
        font-weight: 700;
        color: var(--text-muted);
        background: var(--surface2);
        border-bottom: 1px solid var(--border);
    }

    [dir="ltr"] .log-table th,
    [dir="ltr"] .log-table td { text-align: left; }

    .log-table td {
        padding: 10px 24px;
        font-size: 12.5px;
        color: var(--text);
        border-bottom: 1px solid var(--border);
    }

    .log-table tr:last-child td { border-bottom: none; }

    /* Export area */
    .export-area {
        padding: 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .export-desc { font-size: 13.5px; color: var(--text-muted); }
    .export-desc strong { color: var(--text); font-weight: 700; }

    .btn-export {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 22px;
        background: var(--primary);
        border: none;
        border-radius: var(--radius-sm);
        color: #fff;
        font-size: 14px;
        font-weight: 700;
        font-family: inherit;
        cursor: pointer;
        text-decoration: none;
        transition: opacity .2s, transform .15s;
    }

    .btn-export:hover { opacity: .9; transform: translateY(-1px); }

    /* User table */
    .user-table { width: 100%; border-collapse: collapse; }

    .user-table th {
        padding: 10px 24px;
        text-align: right;
        font-size: 11.5px;
        font-weight: 700;
        color: var(--text-muted);
        background: var(--surface2);
        border-bottom: 1px solid var(--border);
    }

    [dir="ltr"] .user-table th,
    [dir="ltr"] .user-table td { text-align: left; }

    .user-table td {
        padding: 11px 24px;
        font-size: 13px;
        color: var(--text);
        border-bottom: 1px solid var(--border);
    }

    .user-table tr:last-child td { border-bottom: none; }
    .user-table tr:hover td { background: var(--surface2); }

    .admin-chip {
        display: inline-flex;
        padding: 2px 9px;
        background: rgba(239,68,68,.10);
        color: var(--danger);
        border: 1px solid rgba(239,68,68,.2);
        border-radius: 10px;
        font-size: 10.5px;
        font-weight: 700;
    }

    /* Animations */
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(20px); }
        to   { opacity: 1; transform: none; }
    }

    /* Controls */
    .topbar-ctrls { display: flex; align-items: center; gap: 8px; }

    .tb-btn {
        width: 32px;
        height: 32px;
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: 8px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 700;
        color: var(--text-muted);
        font-family: inherit;
        transition: border-color .2s, color .2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .tb-btn:hover { border-color: var(--primary); color: var(--primary); }

    /* Promote button */
    .btn-promote {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 3px 10px;
        background: rgba(139,92,246,.10);
        border: 1px solid rgba(139,92,246,.28);
        border-radius: 8px; color: #7c3aed;
        font-size: 11.5px; font-weight: 700; font-family: inherit;
        cursor: pointer; transition: background .18s, transform .15s;
        white-space: nowrap;
    }
    .btn-promote:hover   { background: rgba(139,92,246,.18); transform: translateY(-1px); }
    .btn-promote:disabled { opacity: .5; cursor: not-allowed; transform: none; }

    /* Demote button */
    .btn-demote {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 3px 10px;
        background: rgba(239,68,68,.08);
        border: 1px solid rgba(239,68,68,.25);
        border-radius: 8px; color: var(--danger);
        font-size: 11.5px; font-weight: 700; font-family: inherit;
        cursor: pointer; transition: background .18s, transform .15s;
        white-space: nowrap;
    }
    .btn-demote:hover    { background: rgba(239,68,68,.16); transform: translateY(-1px); }
    .btn-demote:disabled { opacity: .5; cursor: not-allowed; transform: none; }

    /* Super-admin role chip */
    .super-chip {
        display: inline-flex; padding: 2px 9px;
        background: rgba(139,92,246,.10); color: #7c3aed;
        border: 1px solid rgba(139,92,246,.25);
        border-radius: 10px; font-size: 10.5px; font-weight: 700;
    }

    /* Log filter bar */
    .log-filter-bar {
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        padding: 14px 24px; border-bottom: 1px solid var(--border);
        background: var(--surface2);
    }
    .log-filter-select,
    .log-filter-date {
        padding: 7px 12px; font-size: 13px; font-family: inherit;
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 8px; color: var(--text); min-width: 180px;
        transition: border-color .2s;
    }
    .log-filter-select:focus,
    .log-filter-date:focus { outline: none; border-color: var(--primary); }
    .btn-filter-apply {
        padding: 7px 18px; background: var(--primary); border: none;
        border-radius: 8px; color: #fff; font-size: 13px; font-weight: 700;
        font-family: inherit; cursor: pointer; transition: opacity .2s;
    }
    .btn-filter-apply:hover { opacity: .88; }
    .btn-filter-reset {
        padding: 7px 14px; background: none;
        border: 1px solid var(--border); border-radius: 8px;
        color: var(--text-muted); font-size: 13px; font-weight: 600;
        font-family: inherit; cursor: pointer; transition: border-color .2s;
    }
    .btn-filter-reset:hover { border-color: var(--primary); color: var(--primary); }

    @media (max-width: 768px) {
        .admin-topbar { padding: 0 16px; }
        .admin-page   { padding: 20px 16px 48px; }
        .ticket-table th, .ticket-table td,
        .log-table th,    .log-table td,
        .user-table th,   .user-table td { padding: 10px 14px; }
        .admin-stats { grid-template-columns: repeat(2, 1fr); }
        .t-msg { max-width: 160px; }
        .export-area { padding: 16px; }
        .log-filter-bar { padding: 12px 14px; }
        .log-filter-select, .log-filter-date { min-width: 140px; }
    }

    /* ── Command-center 3-col grid ── */
    .admin-command-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 20px;
        align-items: stretch;
        margin-bottom: 24px;
    }
    @media (min-width: 900px) {
        .admin-command-grid { grid-template-columns: 1fr 1fr; }
    }
    @media (min-width: 1200px) {
        .admin-command-grid { grid-template-columns: 1.05fr 1fr 1.15fr; }
    }

    /* Column wrapper inside the command grid */
    .admin-col {
        display: flex;
        flex-direction: column;
        gap: 20px;
        min-width: 0;
    }
    /* Stretch the single card inside col-1 to match neighbours */
    .admin-col--stretch .admin-section { flex: 1 1 auto; display: flex; flex-direction: column; }
    .admin-col--stretch .chart-card-body { flex: 1 1 auto; display: flex; flex-direction: column; }
    .admin-col--stretch .chart-grid { flex: 1 1 auto; }

    /* Sections inside grid columns have no bottom margin — gap handles it */
    .admin-command-grid .admin-section { margin-bottom: 0; }

    /* Secondary 2-col grid below the command grid */
    .admin-secondary-grid {
        display: grid;
        grid-template-columns: 1fr 1.4fr;
        gap: 20px;
        margin-bottom: 24px;
        align-items: start;
    }
    .admin-secondary-grid .admin-section { margin-bottom: 0; }
    @media (max-width: 900px) {
        .admin-secondary-grid { grid-template-columns: 1fr; }
    }

    /* ── Storage card (T3) ── */
    .storage-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        padding: 20px 24px;
    }
    .storage-item {
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 16px 18px;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .storage-item-icon {
        width: 40px; height: 40px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 18px; flex-shrink: 0;
    }
    .storage-item-val { font-size: 20px; font-weight: 800; color: var(--text); line-height: 1; }
    .storage-item-lbl { font-size: 11.5px; color: var(--text-muted); margin-top: 2px; }

    /* ── Chart cards (T4) ── */
    .chart-card-body { padding: 20px 24px; }
    .chart-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    @media (max-width: 640px) { .chart-grid { grid-template-columns: 1fr; } }
    .chart-wrap { position: relative; height: 230px; }
    .chart-title { font-size: 12.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 10px; text-align: center; }
    .top-users-list { list-style: none; padding: 0; margin: 0; }
    .top-users-list li {
        display: flex; align-items: center; justify-content: space-between;
        padding: 7px 0; border-bottom: 1px solid var(--border); font-size: 13px;
    }
    .top-users-list li:last-child { border-bottom: none; }
    .top-u-name { font-weight: 600; color: var(--text); }

    /* ── Monthly trend chart (full-width) ── */
    .monthly-chart-wrap { position: relative; height: 260px; }

    /* ── Rating Tracker table ── */
    .rating-tracker-table { width: 100%; border-collapse: collapse; }
    .rating-tracker-table th {
        padding: 10px 20px; text-align: right; font-size: 11.5px;
        font-weight: 700; color: var(--text-muted); background: var(--surface2);
        border-bottom: 1px solid var(--border);
    }
    [dir="ltr"] .rating-tracker-table th,
    [dir="ltr"] .rating-tracker-table td { text-align: left; }
    .rating-tracker-table td {
        padding: 10px 20px; font-size: 12.5px; color: var(--text);
        border-bottom: 1px solid var(--border); vertical-align: middle;
    }
    .rating-tracker-table tr:last-child td { border-bottom: none; }
    .rating-tracker-table tr:hover td { background: var(--surface2); }
    .rt-badge {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 2px 10px; border-radius: 12px;
        font-size: 11px; font-weight: 700;
    }
    .rt-rated   { background: rgba(16,185,129,.10); color: #10b981; }
    .rt-pending { background: rgba(239,68,68,.10);  color: var(--danger); }
    .rt-stars   { color: #f59e0b; }
    .top-u-meta { font-size: 11px; color: var(--text-muted); }

    /* ── Feedback list (T4) ── */
    .feedback-wrap {
        max-height: 260px;
        overflow-y: auto;
        padding: 12px 24px;
    }
    .feedback-item {
        padding: 10px 0;
        border-bottom: 1px solid var(--border);
        font-size: 13px;
    }
    .feedback-item:last-child { border-bottom: none; }
    .feedback-stars { color: #f59e0b; font-size: 12px; margin-bottom: 3px; }
    .feedback-text { color: var(--text); line-height: 1.6; }
    .feedback-meta { font-size: 11px; color: var(--text-muted); margin-top: 3px; }

    /* ── Broadcast live preview (T6) ── */
    .broadcast-style-row {
        display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
        margin-bottom: 10px;
    }
    .broadcast-style-group {
        display: flex; flex-direction: column; gap: 4px; font-size: 12px;
        color: var(--text-muted); font-weight: 600;
    }
    .broadcast-style-group input[type=color] {
        width: 36px; height: 28px; border: 1px solid var(--border);
        border-radius: 6px; cursor: pointer; padding: 1px 2px;
        background: var(--surface2);
    }
    .broadcast-style-group input[type=number] {
        width: 60px; padding: 5px 8px;
        background: var(--surface2); border: 1px solid var(--border);
        border-radius: 6px; color: var(--text); font-family: inherit; font-size: 13px;
    }
    .broadcast-preview {
        border-radius: var(--radius-sm);
        padding: 10px 14px;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 10px;
        transition: all .2s;
        min-height: 38px;
        display: flex;
        align-items: center;
        gap: 8px;
        border: 1px dashed var(--border);
    }
    .broadcast-preview-label {
        font-size: 11px; color: var(--text-muted); font-weight: 700;
        margin-bottom: 5px;
    }

    /* ── Email tester (T8) ── */
    .email-tester-form { padding: 20px 24px; display: flex; flex-direction: column; gap: 12px; }
    .email-tester-row { display: flex; gap: 10px; flex-wrap: wrap; }
    .et-select, .et-input {
        flex: 1; min-width: 140px;
        padding: 9px 12px; background: var(--surface2);
        border: 1px solid var(--border); border-radius: var(--radius-sm);
        color: var(--text); font-family: inherit; font-size: 13px;
        transition: border-color .2s;
    }
    .et-select:focus, .et-input:focus { outline: none; border-color: var(--primary); }

    /* ── User action buttons (T5) ── */
    .btn-reset-pw {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 3px 10px;
        background: rgba(245,158,11,.10);
        border: 1px solid rgba(245,158,11,.28);
        border-radius: 8px; color: #b45309;
        font-size: 11.5px; font-weight: 700; font-family: inherit;
        cursor: pointer; transition: background .18s; white-space: nowrap;
    }
    .btn-reset-pw:hover { background: rgba(245,158,11,.2); }
    .btn-send-email {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 3px 10px;
        background: rgba(59,130,246,.10);
        border: 1px solid rgba(59,130,246,.28);
        border-radius: 8px; color: #1d4ed8;
        font-size: 11.5px; font-weight: 700; font-family: inherit;
        cursor: pointer; transition: background .18s; white-space: nowrap;
    }
    .btn-send-email:hover { background: rgba(59,130,246,.2); }

    /* ── Pagination (T7) ── */
    .log-pagination {
        display: flex; align-items: center; justify-content: space-between;
        padding: 12px 24px; border-top: 1px solid var(--border);
        flex-wrap: wrap; gap: 10px;
    }
    .log-page-info { font-size: 12.5px; color: var(--text-muted); }
    .log-page-btns { display: flex; gap: 6px; }
    .btn-page {
        padding: 5px 14px; background: var(--surface2);
        border: 1px solid var(--border); border-radius: 8px;
        color: var(--text); font-size: 12.5px; font-weight: 600;
        font-family: inherit; cursor: pointer; transition: all .18s;
    }
    .btn-page:hover:not(:disabled) { border-color: var(--primary); color: var(--primary); }
    .btn-page:disabled { opacity: .4; cursor: not-allowed; }
    .btn-page.active { background: var(--primary); color: #fff; border-color: var(--primary); }

    /* ── Custom email modal (T5) ── */
    .email-modal-overlay {
        position: fixed; inset: 0; background: rgba(0,0,0,.45);
        z-index: 9000; display: none; align-items: center; justify-content: center;
        padding: 20px;
    }
    .email-modal-overlay.open { display: flex; }
    .email-modal {
        background: var(--surface); border: 1px solid var(--border);
        border-radius: var(--radius-lg); padding: 28px 30px;
        width: 100%; max-width: 480px;
        box-shadow: 0 20px 60px rgba(0,0,0,.15);
    }
    .email-modal-title { font-size: 16px; font-weight: 800; margin: 0 0 18px; color: var(--text); }
    .email-modal textarea {
        width: 100%; min-height: 120px; padding: 10px 12px;
        background: var(--surface2); border: 1px solid var(--border);
        border-radius: var(--radius-sm); color: var(--text);
        font-family: inherit; font-size: 13px; resize: vertical;
        box-sizing: border-box; margin-bottom: 10px; transition: border-color .2s;
    }
    .email-modal textarea:focus { outline: none; border-color: var(--primary); }
    .email-modal input {
        width: 100%; padding: 9px 12px;
        background: var(--surface2); border: 1px solid var(--border);
        border-radius: var(--radius-sm); color: var(--text);
        font-family: inherit; font-size: 13px; box-sizing: border-box;
        margin-bottom: 14px; transition: border-color .2s;
    }
    .email-modal input:focus { outline: none; border-color: var(--primary); }
    .email-modal-btns { display: flex; gap: 8px; justify-content: flex-end; }

    /* ── Button loading spinner ── */
    .btn-spinner {
        display: inline-block;
        width: 12px; height: 12px;
        border: 2px solid rgba(255,255,255,.4);
        border-top-color: #fff;
        border-radius: 50%;
        animation: mz-spin .6s linear infinite;
        vertical-align: middle;
        margin-inline-end: 6px;
        flex-shrink: 0;
    }
    @keyframes mz-spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<canvas id="admin-bg" aria-hidden="true"></canvas>

<!-- ── Top Bar ── -->
<header class="admin-topbar">
    <div style="display:flex;align-items:center;gap:14px;">
        <a href="/admin/dashboard.php" class="admin-topbar-brand">
            <img src="/assets/icons/logo.png" alt="ميزان"
                 onerror="this.replaceWith(Object.assign(document.createElement('span'),{textContent:'⚖️',style:'font-size:20px'}))">
            <span>ميزان</span>
        </a>
        <span class="admin-badge-pill">
            <svg width="9" height="9" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1a5 5 0 0 1 5 5v2h1a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V10a2 2 0 0 1 2-2h1V6a5 5 0 0 1 5-5z"/></svg>
            Admin Panel
        </span>
    </div>
    <div class="admin-topbar-right">
        <div class="topbar-ctrls">
            <button class="tb-btn" id="themeToggle" onclick="toggleTheme()" title="Toggle theme">🌙</button>
            <button class="tb-btn" id="langToggle" onclick="toggleLang()" title="Switch language">EN</button>
        </div>
        <span class="admin-user-chip">👤 <?= $admin_name ?></span>
        <a href="/logout.php" class="admin-logout">تسجيل الخروج</a>
    </div>
</header>

<!-- ── Page ── -->
<div class="admin-page">
    <h1 class="admin-page-title" data-i18n="admin_dashboard">لوحة تحكم المدير</h1>
    <p class="admin-page-sub" data-i18n="admin_dashboard_sub">إدارة المستخدمين، تذاكر الدعم، وسجلات النشاط</p>

    <!-- ── Stats — Bento Grid ──
         Row 1: 6 basic metrics (1-col each)
         Row 2: Budgets hero (full-width)
         Row 3: DB size + File storage + SLA (2-col each)
    ── -->
    <div class="admin-stats">

        <!-- ▸ Row 1: Basic Metrics -->
        <div class="admin-stat" style="animation-delay:.00s;">
            <div class="admin-stat-icon s-blue">👥</div>
            <div>
                <div class="admin-stat-val"><?= $total_users ?></div>
                <div class="admin-stat-lbl" data-i18n="admin_total_users">إجمالي المستخدمين</div>
            </div>
        </div>
        <div class="admin-stat" style="animation-delay:.05s;">
            <div class="admin-stat-icon s-green">◈</div>
            <div>
                <div class="admin-stat-val"><?= $total_projects ?></div>
                <div class="admin-stat-lbl" data-i18n="admin_total_projects">إجمالي المشاريع</div>
            </div>
        </div>
        <div class="admin-stat" style="animation-delay:.10s;">
            <div class="admin-stat-icon s-amber">📋</div>
            <div>
                <div class="admin-stat-val"><?= $total_expenses ?></div>
                <div class="admin-stat-lbl" data-i18n="admin_total_expenses">إجمالي المصاريف</div>
            </div>
        </div>
        <div class="admin-stat" style="animation-delay:.15s;">
            <div class="admin-stat-icon s-violet">📁</div>
            <div>
                <div class="admin-stat-val"><?= $total_files ?></div>
                <div class="admin-stat-lbl" data-i18n="admin_total_files">إجمالي الملفات</div>
            </div>
        </div>
        <div class="admin-stat" style="animation-delay:.20s;">
            <div class="admin-stat-icon s-red">🎫</div>
            <div>
                <div class="admin-stat-val" id="openTicketCount"><?= count($open_tickets) ?></div>
                <div class="admin-stat-lbl" data-i18n="admin_open_tickets">تذاكر مفتوحة</div>
            </div>
        </div>
        <div class="admin-stat" style="animation-delay:.25s;">
            <div class="admin-stat-icon s-amber">⭐</div>
            <div>
                <div class="admin-stat-val">
                    <?php if ($avg_satisfaction !== null): ?>
                        <?= number_format($avg_satisfaction, 1) ?><span style="font-size:14px;font-weight:600;color:var(--text-muted);">/5</span>
                    <?php else: ?>
                        <span style="font-size:18px;color:var(--text-muted);">—</span>
                    <?php endif; ?>
                </div>
                <div class="admin-stat-lbl" data-i18n="admin_avg_satisfaction">متوسط رضا التذاكر</div>
                <?php if ($satisfaction_count > 0): ?>
                <div style="font-size:10.5px;color:var(--text-muted);margin-top:2px;"><?= $satisfaction_count ?> <?= $satisfaction_count === 1 ? 'تقييم' : 'تقييمات' ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ▸ Row 2: Hero — Budgets Managed (col-span-full) -->
        <div class="admin-stat admin-stat-hero" style="animation-delay:.30s;">
            <div class="admin-stat-icon s-green" style="width:46px;height:46px;font-size:20px;">💰</div>
            <div style="flex:1;">
                <div class="admin-stat-val" style="font-size:24px;"><?= $total_budgets_fmt ?></div>
                <div class="admin-stat-lbl" data-i18n="admin_total_budgets">الميزانيات المُدارة</div>
            </div>
            <div style="font-size:11px;color:rgba(3,105,161,.85);background:rgba(3,105,161,.08);padding:6px 14px;border-radius:20px;white-space:nowrap;font-weight:600;" data-i18n="admin_budgets_note">إجمالي الميزانيات عبر جميع المشاريع</div>
        </div>

        <!-- ▸ Row 3: Storage + SLA (span-2 each = 6 cols total) -->
        <div class="admin-stat admin-stat-wide" style="animation-delay:.35s;">
            <div class="admin-stat-icon s-violet">🗄️</div>
            <div>
                <div class="admin-stat-val"><?= $db_size_fmt ?></div>
                <div class="admin-stat-lbl" data-i18n="admin_storage_db">حجم قاعدة البيانات</div>
            </div>
        </div>
        <div class="admin-stat admin-stat-wide" style="animation-delay:.40s;">
            <div class="admin-stat-icon s-blue">💾</div>
            <div>
                <div class="admin-stat-val"><?= $uploads_fmt ?></div>
                <div class="admin-stat-lbl" data-i18n="admin_storage_uploads">تخزين الملفات</div>
            </div>
        </div>
        <div class="admin-stat admin-stat-wide" style="animation-delay:.45s;">
            <div class="admin-stat-icon s-red">📊</div>
            <div>
                <div class="admin-stat-val">
                    <?= $sla_closed ?><span style="font-size:13px;font-weight:600;color:var(--text-muted);">/<?= $sla_open + $sla_closed ?></span>
                </div>
                <div class="admin-stat-lbl" data-i18n="admin_sla_resolved">تذاكر مُحلّة</div>
                <?php if ($avg_resolution_hours !== null): ?>
                <div style="font-size:10.5px;color:var(--text-muted);margin-top:2px;">
                    <span data-i18n="admin_avg_hours">متوسط</span> <?= $avg_resolution_hours ?> <span data-i18n="admin_hours">ساعة</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         Command-Center Grid  (3-col at xl · 2-col at lg · 1 on mobile)
         Col 1  — Analytics & Statistics    (full-height left)
         Col 2  — Announcement + Email Tester  (middle)
         Col 3  — Support Tickets + Feedback + Rating Tracker
    ══════════════════════════════════════════════════════════ -->
    <div class="admin-command-grid">

        <!-- ── Column 1: Analytics & Statistics ── -->
        <div class="admin-col admin-col--stretch">
            <div class="admin-section">
                <div class="admin-section-head">
                    <h2 class="admin-section-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        <span data-i18n="admin_analytics_title">التحليلات والإحصاءات</span>
                    </h2>
                </div>
                <div class="chart-card-body">
                    <div class="chart-grid">
                        <div>
                            <div class="chart-title" data-i18n="admin_chart_ratings">توزيع التقييمات</div>
                            <div class="chart-wrap"><canvas id="chartRatingsDist"></canvas></div>
                        </div>
                        <div>
                            <div class="chart-title" data-i18n="admin_chart_satisfaction">مستوى الرضا (1-5 نجوم)</div>
                            <div class="chart-wrap"><canvas id="chartStars"></canvas></div>
                        </div>
                        <div>
                            <div class="chart-title" data-i18n="admin_chart_projects">المشاريع: شخصي / تجاري</div>
                            <div class="chart-wrap"><canvas id="chartProjects"></canvas></div>
                        </div>
                        <div>
                            <div class="chart-title" data-i18n="admin_chart_top_users">أكثر المستخدمين نشاطاً</div>
                            <ul class="top-users-list" id="topUsersList">
                            <?php foreach ($top_users as $tu): ?>
                            <li>
                                <div>
                                    <div class="top-u-name"><?= htmlspecialchars($tu['name']) ?></div>
                                    <div class="top-u-meta"><?= (int)$tu['ec'] ?> مصروف · <?= (int)$tu['pc'] ?> مشروع</div>
                                </div>
                                <strong><?= number_format((float)$tu['ts'], 0) ?></strong>
                            </li>
                            <?php endforeach; ?>
                            <?php if (empty($top_users)): ?>
                            <li style="color:var(--text-muted);justify-content:center;" data-i18n="admin_no_data">لا توجد بيانات</li>
                            <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- /col-1 -->

        <!-- (Tickets section moved to Column 3 below) -->
        <?php $___tickets_block_render = function() use ($tickets, $open_tickets) { ?>
            <div class="admin-section" style="overflow-y:auto;max-height:650px;">
                <div class="admin-section-head" style="position:sticky;top:0;background:var(--surface);z-index:2;">
                    <h2 class="admin-section-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        <span data-i18n="admin_support_tickets">تذاكر الدعم الفني</span>
                    </h2>
                    <span class="admin-count-badge open-badge" id="openBadge"><span id="openBadgeNum"><?= count($open_tickets) ?></span> <span data-i18n="admin_open_label">مفتوحة</span></span>
                </div>
                <?php if (empty($tickets)): ?>
                <div style="padding:40px 24px; text-align:center; color:var(--text-muted); font-size:14px;" data-i18n="admin_no_tickets">
                    لا توجد تذاكر دعم حتى الآن
                </div>
                <?php else: ?>
                <div style="overflow-x:auto;">
                <table class="ticket-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th data-i18n="admin_col_user">المستخدم</th>
                            <th data-i18n="admin_col_subject">الموضوع</th>
                            <th data-i18n="admin_col_status">الحالة</th>
                            <th data-i18n="admin_col_date">التاريخ</th>
                            <th data-i18n="admin_col_action">إجراء</th>
                        </tr>
                    </thead>
                    <tbody id="ticketsTbody">
                    <?php foreach ($tickets as $t): ?>
                    <tr id="ticket-row-<?= (int)$t['id'] ?>">
                        <td style="color:var(--text-muted);font-size:12px;">#<?= (int)$t['id'] ?></td>
                        <td>
                            <div class="t-user"><?= htmlspecialchars($t['user_name']) ?></div>
                            <div class="t-email"><?= htmlspecialchars($t['user_email']) ?></div>
                        </td>
                        <td>
                            <div style="font-weight:600;"><?= htmlspecialchars($t['subject']) ?></div>
                            <div class="t-msg"><?= htmlspecialchars($t['message']) ?></div>
                            <?php if ($t['status'] === 'closed' && $t['admin_reply']): ?>
                            <div class="prev-reply">↩ <?= htmlspecialchars(mb_substr($t['admin_reply'], 0, 100)) ?><?= mb_strlen($t['admin_reply']) > 100 ? '...' : '' ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="t-status t-<?= $t['status'] === 'open' ? 'open' : 'closed' ?>" id="status-<?= (int)$t['id'] ?>">
                            <?= $t['status'] === 'open' ? 'مفتوحة' : 'مغلقة' ?>
                        </span></td>
                        <td style="color:var(--text-muted); font-size:12px; white-space:nowrap;">
                            <?= date('Y/m/d', strtotime($t['created_at'])) ?>
                        </td>
                        <td id="action-<?= (int)$t['id'] ?>">
                            <?php if ($t['status'] === 'open'): ?>
                            <div>
                                <button class="btn-reply" onclick="showReplyForm(<?= (int)$t['id'] ?>)" id="reply-btn-<?= (int)$t['id'] ?>" data-i18n="admin_reply_close">
                                    ✉️ رد وإغلاق
                                </button>
                                <div class="reply-form" id="reply-form-<?= (int)$t['id'] ?>" style="display:none;">
                                    <textarea class="reply-textarea" id="reply-text-<?= (int)$t['id'] ?>"
                                        placeholder="اكتب ردك هنا... (يظهر للمستخدم)" rows="3"></textarea>
                                    <div class="reply-actions">
                                        <button class="btn-reply-send" onclick="submitReply(<?= (int)$t['id'] ?>)" data-i18n="admin_send_close">
                                            ↩ إرسال وإغلاق
                                        </button>
                                        <button class="btn-reply-send" style="background:var(--secondary,#1d4ed8);" onclick="submitReplyOpen(<?= (int)$t['id'] ?>)" data-i18n="admin_reply_open">
                                            رد بدون إغلاق
                                        </button>
                                        <button class="btn-cancel-reply" onclick="hideReplyForm(<?= (int)$t['id'] ?>)" data-i18n="admin_cancel">إلغاء</button>
                                    </div>
                                    <div class="reply-hint" data-i18n="admin_optional_note">اختياري: اتركه فارغاً لإغلاق بدون رد</div>
                                </div>
                            </div>
                            <?php else: ?>
                            <span class="btn-closed-tag">✓ <?= $t['admin_reply'] ? 'تم الرد' : 'مغلقة' ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        <?php }; ?>

        <!-- ── Column 2: Broadcast + Email Tester ── -->
        <div class="admin-col">

            <!-- Broadcast (T6) -->
            <div class="admin-section">
                <div class="admin-section-head">
                    <h2 class="admin-section-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                        <span data-i18n="admin_announce_title">إعلان للمستخدمين</span>
                    </h2>
                    <span class="admin-count-badge" id="annStatus" data-i18n="<?= $active_ann ? 'admin_ann_active' : 'admin_ann_none' ?>"><?= $active_ann ? 'نشط' : 'لا يوجد' ?></span>
                </div>
                <div class="broadcast-form">
                    <?php if ($active_ann): ?>
                    <div class="broadcast-current" id="currentAnnBox">
                        <div>
                            <div class="broadcast-current-label" data-i18n="admin_current_ann">الإعلان الحالي</div>
                            <div class="broadcast-current-text"><?= htmlspecialchars($active_ann['message_ar']) ?></div>
                        </div>
                        <button class="btn-clear-broadcast" onclick="clearBroadcast(this)" data-i18n="admin_cancel_ann">× إلغاء</button>
                    </div>
                    <?php else: ?>
                    <div id="currentAnnBox" style="display:none;"></div>
                    <?php endif; ?>

                    <div style="margin-bottom:6px;font-size:12.5px;color:var(--text-muted);font-weight:600;" data-i18n="admin_new_announce">إعلان جديد</div>
                    <input type="text" class="broadcast-input" id="annAr" placeholder="النص بالعربية (مطلوب)" maxlength="500" oninput="updateBroadcastPreview()">
                    <input type="text" class="broadcast-input" id="annEn" placeholder="English text (optional)" maxlength="500">

                    <div class="broadcast-style-row">
                        <div class="broadcast-style-group">
                            <label data-i18n="admin_ann_bg_color">لون الخلفية</label>
                            <input type="color" id="annBgColor" value="#1e3a5f" oninput="updateBroadcastPreview()">
                        </div>
                        <div class="broadcast-style-group">
                            <label data-i18n="admin_ann_text_color">لون النص</label>
                            <input type="color" id="annTextColor" value="#ffffff" oninput="updateBroadcastPreview()">
                        </div>
                        <div class="broadcast-style-group">
                            <label data-i18n="admin_ann_font_size">حجم الخط</label>
                            <input type="number" id="annFontSize" value="14" min="11" max="20" oninput="updateBroadcastPreview()">
                        </div>
                    </div>

                    <div class="broadcast-preview-label" data-i18n="admin_ann_preview">معاينة مباشرة:</div>
                    <div class="broadcast-preview" id="annPreview" style="background:#1e3a5f;color:#ffffff;font-size:14px;">
                        📢 <span id="annPreviewText" data-i18n="admin_ann_preview_placeholder">اكتب النص لتظهر المعاينة...</span>
                    </div>

                    <div style="display:flex;gap:10px;align-items:center;margin-top:6px;">
                        <button class="btn-export" onclick="sendBroadcast()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                            <span data-i18n="admin_publish">نشر الإعلان</span>
                        </button>
                        <span style="font-size:11.5px;color:var(--text-muted);" data-i18n="admin_publish_note">يظهر فوراً لجميع المستخدمين</span>
                    </div>
                </div>
            </div>

            <!-- Email Template Tester (T8) -->
            <div class="admin-section">
                <div class="admin-section-head">
                    <h2 class="admin-section-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <span data-i18n="admin_email_tester_title">اختبار قوالب البريد الإلكتروني</span>
                    </h2>
                </div>
                <div class="email-tester-form">
                    <div class="email-tester-row">
                        <select class="et-select" id="etTemplate">
                            <option value="welcome" data-i18n="admin_et_tpl_welcome">ترحيب</option>
                            <option value="reset_password" data-i18n="admin_et_tpl_reset">إعادة كلمة المرور</option>
                            <option value="warranty_alert" data-i18n="admin_et_tpl_warranty">تنبيه ضمان</option>
                            <option value="budget_alert" data-i18n="admin_et_tpl_budget">تنبيه ميزانية</option>
                        </select>
                        <select class="et-select" id="etLang">
                            <option value="ar" data-i18n="admin_et_lang_ar">عربي</option>
                            <option value="en" data-i18n="admin_et_lang_en">English</option>
                        </select>
                        <input type="email" class="et-input" id="etTarget" placeholder="البريد المستهدف" data-i18n-placeholder="admin_et_target_ph">
                    </div>
                    <div>
                        <button class="btn-export" id="etSendBtn" onclick="sendTestEmail()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            <span data-i18n="admin_et_send_btn">إرسال بريد اختباري</span>
                        </button>
                    </div>
                </div>
            </div>

        </div><!-- /col-2 -->

        <!-- ── Column 3: Support Tickets + Customer Feedback + Rating Tracker ── -->
        <div class="admin-col">

            <!-- Support Tickets (moved here from former col-1) -->
            <?php $___tickets_block_render(); ?>

            <!-- Customer Feedback -->
            <div class="admin-section">
                <div class="admin-section-head">
                    <h2 class="admin-section-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        <span data-i18n="admin_feedback_title">آراء العملاء</span>
                    </h2>
                    <span class="admin-count-badge"><?= count($feedback_list) ?></span>
                </div>
                <?php if (empty($feedback_list)): ?>
                <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px;" data-i18n="admin_no_feedback">لا توجد آراء مكتوبة بعد</div>
                <?php else: ?>
                <div class="feedback-wrap">
                    <?php foreach ($feedback_list as $fb): ?>
                    <div class="feedback-item">
                        <div class="feedback-stars"><?= str_repeat('★', (int)$fb['user_rating']) . str_repeat('☆', 5 - (int)$fb['user_rating']) ?></div>
                        <div class="feedback-text"><?= htmlspecialchars($fb['satisfaction_comment']) ?></div>
                        <div class="feedback-meta"><?= htmlspecialchars($fb['un']) ?> · <?= date('Y/m/d', strtotime($fb['created_at'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Rating Tracker -->
            <div class="admin-section">
                <div class="admin-section-head">
                    <h2 class="admin-section-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        <span data-i18n="admin_rating_tracker">متابعة تقييمات المستخدمين</span>
                    </h2>
                    <span class="admin-count-badge"><?= count($rating_tracker) ?> <span data-i18n="admin_active_users">مستخدم</span></span>
                </div>
                <div style="overflow-x:auto;">
                    <table class="rating-tracker-table">
                        <thead>
                            <tr>
                                <th data-i18n="admin_rt_user">المستخدم</th>
                                <th data-i18n="admin_rt_tickets">التذاكر</th>
                                <th data-i18n="admin_rt_rated">قيّموا</th>
                                <th data-i18n="admin_rt_status">الحالة</th>
                                <th data-i18n="admin_rt_avg">متوسط التقييم</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rating_tracker as $rt): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;"><?= htmlspecialchars($rt['name']) ?></div>
                                    <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($rt['email']) ?></div>
                                </td>
                                <td><?= (int)$rt['ticket_count'] ?></td>
                                <td><?= (int)$rt['rated_count'] ?> / <?= (int)$rt['ticket_count'] ?></td>
                                <td>
                                    <?php if ((int)$rt['rated_count'] > 0): ?>
                                    <span class="rt-badge rt-rated" data-i18n="admin_rt_badge_rated">✓ قيّم</span>
                                    <?php else: ?>
                                    <span class="rt-badge rt-pending" data-i18n="admin_rt_badge_pending">— لم يقيّم</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($rt['avg_rating']): ?>
                                    <span class="rt-stars">★</span> <strong><?= $rt['avg_rating'] ?></strong> / 5
                                    <?php else: ?>
                                    <span style="color:var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($rating_tracker)): ?>
                            <tr><td colspan="5" style="text-align:center;padding:28px;color:var(--text-muted);" data-i18n="admin_no_data">لا توجد بيانات</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /col-3 -->

    </div><!-- /admin-command-grid -->

    <!-- ── Monthly Expense Trend (full-width) ── -->
    <div class="admin-section">
        <div class="admin-section-head">
            <h2 class="admin-section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 12 8 7 12 17 15 12 18 16 21 10"/></svg>
                <span data-i18n="admin_chart_monthly_title">اتجاه المصاريف الشهرية</span>
            </h2>
            <span class="admin-count-badge" data-i18n="admin_last_12">آخر 12 شهراً</span>
        </div>
        <div style="padding:20px 24px;">
            <div class="monthly-chart-wrap"><canvas id="chartMonthly"></canvas></div>
        </div>
    </div>

    <!-- ── Activity Log — full-width ── -->
    <div class="admin-section">
        <div class="admin-section-head">
            <h2 class="admin-section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <span data-i18n="admin_activity_log">سجل النشاطات</span>
            </h2>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <label for="logPerPage" style="font-size:12px;color:var(--text-muted);font-weight:600;white-space:nowrap;" data-i18n="admin_rows_per_page">سجلات لكل صفحة:</label>
                <select id="logPerPage"
                        class="log-filter-select"
                        style="min-width:76px;padding:5px 10px;font-size:13px;"
                        onchange="logChangePerPage()"
                        aria-label="عدد السجلات في الصفحة">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
        <div class="export-area">
            <div>
                <strong class="export-desc" data-i18n="admin_activity_desc">سجل نشاطات جميع المستخدمين</strong>
                <p class="export-desc" style="margin:4px 0 0;" data-i18n="admin_csv_desc">تحميل ملف CSV يحتوي على آخر ٥٠٫٠٠٠ سجل — متوافق مع Excel (BOM UTF-8)</p>
            </div>
            <a href="/api/export_logs.php" class="btn-export" id="exportLogsBtn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span data-i18n="admin_download_log">📥 تحميل السجل (Excel)</span>
            </a>
        </div>
        <div class="log-filter-bar">
            <select id="logUserFilter" class="log-filter-select" aria-label="تصفية بالمستخدم" data-i18n-title="admin_log_filter_user">
                <option value="" data-i18n="admin_all_users">كل المستخدمين</option>
                <?php foreach ($all_users as $u): ?>
                <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['name']) ?> — <?= htmlspecialchars($u['email']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" id="logDateFilter" class="log-filter-date"
                   placeholder="اختر تاريخاً"
                   data-i18n-placeholder="admin_select_date"
                   aria-label="تصفية بالتاريخ"
                   readonly>
            <button class="btn-filter-apply" onclick="applyLogFilters()" data-i18n="admin_filter_apply">تطبيق</button>
            <button class="btn-filter-reset" onclick="resetLogFilters()" data-i18n="admin_filter_reset">إعادة ضبط</button>
        </div>
        <div style="overflow-x:auto; border-top:1px solid var(--border);">
        <table class="log-table">
            <thead>
                <tr>
                    <th data-i18n="admin_col_name">الاسم</th>
                    <th data-i18n="admin_col_action_type">الإجراء</th>
                    <th data-i18n="admin_col_details">التفاصيل</th>
                    <th data-i18n="admin_col_datetime">التاريخ والوقت</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody id="logTbody">
            <!-- loaded by JS -->
            </tbody>
        </table>
        </div>
        <div class="log-pagination">
            <div class="log-page-info" id="logPageInfo"></div>
            <div class="log-page-btns">
                <button class="btn-page" id="logPrevBtn" onclick="logGoPage(-1)" data-i18n="admin_prev">← السابق</button>
                <span id="logPageNums" style="display:flex;gap:4px;"></span>
                <button class="btn-page" id="logNextBtn" onclick="logGoPage(1)" data-i18n="admin_next">التالي →</button>
            </div>
        </div>
    </div>

    <!-- ── Recent Users (full-width) ── -->
    <div class="admin-section">
        <div class="admin-section-head">
            <h2 class="admin-section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span data-i18n="admin_recent_users">آخر المستخدمين المسجلين</span>
            </h2>
            <span class="admin-count-badge"><span id="totalUserNum"><?= $total_users ?></span> <span data-i18n="admin_user_label">مستخدم</span></span>
        </div>
        <div style="overflow-x:auto;">
        <table class="user-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th data-i18n="admin_col_name">الاسم</th>
                    <th data-i18n="email_label">البريد الإلكتروني</th>
                    <th data-i18n="admin_col_reg_date">تاريخ التسجيل</th>
                    <th data-i18n="admin_col_role">الصلاحية</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recent_users as $u): ?>
            <tr id="user-row-<?= (int)$u['id'] ?>">
                <td style="color:var(--text-muted);font-size:12px;"><?= (int)$u['id'] ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($u['name']) ?></td>
                <td style="color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></td>
                <td style="color:var(--text-muted);font-size:12px;white-space:nowrap;"><?= date('Y/m/d', strtotime($u['created_at'])) ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <?php if ($u['role'] === 'super_admin'): ?>
                        <span class="super-chip" data-i18n="admin_role_super_admin">مدير أعلى</span>
                    <?php elseif ($u['is_admin']): ?>
                        <span class="admin-chip" data-i18n="admin_role_admin">مدير</span>
                    <?php else: ?>
                        <span style="font-size:12px;color:var(--text-muted);" data-i18n="admin_role_user">مستخدم</span>
                    <?php endif; ?>
                    <?php if ($is_super_admin && !$u['is_admin'] && $u['role'] !== 'super_admin'): ?>
                        <button class="btn-promote" onclick="promoteToAdmin(<?= (int)$u['id'] ?>, this)" data-i18n="admin_promote_btn">ترقية لمدير</button>
                    <?php endif; ?>
                    <?php if ($is_super_admin && $u['is_admin'] && $u['role'] !== 'super_admin'): ?>
                        <button class="btn-demote" onclick="demoteToUser(<?= (int)$u['id'] ?>, this)" data-i18n="admin_demote_btn">إزالة الصلاحية</button>
                    <?php endif; ?>
                    <button class="btn-reset-pw" onclick="resetUserPassword(<?= (int)$u['id'] ?>, <?= json_encode($u['email']) ?>, this)" data-i18n="admin_reset_pw">🔑 إعادة كلمة المرور</button>
                    <button class="btn-send-email" onclick="openCustomEmailModal(<?= (int)$u['id'] ?>, <?= json_encode($u['name']) ?>, <?= json_encode($u['email']) ?>)" data-i18n="admin_send_email">✉️ إرسال بريد</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

</div>

<!-- ── Custom Email Modal — outside page wrapper to avoid stacking context ── -->
<div class="email-modal-overlay" id="customEmailModal" onclick="if(event.target===this)closeCustomEmailModal()">
    <div class="email-modal">
        <h3 class="email-modal-title" data-i18n="admin_custom_email_title">إرسال بريد مخصص</h3>
        <input type="hidden" id="cEmailUserId">
        <p style="font-size:13px;color:var(--text-muted);margin:0 0 12px;">
            <span data-i18n="admin_to">إلى:</span> <strong id="cEmailTarget"></strong>
        </p>
        <input type="text" id="cEmailSubject" placeholder="الموضوع" data-i18n-placeholder="admin_email_subject_ph">
        <textarea id="cEmailBody" placeholder="نص البريد الإلكتروني..."></textarea>
        <div class="email-modal-btns">
            <button class="btn-cancel-reply" onclick="closeCustomEmailModal()" data-i18n="admin_cancel">إلغاء</button>
            <button class="btn-reply-send" id="cEmailSendBtn" onclick="submitCustomEmail()" data-i18n="admin_send_email_btn">✉️ إرسال</button>
        </div>
    </div>
</div>

<script src="/assets/js/main.js"></script>
<script>
// ── Inline reply form helpers ─────────────────────────────────
function showReplyForm(id) {
    document.getElementById('reply-btn-' + id).style.display  = 'none';
    document.getElementById('reply-form-' + id).style.display = '';
    document.getElementById('reply-text-' + id).focus();
}

// Defines the hideReplyForm routine.
function hideReplyForm(id) {
    document.getElementById('reply-form-' + id).style.display = 'none';
    document.getElementById('reply-btn-' + id).style.display  = '';
}

// ── Submit reply and close ticket ────────────────────────────
async function submitReply(ticketId) {
    const replyText = (document.getElementById('reply-text-' + ticketId)?.value || '').trim();
    const sendBtn   = document.querySelector(`#reply-form-${ticketId} .btn-reply-send`);
    const origLabel = sendBtn ? sendBtn.textContent : '';

    if (sendBtn) { sendBtn.disabled = true; sendBtn.textContent = 'جاري الإرسال...'; }

    try {
        const res  = await mizanFetch('/api/admin_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reply_ticket', ticket_id: ticketId, reply: replyText })
        });
        const data = await res.json();

        if (data.success) {
            const statusEl = document.getElementById('status-' + ticketId);
            if (statusEl) { statusEl.className = 't-status t-closed'; statusEl.textContent = 'مغلقة'; }

            const actionCell = document.getElementById('action-' + ticketId);
            if (actionCell) actionCell.innerHTML = `<span class="btn-closed-tag">✓ ${replyText ? 'تم الرد' : 'مغلقة'}</span>`;

            let cnt = parseInt(document.getElementById('openTicketCount').textContent, 10) - 1;
            document.getElementById('openTicketCount').textContent = cnt;
            const openNum = document.getElementById('openBadgeNum');
            if (openNum) openNum.textContent = cnt;

            if (typeof showToast === 'function') showToast('✉️ تم إرسال الرد وإغلاق التذكرة', 'success', 3500);
        } else {
            if (sendBtn) { sendBtn.disabled = false; sendBtn.textContent = origLabel || '↩ إرسال وإغلاق'; }
            if (typeof showToast === 'function') showToast(data.error || 'فشل الإجراء', 'error');
        }
    } catch {
        if (sendBtn) { sendBtn.disabled = false; sendBtn.textContent = origLabel || '↩ إرسال وإغلاق'; }
        if (typeof showToast === 'function') showToast('تعذّر الاتصال بالخادم', 'error');
    }
}

// ── Submit reply WITHOUT closing the ticket ─────────────────
async function submitReplyOpen(ticketId) {
    const replyText = (document.getElementById('reply-text-' + ticketId)?.value || '').trim();
    if (!replyText) {
        if (typeof showToast === 'function') showToast('نص الرد مطلوب', 'warning');
        return;
    }
    const formButtons = document.querySelectorAll(`#reply-form-${ticketId} button`);
    formButtons.forEach(b => b.disabled = true);

    try {
        const res = await mizanFetch('/api/admin_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'admin_reply_open', ticket_id: ticketId, reply: replyText })
        });
        const data = await res.json();

        if (data.success) {
            // Hide reply form, keep status open
            hideReplyForm(ticketId);
            // Append/refresh inline preview row
            const row = document.getElementById('ticket-row-' + ticketId);
            if (row) {
                const msgCell = row.querySelector('.t-msg');
                if (msgCell && !row.querySelector('.prev-reply')) {
                    const prev = document.createElement('div');
                    prev.className = 'prev-reply';
                    prev.textContent = '↩ ' + (replyText.length > 100 ? replyText.slice(0, 100) + '...' : replyText);
                    msgCell.parentNode.appendChild(prev);
                }
            }
            if (typeof showToast === 'function') showToast('تم إرسال الرد — التذكرة لا تزال مفتوحة', 'success', 3500);
        } else {
            if (typeof showToast === 'function') showToast(data.error || 'فشل الإجراء', 'error');
            formButtons.forEach(b => b.disabled = false);
        }
    } catch {
        if (typeof showToast === 'function') showToast('تعذّر الاتصال بالخادم', 'error');
        formButtons.forEach(b => b.disabled = false);
    }
}

// ── Broadcast live preview (T6) ───────────────────────────────
function updateBroadcastPreview() {
    const preview  = document.getElementById('annPreview');
    const prevText = document.getElementById('annPreviewText');
    const arText   = (document.getElementById('annAr')?.value || '').trim();
    const bg       = document.getElementById('annBgColor')?.value || '#1e3a5f';
    const tc       = document.getElementById('annTextColor')?.value || '#ffffff';
    const fs       = parseInt(document.getElementById('annFontSize')?.value || '14', 10);
    if (preview) { preview.style.background = bg; preview.style.color = tc; preview.style.fontSize = fs + 'px'; }
    if (prevText) prevText.textContent = arText || (window.t ? window.t('admin_ann_preview_placeholder') : 'اكتب النص لتظهر المعاينة...');
}

// ── Broadcast ─────────────────────────────────────────────────
async function sendBroadcast() {
    const arText = document.getElementById('annAr').value.trim();
    const enText = document.getElementById('annEn').value.trim();
    if (!arText) { if (typeof showToast === 'function') showToast('النص بالعربية مطلوب', 'warning'); return; }

    const payload = {
        action:     'broadcast',
        message_ar: arText,
        message_en: enText,
        bg_color:   document.getElementById('annBgColor')?.value || '',
        text_color: document.getElementById('annTextColor')?.value || '',
        font_size:  parseInt(document.getElementById('annFontSize')?.value || '14', 10),
    };

    try {
        const res  = await mizanFetch('/api/admin_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('annAr').value = '';
            document.getElementById('annEn').value = '';
            updateBroadcastPreview();
            const annSt = document.getElementById('annStatus');
            if (annSt) { annSt.dataset.i18n = 'admin_ann_active'; annSt.textContent = window.t ? window.t('admin_ann_active') : 'نشط'; }
            const box = document.getElementById('currentAnnBox');
            if (box) {
                box.style.display = '';
                box.textContent = '';
                const wrap = document.createElement('div');
                wrap.className = 'broadcast-current';
                const inner = document.createElement('div');
                const lbl = document.createElement('div');
                lbl.className = 'broadcast-current-label';
                lbl.textContent = 'الإعلان الحالي';
                const txt = document.createElement('div');
                txt.className = 'broadcast-current-text';
                txt.textContent = arText;
                inner.appendChild(lbl);
                inner.appendChild(txt);
                const clrBtn = document.createElement('button');
                clrBtn.className = 'btn-clear-broadcast';
                clrBtn.textContent = '× إلغاء';
                clrBtn.onclick = function() { clearBroadcast(clrBtn); };
                wrap.appendChild(inner);
                wrap.appendChild(clrBtn);
                box.appendChild(wrap);
            }
            if (typeof showToast === 'function') showToast('تم نشر الإعلان', 'success');
        } else {
            if (typeof showToast === 'function') showToast(data.error || 'فشل النشر', 'error');
        }
    } catch { if (typeof showToast === 'function') showToast('تعذّر الاتصال', 'error'); }
}

async function clearBroadcast(btn) {
    const isAr = (localStorage.getItem('mizan_lang') || 'ar') === 'ar';
    const ok = await confirmDestructiveAction({
        title:       isAr ? 'إلغاء الإعلان الحالي؟'                         : 'Cancel current announcement?',
        text:        isAr ? 'سيختفي الإعلان فوراً لجميع المستخدمين.'        : 'The announcement will disappear immediately for all users.',
        confirmText: isAr ? 'نعم، ألغِه'                                     : 'Yes, cancel it',
        icon:        'warning',
    });
    if (!ok) return;

    const data = await withLoading(btn || null, async () => {
        const res = await mizanFetch('/api/admin_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'clear_broadcast' })
        });
        return res.json();
    }, {
        loadingLabel:   isAr ? 'جاري الإلغاء...' : 'Cancelling...',
        successMessage: isAr ? 'تم إلغاء الإعلان' : 'Announcement cancelled',
    });

    if (!data?.success) return;
    document.getElementById('currentAnnBox').style.display = 'none';
    const annSt2 = document.getElementById('annStatus');
    if (annSt2) { annSt2.dataset.i18n = 'admin_ann_none'; annSt2.textContent = window.t ? window.t('admin_ann_none') : 'لا يوجد'; }
}

// ── Promote user to admin (super_admin only) ──────────────────
async function promoteToAdmin(userId, btn) {
    const isAr = (localStorage.getItem('mizan_lang') || 'ar') === 'ar';
    const ok = await confirmDestructiveAction({
        title:       isAr ? 'ترقية هذا المستخدم إلى مدير؟'             : 'Promote this user to admin?',
        text:        isAr ? 'سيتم منحه صلاحيات الإدارة الكاملة.'       : 'They will be granted full admin permissions.',
        confirmText: isAr ? 'نعم، ارقِّه'                               : 'Yes, promote',
        icon:        'question',
    });
    if (!ok) return;

    const data = await withLoading(btn, async () => {
        const res = await mizanFetch('/api/admin_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'promote_user', user_id: userId }),
        });
        return res.json();
    }, {
        loadingLabel:   isAr ? 'جاري الترقية...' : 'Promoting...',
        successMessage: isAr ? 'تمت ترقية المستخدم بنجاح' : 'User promoted successfully',
    });

    if (!data?.success) return;
    const row = document.getElementById('user-row-' + userId);
    if (!row) return;
    const roleCell = row.querySelector('td:last-child div');
    if (!roleCell) return;
    const chip = document.createElement('span');
    chip.className = 'admin-chip';
    chip.dataset.i18n = 'admin_role_admin';
    chip.textContent = window.t ? window.t('admin_role_admin') : 'مدير';
    const demoteBtn = document.createElement('button');
    demoteBtn.className = 'btn-demote';
    demoteBtn.dataset.i18n = 'admin_demote_btn';
    demoteBtn.textContent = window.t ? window.t('admin_demote_btn') : 'إزالة الصلاحية';
    demoteBtn.onclick = function() { demoteToUser(userId, demoteBtn); };
    roleCell.replaceChildren(chip, demoteBtn);
}

// ── Demote admin back to user (super_admin only) ──────────────
async function demoteToUser(userId, btn) {
    const isAr = (localStorage.getItem('mizan_lang') || 'ar') === 'ar';
    const ok = await confirmDestructiveAction({
        title:       isAr ? 'إزالة صلاحية المدير؟'                       : 'Remove admin role?',
        text:        isAr ? 'سيعود المستخدم إلى دور مستخدم عادي.'        : 'The user will be reverted to a regular role.',
        confirmText: isAr ? 'نعم، أزِل الصلاحية'                         : 'Yes, remove',
        icon:        'warning',
    });
    if (!ok) return;

    const data = await withLoading(btn, async () => {
        const res = await mizanFetch('/api/admin_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'demote_user', user_id: userId }),
        });
        return res.json();
    }, {
        loadingLabel:   isAr ? 'جاري الإزالة...' : 'Removing...',
        successMessage: isAr ? 'تمت إزالة صلاحية المدير' : 'Admin role removed',
    });

    if (!data?.success) return;
    const row = document.getElementById('user-row-' + userId);
    if (!row) return;
    const roleCell = row.querySelector('td:last-child div');
    if (!roleCell) return;
    const txt = document.createElement('span');
    txt.style.cssText = 'font-size:12px;color:var(--text-muted);';
    txt.dataset.i18n = 'admin_role_user';
    txt.textContent = window.t ? window.t('admin_role_user') : 'مستخدم';
    const promoteBtn = document.createElement('button');
    promoteBtn.className = 'btn-promote';
    promoteBtn.dataset.i18n = 'admin_promote_btn';
    promoteBtn.textContent = window.t ? window.t('admin_promote_btn') : 'ترقية لمدير';
    promoteBtn.onclick = function() { promoteToAdmin(userId, promoteBtn); };
    roleCell.replaceChildren(txt, promoteBtn);
}

// ── Activity log filters ───────────────────────────────────────
const _logLabelsFallback = {
    user_login:'تسجيل دخول', user_logout:'تسجيل خروج',
    create_project:'إنشاء مشروع', export_data:'تصدير بيانات'
};
// Logs the label.
function _logLabel(key) {
    if (window.t) {
        const v = window.t('log_' + key);
        if (v && v !== 'log_' + key) return v;
    }
    return _logLabelsFallback[key] || key;
}

// Renders the log rows.
function renderLogRows(logs) {
    const tbody = document.getElementById('logTbody');
    if (!tbody) return;
    tbody.textContent = '';

    if (!logs.length) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 5;
        td.textContent = 'لا توجد سجلات تطابق الفلتر';
        td.style.cssText = 'text-align:center;padding:24px;color:var(--text-muted);';
        tr.appendChild(td);
        tbody.appendChild(tr);
        return;
    }

    for (const l of logs) {
        const tr = document.createElement('tr');

        const tdName = document.createElement('td');
        const nameDiv = document.createElement('div');
        nameDiv.style.fontWeight = '600';
        nameDiv.textContent = l.name;
        const emailDiv = document.createElement('div');
        emailDiv.style.cssText = 'font-size:11px;color:var(--text-muted);';
        emailDiv.textContent = l.email;
        tdName.appendChild(nameDiv);
        tdName.appendChild(emailDiv);
        tr.appendChild(tdName);

        const tdAction = document.createElement('td');
        tdAction.dataset.action = l.action_type;
        tdAction.textContent = _logLabel(l.action_type);
        tr.appendChild(tdAction);

        const tdDetails = document.createElement('td');
        tdDetails.textContent = l.details || '—';
        tdDetails.style.cssText = 'color:var(--text-muted);max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
        tr.appendChild(tdDetails);

        const tdDate = document.createElement('td');
        tdDate.textContent = l.created_at;
        tdDate.style.cssText = 'white-space:nowrap;color:var(--text-muted);font-size:12px;';
        tr.appendChild(tdDate);

        const tdIp = document.createElement('td');
        tdIp.textContent = l.ip_address || '—';
        tdIp.style.cssText = 'font-size:12px;color:var(--text-muted);';
        tr.appendChild(tdIp);

        tbody.appendChild(tr);
    }
}

// ── Log pagination (T7) ───────────────────────────────────────
let _logPage    = 1;
let _logPerPage = <?= $logs_per_page ?>;
let _logTotal   = <?= $logs_total ?>;

// Logs the get per page.
function logGetPerPage() {
    return parseInt(document.getElementById('logPerPage')?.value ?? '', 10) || _logPerPage;
}

// Logs the change per page.
function logChangePerPage() {
    _logPage = 1;
    loadLogPage();
}

// Logs the go page.
function logGoPage(delta) {
    const perPage = logGetPerPage();
    const maxPage = Math.max(1, Math.ceil(_logTotal / perPage));
    const newPage = Math.min(maxPage, Math.max(1, _logPage + delta));
    if (newPage === _logPage) return;
    _logPage = newPage;
    loadLogPage();
}

// Logs the jump page.
function logJumpPage(p) {
    _logPage = p;
    loadLogPage();
}

// Renders the log pagination.
function renderLogPagination() {
    const perPage = logGetPerPage();
    const maxPage = Math.max(1, Math.ceil(_logTotal / perPage));
    const info    = document.getElementById('logPageInfo');
    const nums    = document.getElementById('logPageNums');
    const prev    = document.getElementById('logPrevBtn');
    const next    = document.getElementById('logNextBtn');

    if (info) {
        const from = (_logPage - 1) * perPage + 1;
        const to   = Math.min(_logPage * perPage, _logTotal);
        info.textContent = from + '–' + to + ' / ' + _logTotal;
    }
    if (prev) prev.disabled = _logPage <= 1;
    if (next) next.disabled = _logPage >= maxPage;

    if (nums) {
        nums.textContent = '';
        const range = [];
        for (let i = Math.max(1, _logPage - 2); i <= Math.min(maxPage, _logPage + 2); i++) range.push(i);
        range.forEach(p => {
            const btn = document.createElement('button');
            btn.className = 'btn-page' + (p === _logPage ? ' active' : '');
            btn.textContent = p;
            btn.onclick = () => logJumpPage(p);
            nums.appendChild(btn);
        });
    }
}

async function loadLogPage(userId, date) {
    const perPage = logGetPerPage();
    const params = new URLSearchParams({ action: 'get_logs', limit: perPage, offset: (_logPage - 1) * perPage });
    const uid = userId ?? document.getElementById('logUserFilter')?.value ?? '';
    const dt  = date  ?? document.getElementById('logDateFilter')?.value ?? '';
    if (uid) params.set('user_id', uid);
    if (dt)  params.set('date', dt);

    const applyBtn = document.querySelector('.btn-filter-apply');
    if (applyBtn) setButtonBusy(applyBtn, true);

    // Show a loading row while the network request is in-flight
    const tbody = document.getElementById('logTbody');
    if (tbody) {
        tbody.textContent = '';
        const skRow = document.createElement('tr');
        const skCell = document.createElement('td');
        skCell.colSpan = 5;
        skCell.style.cssText = 'text-align:center;padding:28px;color:var(--text-muted);font-size:13px;';
        skCell.textContent = '⏳ ' + (window.t ? window.t('loading') : 'جاري التحميل...');
        skRow.appendChild(skCell);
        tbody.appendChild(skRow);
    }

    try {
        const res  = await mizanFetch('/api/admin_actions.php?' + params.toString());
        const data = await res.json();
        if (data.success) {
            if (data.total !== undefined) _logTotal = data.total;
            renderLogRows(data.logs);
            renderLogPagination();
        } else {
            showToast(data.error || 'فشل التصفية', 'error');
        }
    } catch {
        showToast('تعذّر الاتصال بالخادم', 'error');
    } finally {
        if (applyBtn) {
            setButtonBusy(applyBtn, false);
            applyBtn.textContent = window.t ? window.t('admin_filter_apply') : 'تطبيق';
        }
    }
}

async function applyLogFilters() {
    _logPage = 1;
    await loadLogPage();
}

// Resets the log filters.
function resetLogFilters() {
    document.getElementById('logUserFilter').value = '';
    if (window._logDatePicker) window._logDatePicker.clear();
    _logPage = 1;
    loadLogPage();
}

// ── CSV export — sync lang param ─────────────────────────────
(function () {
    // Updates the export href.
    function updateExportHref() {
        const btn = document.getElementById('exportLogsBtn');
        if (!btn) return;
        btn.href = '/api/export_logs.php?lang=' + (localStorage.getItem('mizan_lang') || 'ar');
    }
    updateExportHref();
    window.addEventListener('mizan:lang-change', updateExportHref);
})();

// ── Re-translate static log action cells on lang change ───────
window.addEventListener('mizan:lang-change', function () {
    document.querySelectorAll('#logTbody td[data-action]').forEach(function (td) {
        td.textContent = _logLabel(td.dataset.action);
    });
});

// ── Chart.js initialization (T4) ─────────────────────────────
(function initCharts() {
    const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';
    const textColor = () => isDark() ? '#94a3b8' : '#64748b';
    const gridColor = () => isDark() ? 'rgba(255,255,255,.07)' : 'rgba(0,0,0,.06)';

    const rated   = <?= $rated_count ?>;
    const unrated = <?= $unrated_count ?>;
    const stars   = [<?= implode(',', array_values($star_dist)) ?>];
    const projLabels = ['شخصي', 'تجاري'];
    const projData   = [<?= $proj_personal ?>, <?= $proj_commercial ?>];

    // Builds the chart.
    function makeChart(id, config) {
        const el = document.getElementById(id);
        if (!el) return;
        return new Chart(el.getContext('2d'), config);
    }

    // Ratings distribution doughnut
    makeChart('chartRatingsDist', {
        type: 'doughnut',
        data: {
            labels: ['قيّموا', 'لم يقيّموا'],
            datasets: [{ data: [rated, unrated], backgroundColor: ['#3b82f6','#e2e8f0'], borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { color: textColor(), font: { family: 'Tajawal' } } } }
        }
    });

    // Satisfaction bar
    makeChart('chartStars', {
        type: 'bar',
        data: {
            labels: ['⭐', '⭐⭐', '⭐⭐⭐', '⭐⭐⭐⭐', '⭐⭐⭐⭐⭐'],
            datasets: [{ data: stars, backgroundColor: ['#ef4444','#f97316','#f59e0b','#84cc16','#22c55e'], borderRadius: 4, borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { ticks: { color: textColor() }, grid: { color: gridColor() } },
                      y: { ticks: { color: textColor(), stepSize: 1 }, grid: { color: gridColor() }, beginAtZero: true } }
        }
    });

    // Projects doughnut
    makeChart('chartProjects', {
        type: 'doughnut',
        data: {
            labels: projLabels,
            datasets: [{ data: projData, backgroundColor: ['#6366f1','#f59e0b'], borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { color: textColor(), font: { family: 'Tajawal' } } } }
        }
    });

    // Monthly expense trend — line chart (initiative)
    const mLabels = <?= json_encode(array_column($monthly_expenses, 'lbl')) ?>;
    const mData   = <?= json_encode(array_map('floatval', array_column($monthly_expenses, 'total'))) ?>;
    makeChart('chartMonthly', {
        type: 'line',
        data: {
            labels: mLabels,
            datasets: [{
                label: 'المصاريف الشهرية',
                data: mData,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59,130,246,.10)',
                fill: true,
                tension: 0.42,
                pointBackgroundColor: '#3b82f6',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ' + Number(ctx.parsed.y).toLocaleString('ar-SA') + ' ' + getCurrencySymbol()
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: gridColor() },
                    ticks: { color: textColor(), font: { family: 'Tajawal', size: 11 },
                             callback: v => Number(v).toLocaleString('ar-SA') }
                },
                x: {
                    grid: { color: gridColor() },
                    ticks: { color: textColor(), font: { family: 'Tajawal', size: 10 }, maxRotation: 30 }
                }
            }
        }
    });
})();

// ── Reset user password (T5) ──────────────────────────────────
async function resetUserPassword(userId, email, btn = null) {
    const isAr = (localStorage.getItem('mizan_lang') || 'ar') === 'ar';
    const ok = await confirmDestructiveAction({
        title:       isAr ? `إعادة تعيين كلمة مرور ${email}؟`                     : `Reset password for ${email}?`,
        text:        isAr ? 'ستصله كلمة مرور جديدة عشوائية عبر البريد الإلكتروني.' : 'A new random password will be emailed to them.',
        confirmText: isAr ? 'نعم، أعِد التعيين'                                     : 'Yes, reset it',
        icon:        'question',
    });
    if (!ok) return;

    await withLoading(btn, async () => {
        const res = await mizanFetch('/api/admin_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reset_user_password', user_id: userId }),
        });
        return res.json();
    }, {
        loadingLabel:   isAr ? 'جاري الإرسال...' : 'Sending...',
        successMessage: isAr ? 'تم إرسال كلمة المرور الجديدة بالبريد' : 'New password emailed',
    });
}

// ── Custom email modal (T5) ───────────────────────────────────
function openCustomEmailModal(userId, name, email) {
    document.getElementById('cEmailUserId').value = userId;
    const target = document.getElementById('cEmailTarget');
    if (target) target.textContent = name + ' <' + email + '>';
    document.getElementById('cEmailSubject').value = '';
    document.getElementById('cEmailBody').value = '';
    document.getElementById('customEmailModal').classList.add('open');
}

// Closes the custom email modal.
function closeCustomEmailModal() {
    document.getElementById('customEmailModal').classList.remove('open');
}

async function submitCustomEmail() {
    const isAr    = (localStorage.getItem('mizan_lang') || 'ar') === 'ar';
    const userId  = document.getElementById('cEmailUserId').value;
    const subject = document.getElementById('cEmailSubject').value.trim();
    const body    = document.getElementById('cEmailBody').value.trim();
    if (!subject || !body) {
        showToast(isAr ? 'الموضوع والنص مطلوبان' : 'Subject and body are required', 'warning');
        return;
    }

    const data = await withLoading(document.getElementById('cEmailSendBtn'), async () => {
        const res = await mizanFetch('/api/admin_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'send_custom_email', user_id: userId, subject, body }),
        });
        return res.json();
    }, {
        loadingLabel:   isAr ? 'جاري الإرسال...' : 'Sending...',
        successMessage: isAr ? 'تم الإرسال بنجاح' : 'Email sent successfully',
    });

    if (data?.success) closeCustomEmailModal();
}

// ── Email Template Tester (T8) ────────────────────────────────
async function sendTestEmail() {
    const isAr   = (localStorage.getItem('mizan_lang') || 'ar') === 'ar';
    const tpl    = document.getElementById('etTemplate').value;
    const lang   = document.getElementById('etLang').value;
    const target = document.getElementById('etTarget').value.trim();
    if (!target) {
        showToast(isAr ? 'البريد المستهدف مطلوب' : 'Target email is required', 'warning');
        return;
    }

    await withLoading(document.getElementById('etSendBtn'), async () => {
        const res = await mizanFetch('/api/admin_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'send_test_email', template: tpl, lang, target }),
        });
        return res.json();
    }, {
        loadingLabel:   isAr ? 'جاري الإرسال...' : 'Sending...',
        successMessage: isAr ? 'تم الإرسال — تحقق من صندوق البريد' : 'Sent — check the inbox',
    });
}

// ── Flatpickr init for log date filter ────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    if (typeof flatpickr === 'function') {
        window._logDatePicker = flatpickr('#logDateFilter', {
            locale: document.documentElement.lang === 'ar' ? 'ar' : 'default',
            dateFormat: 'Y-m-d',
            allowInput: false,
            disableMobile: true
        });
    }
    loadLogPage();
    updateBroadcastPreview();
});

// ── Background canvas ─────────────────────────────────────────
(function() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const cv  = document.getElementById('admin-bg');
    const ctx = cv.getContext('2d');
    let pts   = [];
    const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';

    // Defines the resize routine.
    function resize() { cv.width = innerWidth; cv.height = innerHeight; }

    // Defines the spawn routine.
    function spawn() {
        pts = [];
        const n = Math.max(16, Math.floor((cv.width * cv.height) / 22000));
        for (let i = 0; i < n; i++) {
            pts.push({ x:Math.random()*cv.width, y:Math.random()*cv.height,
                vx:(Math.random()-.5)*.35, vy:(Math.random()-.5)*.35, r:Math.random()*1.5+.6 });
        }
    }

    let raf;
    // Renders.
    function draw() {
        ctx.clearRect(0,0,cv.width,cv.height);
        const base = isDark() ? '52,211,153' : '29,30,82';
        const MD   = 95;
        for (let i = 0; i < pts.length; i++) {
            const p = pts[i];
            p.x += p.vx; p.y += p.vy;
            if (p.x<0||p.x>cv.width)  p.vx*=-1;
            if (p.y<0||p.y>cv.height) p.vy*=-1;
            ctx.beginPath(); ctx.arc(p.x,p.y,p.r,0,Math.PI*2);
            ctx.fillStyle=`rgba(${base},.38)`; ctx.fill();
            for (let j=i+1; j<pts.length; j++) {
                const q=pts[j], dx=p.x-q.x, dy=p.y-q.y, d=Math.hypot(dx,dy);
                if (d<MD) {
                    ctx.beginPath(); ctx.moveTo(p.x,p.y); ctx.lineTo(q.x,q.y);
                    ctx.strokeStyle=`rgba(${base},${(.11*(1-d/MD)).toFixed(3)})`;
                    ctx.lineWidth=.65; ctx.stroke();
                }
            }
        }
        raf = requestAnimationFrame(draw);
    }
    resize(); spawn(); draw();
    window.addEventListener('resize', ()=>{ cancelAnimationFrame(raf); resize(); spawn(); draw(); });
})();
</script>
</body>
</html>
