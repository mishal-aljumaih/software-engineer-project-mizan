<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: forgot-password.php
 * PURPOSE: Initiates the password reset email workflow.
 * OWNER: Mishal Al-jumaih - ScrumMaster & Development Member (Full Stack All Rounder)
 * ========================================================================
 */
require_once 'includes/security.php';
mz_send_security_headers();
mz_session_start();
if (isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

require_once 'config/db.php';
require_once 'config/mail.php';

$msg       = '';
$error     = '';
$error_key = '';
$sent      = false;

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check — reject forged submissions
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $error = 'انتهت صلاحية الجلسة، أعد المحاولة'; $error_key = 'err_session_expired';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !mz_validate_email($email)) {
            $error = 'الرجاء إدخال بريد إلكتروني صحيح ونطاقه موجود'; $error_key = 'err_invalid_email_domain';
        } else {
            $pdo  = getDB();
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Only generate a token when the account exists, but the response is identical
            // either way to prevent email-enumeration attacks
            if ($user) {
                // Cryptographically-random reset token (32 bytes / 64 hex chars)
                $token        = bin2hex(random_bytes(32));
                // Store only the SHA-256 hash in DB — even a DB leak cannot reveal usable tokens
                $token_hashed = hash('sha256', $token);
                $expires      = date('Y-m-d H:i:s', time() + 3600);

                $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
                $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")
                    ->execute([$email, $token_hashed, $expires]);

                $reset_link = APP_URL . "/reset-password.php?token=" . urlencode($token);

                $html = mailResetPassword($user['name'] ?? 'المستخدم', $reset_link);
                sendMail($email, 'ميزان — استعادة كلمة المرور', $html);

                $sent = true;
                $msg  = 'تم إرسال رابط الاستعادة إلى بريدك الإلكتروني. تحقق من صندوق الوارد أو مجلد الـ Spam.';

            // Unknown email: still respond with success message so attackers can't probe for accounts
            } else {
                $sent = true;
                $msg  = 'إذا كان البريد مسجلاً لدينا، سيصلك رابط الاستعادة قريباً.';
            }
        }
    }
}
// ─────────────────────────────────────────────────────────────────────────
// END PHP LOGIC — BEGIN HTML VIEW (email-input form + success state)
// ─────────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نسيت كلمة المرور — ميزان</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
    /* ── extra styles specific to this page ── */
    .auth-icon-wrap {
        width: 72px;
        height: 72px;
        background: linear-gradient(135deg, #006C35, #008A43);
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        margin: 0 auto 16px;
        box-shadow: 0 8px 28px rgba(0, 108, 53, .3);
    }

    .success-box {
        background: linear-gradient(135deg, rgba(0, 108, 53, .08), rgba(0, 108, 53, .04));
        border: 1.5px solid rgba(0, 108, 53, .25);
        border-radius: 14px;
        padding: 20px 18px;
        text-align: center;
    }

    .success-box .s-icon {
        font-size: 40px;
        display: block;
        margin-bottom: 10px;
    }

    .success-box p {
        color: var(--txt, #0F172A);
        line-height: 1.75;
        margin: 0;
        font-size: 14px;
    }

    .back-link {
        display: block;
        text-align: center;
        margin-top: 20px;
        color: var(--g, #006C35);
        font-weight: 700;
        text-decoration: none;
        font-size: 14px;
    }

    .back-link:hover {
        text-decoration: underline;
    }

    .email-hint {
        font-size: 12px;
        color: var(--mut, #5A6478);
        text-align: center;
        margin-top: 14px;
    }
    </style>
</head>

<body class="auth-page">

    <div class="auth-container">
        <div class="auth-card">

            <div class="auth-icon-wrap">🔑</div>

            <?php if ($sent): ?>
            <!-- ─── حالة النجاح ─── -->
            <h1 style="text-align:center;margin-bottom:18px;" data-i18n="reset_check_email">تحقق من بريدك</h1>
            <div class="success-box">
                <span class="s-icon">📬</span>
                <p><?= htmlspecialchars($msg) ?></p>
            </div>
            <a href="login.php" class="back-link" data-i18n="back_to_login">← العودة لتسجيل الدخول</a>

            <?php else: ?>
            <!-- ─── النموذج ─── -->
            <h1 style="text-align:center;margin-bottom:6px;" data-i18n="forgot_password_title">نسيت كلمة المرور</h1>
            <p class="auth-logo-sub" style="text-align:center;margin-bottom:22px;" data-i18n="forgot_password_subtitle">
                أدخل بريدك وسنرسل لك رابط الاستعادة
            </p>

            <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <span data-i18n="<?= htmlspecialchars($error_key) ?>"><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>

            <!-- Forgot-password form — emails a tokenized reset link to the user's address -->
            <form method="POST" action="" data-mz-busy>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group">
                    <label for="fpEmail">البريد الإلكتروني</label>
                    <input id="fpEmail" type="email" name="email" placeholder="example@email.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required autofocus
                        autocomplete="email" aria-label="البريد الإلكتروني">
                </div>
                <button type="submit" class="btn btn-primary btn-full" data-i18n="send_reset_link">
                    إرسال رابط الاستعادة 📧
                </button>
            </form>

            <p class="email-hint" data-i18n="reset_email_hint">
                تأكد من إدخال البريد الذي سجّلت به حسابك في ميزان
            </p>
            <a href="login.php" class="back-link" data-i18n="back_to_login">← العودة لتسجيل الدخول</a>

            <?php endif; ?>

        </div>
    </div>

    <!-- أزرار التحكم -->
    <div class="controls-bar">
        <button class="control-btn" id="themeToggle" onclick="toggleTheme()" title="الوضع الليلي">🌙</button>
        <button class="control-btn" id="langToggle" onclick="toggleLang()" title="English">EN</button>
    </div>

    <script src="assets/js/main.js"></script>
</body>

</html>