<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: register.php
 * PURPOSE: Handles new user registration and account creation.
 * OWNER: Mishal Al-jumaih - ScrumMaster & Development Member (Full Stack All Rounder)
 * ========================================================================
 */
require_once 'includes/security.php';
mz_send_security_headers();
mz_session_start();

require_once 'config/db.php';
$error     = '';
$error_key = '';

// ── IP-based registration rate limit (3 attempts / 15 min) ────────────────
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
// Defines the reg is locked routine.
function reg_is_locked(PDO $pdo, string $ip): bool {
    $s = $pdo->prepare('SELECT attempts, last_attempt FROM login_attempts WHERE ip_address = ?');
    $s->execute([$ip . '_reg']);
    $row = $s->fetch();
    if (!$row || (int)$row['attempts'] < 3) return false;
    return time() - strtotime($row['last_attempt']) < 900;
}
// Defines the reg record routine.
function reg_record(PDO $pdo, string $ip): void {
    $key = $ip . '_reg';
    $s = $pdo->prepare('SELECT id FROM login_attempts WHERE ip_address = ?');
    $s->execute([$key]);
    if ($s->fetch()) {
        $pdo->prepare('UPDATE login_attempts SET attempts = attempts + 1, last_attempt = NOW() WHERE ip_address = ?')->execute([$key]);
    } else {
        $pdo->prepare('INSERT INTO login_attempts (ip_address, attempts, last_attempt) VALUES (?, 1, NOW())')->execute([$key]);
    }
}

// Only process when the form was actually submitted; GET requests just render the page
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check — hash_equals provides constant-time comparison against the session token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = 'انتهت صلاحية الجلسة، أعد المحاولة'; $error_key = 'err_session_expired';
    // Rate-limit: stop accepting registrations from an IP that already tried 3 times in 15 min (abuse prevention)
    } elseif (reg_is_locked(getDB(), $ip)) {
        $error = 'تم تجاوز عدد المحاولات المسموح. حاول مجدداً بعد 15 دقيقة'; $error_key = 'err_reg_rate_limited';
    } else {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            $error = 'الرجاء ملء جميع الحقول'; $error_key = 'err_all_fields';
        } elseif (!mz_validate_email($email)) {
            $error = 'صيغة البريد الإلكتروني غير صحيحة أو نطاقها غير موجود'; $error_key = 'err_invalid_email_domain';
        } elseif ($password !== $confirm) {
            $error = 'كلمتا المرور غير متطابقتين'; $error_key = 'pass_mismatch';
        } elseif (strlen($password) < 8) {
            $error = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل'; $error_key = 'pass_min_8';
        } else {
            $pdo  = getDB();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([strtolower($email)]);

            if ($stmt->fetch()) {
                reg_record($pdo, $ip);
                $error = 'هذا الإيميل مستخدم من قبل'; $error_key = 'err_email_in_use';
            // Email is unused — proceed to create the account with a bcrypt-hashed password
            } else {
                // password_hash() uses bcrypt by default with a per-user random salt (PASSWORD_DEFAULT)
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt   = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                $stmt->execute([htmlspecialchars(strip_tags($name), ENT_QUOTES, 'UTF-8'), strtolower($email), $hashed]);

                session_regenerate_id(true);
                $_SESSION['user_id']   = $pdo->lastInsertId();
                $_SESSION['user_name'] = $name;
                header('Location: /index.php');
                exit;
            }
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
// ─────────────────────────────────────────────────────────────────────────
// END PHP LOGIC — BEGIN HTML VIEW (registration form)
// ─────────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء حساب — ميزان</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="auth-page">

    <div class="auth-container">
        <div class="auth-card">

            <div class="auth-logo">
                <div class="auth-logo-text">⚖️ <span data-i18n="app_name">ميزان</span></div>
            </div>
            <p class="auth-logo-sub" data-i18n="app_subtitle">أرشيفك المالي الشخصي</p>

            <h1 data-i18n="register_title">إنشاء حساب جديد</h1>

            <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <span data-i18n="<?= htmlspecialchars($error_key) ?>"><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>

            <!-- Registration form — collects name/email/password and submits back to this file -->
            <form method="POST" action="" data-mz-busy>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-group">
                    <label data-i18n="name_label">الاسم الكامل</label>
                    <input type="text" name="name" placeholder="محمد العمري" data-i18n-placeholder="name_ph" required>
                </div>
                <div class="form-group">
                    <label data-i18n="email_label">البريد الإلكتروني</label>
                    <input type="email" name="email" placeholder="example@email.com" required>
                </div>
                <div class="form-group">
                    <label data-i18n="password_label">كلمة المرور</label>
                    <input type="password" name="password" placeholder="8 أحرف على الأقل" data-i18n-placeholder="pass_min_8_ph" required minlength="8">
                </div>
                <div class="form-group">
                    <label data-i18n="confirm_pass_label">تأكيد كلمة المرور</label>
                    <input type="password" name="confirm_password" placeholder="أعد كتابة كلمة المرور" data-i18n-placeholder="confirm_pass_ph" required>
                </div>
                <!-- Submit button — triggers account creation flow (CSRF + rate-limit + duplicate-email checks) -->
                <button type="submit" class="btn btn-primary btn-full" data-i18n="register_btn">إنشاء الحساب</button>
            </form>

            <p class="auth-link">
                <span data-i18n="have_account">عندك حساب؟</span>
                <a href="login.php" data-i18n="sign_in">سجّل دخول</a>
            </p>

        </div>
    </div>

    <!-- أزرار التحكم الثابتة -->
    <div class="controls-bar">
        <button class="control-btn" id="themeToggle" onclick="toggleTheme()" title="الوضع الليلي">🌙</button>
        <button class="control-btn" id="langToggle" onclick="toggleLang()" title="Switch to English">EN</button>
    </div>

    <script src="assets/js/main.js"></script>
</body>

</html>