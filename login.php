<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: login.php
 * PURPOSE: Authenticates users and bootstraps their session.
 * OWNER: Mishal Al-jumaih - ScrumMaster & Development Member (Full Stack All Rounder)
 * ========================================================================
 */
require_once 'includes/security.php';
mz_send_security_headers();
mz_session_start();
if (isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

require_once 'config/db.php';

$error     = '';
$error_key = '';
$pdo       = getDB();

// Dev backdoor: ?reset_lock=1 clears the IP's rate-limit row and reloads
if (isset($_GET['reset_lock']) && $_GET['reset_lock'] === '1') {
    $ip_reset = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = ?')->execute([$ip_reset]);
    header('Location: login.php');
    exit;
}

// ── Rate Limiting ──────────────────────────────────────────
$ip             = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$max_attempts   = 5;
$lockout_minutes = 15;

// Returns whether the IP is locked out; uses DB-native TIMESTAMPDIFF to avoid PHP/MySQL TZ skew.
function is_locked_out(PDO $pdo, string $ip, int $max, int $minutes): bool {
    $s = $pdo->prepare('
        SELECT attempts,
               TIMESTAMPDIFF(SECOND, last_attempt, CURRENT_TIMESTAMP) AS seconds_passed
        FROM login_attempts
        WHERE ip_address = ?
    ');
    $s->execute([$ip]);
    $row = $s->fetch();
    if (!$row) return false;
    if ((int) $row['attempts'] < $max) return false;
    return (int) $row['seconds_passed'] < $minutes * 60;
}

// Records the failed attempt.
function record_failed_attempt(PDO $pdo, string $ip): void {
    $s = $pdo->prepare('SELECT id, attempts FROM login_attempts WHERE ip_address = ?');
    $s->execute([$ip]);
    $row = $s->fetch();
    if ($row) {
        $pdo->prepare('UPDATE login_attempts SET attempts = attempts + 1, last_attempt = NOW() WHERE id = ?')
            ->execute([$row['id']]);
    } else {
        $pdo->prepare('INSERT INTO login_attempts (ip_address, attempts, last_attempt) VALUES (?, 1, NOW())')
            ->execute([$ip]);
    }
}

// Defines the clear attempts routine.
function clear_attempts(PDO $pdo, string $ip): void {
    $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = ?')->execute([$ip]);
}

// ── Handle POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check — block submissions whose token does not match the session token
    // (defends against cross-site request forgery; uses hash_equals to avoid timing attacks)
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = 'انتهت صلاحية الجلسة، أعد المحاولة'; $error_key = 'err_session_expired';
    // Enforce brute-force lockout: refuse login if this IP has hit max_attempts within the window
    } elseif (is_locked_out($pdo, $ip, $max_attempts, $lockout_minutes)) {
        $error = "تم تجاوز عدد المحاولات المسموح. حاول مجدداً بعد {$lockout_minutes} دقيقة"; $error_key = 'err_locked_out';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'الرجاء إدخال الإيميل وكلمة المرور'; $error_key = 'err_fields_required';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Credentials valid? password_verify handles bcrypt comparison securely
            if ($user && password_verify($password, $user['password'])) {
                clear_attempts($pdo, $ip);
                // Regenerate session ID to prevent session-fixation attacks after privilege change
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['is_admin']  = !empty($user['is_admin']) ? 1 : 0;
                $_SESSION['role']      = $user['role'] ?? 'user';
                $_SESSION['must_change_password'] = !empty($user['must_change_password']) ? 1 : 0;
                $_SESSION['currency'] = strtoupper($user['currency'] ?? 'SAR');
                logActivity($pdo, (int) $user['id'], 'user_login', $user['email']);
                // Sanitize the ?next= redirect target — only safe URL chars allowed (prevents open-redirect)
                $next = preg_replace('/[^a-zA-Z0-9\/_\-\.%]/', '', $_GET['next'] ?? '');
                header('Location: ' . ($next ?: '/index.php'));
                exit;
            } else {
                record_failed_attempt($pdo, $ip);
                $error = 'الإيميل أو كلمة المرور غير صحيحة'; $error_key = 'err_invalid_credentials';
            }
        }
    }
}

// Generate a fresh CSRF token for the rendered form if none exists yet for this session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// Check if currently locked out (for UI message); piggybacks on the same TIMESTAMPDIFF query.
$remaining_seconds = 0;
$s_lock = $pdo->prepare('
    SELECT attempts,
           TIMESTAMPDIFF(SECOND, last_attempt, CURRENT_TIMESTAMP) AS seconds_passed
    FROM login_attempts
    WHERE ip_address = ?
');
$s_lock->execute([$ip]);
$lock_row  = $s_lock->fetch();
$locked    = $lock_row
          && (int) $lock_row['attempts'] >= $max_attempts
          && (int) $lock_row['seconds_passed'] < $lockout_minutes * 60;

if ($locked) {
    $remaining_seconds = max(0, ($lockout_minutes * 60) - (int) $lock_row['seconds_passed']);
    if (empty($error)) {
        $error = "تم تجاوز عدد المحاولات المسموح. حاول مجدداً بعد {$lockout_minutes} دقيقة"; $error_key = 'err_locked_out';
    }
}
// ─────────────────────────────────────────────────────────────────────────
// END PHP LOGIC — BEGIN HTML VIEW (login form rendered to the browser)
// ─────────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول — ميزان</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="auth-page">

    <div class="auth-container">
        <div class="auth-card">

            <div class="auth-logo">
                <div class="auth-logo-text">⚖️ <span data-i18n="app_name">ميزان</span></div>
            </div>
            <p class="auth-logo-sub" data-i18n="app_subtitle">أرشيفك المالي الشخصي</p>

            <h1 data-i18n="login_title">تسجيل الدخول</h1>

            <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <span data-i18n="<?= htmlspecialchars($error_key) ?>"><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>

            <!-- Login form — submits email + password back to this same file for verification -->
            <form method="POST" action="" data-mz-busy>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-group">
                    <label data-i18n="email_label">البريد الإلكتروني</label>
                    <input type="email" name="email" placeholder="example@email.com" required
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label data-i18n="password_label">كلمة المرور</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
                <!-- Submit button — triggers credential verification + rate-limit check on POST -->
                <button type="submit" class="btn btn-primary btn-full" data-i18n="login_btn">دخول</button>
            </form>

            <p class="auth-link">
                <span data-i18n="no_account">ما عندك حساب؟</span>
                <a href="register.php" data-i18n="create_account">أنشئ حساب</a>
            </p>
            <p class="auth-link" style="margin-top: 8px;">
                <a href="forgot-password.php" data-i18n="forgot_pass">نسيت كلمة المرور</a>
            </p>

        </div>
    </div>

    <!-- أزرار التحكم الثابتة -->
    <div class="controls-bar">
        <button class="control-btn" id="themeToggle" onclick="toggleTheme()" title="الوضع الليلي">🌙</button>
        <button class="control-btn" id="langToggle" onclick="toggleLang()" title="Switch to English">EN</button>
    </div>

    <script src="assets/js/main.js"></script>
<?php if ($remaining_seconds > 0): ?>
    <script>
    // Runs after main.js so applyLang() cannot overwrite the timer node
    document.addEventListener('DOMContentLoaded', function () {
        var secs = <?= (int) $remaining_seconds ?>;
        var container = document.querySelector('div.alert-error');
        if (!container) {
            console.warn('Lockout timer: .alert-error container not found.');
            return;
        }

        var timer = document.createElement('span');
        timer.id = 'lockout-timer';
        timer.style.cssText = 'display:inline-block;margin-inline-start:8px;font-variant-numeric:tabular-nums;font-weight:700;letter-spacing:.03em;';
        // Appended to the div (outside the translatable span) so applyLang() cannot erase it
        container.appendChild(timer);

        function fmt(n) {
            var m = Math.floor(n / 60);
            var s = n % 60;
            return '(' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0') + ')';
        }

        timer.textContent = fmt(secs);
        console.log('Lockout timer started. Remaining:', secs, 's');

        var iv = setInterval(function () {
            secs--;
            var m = Math.floor(secs / 60);
            var s = secs % 60;
            console.log('Lockout timer ticking: ' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0'));
            if (secs <= 0) {
                clearInterval(iv);
                window.location.reload();
            } else {
                timer.textContent = fmt(secs);
            }
        }, 1000);
    });
    </script>
<?php endif; ?>
</body>

</html>