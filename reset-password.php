<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: reset-password.php
 * PURPOSE: Password reset confirmation page driven by tokenized email links.
 * OWNER: Mishal Al-jumaih - ScrumMaster & Development Member (Full Stack All Rounder)
 * ========================================================================
 */
require_once 'includes/security.php';
mz_send_security_headers();
mz_session_start();
if (isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

require_once 'config/db.php';

$token     = trim($_GET['token'] ?? '');
$error     = '';
$error_key = '';
$success   = false;
$valid     = false;
$email     = '';

// ─── Validate the reset token from the URL ───
// Compare the SHA-256 hash of the URL token against the stored hash and check expiry
if (empty($token)) {
    $error = 'رابط الاستعادة غير صحيح أو منتهي الصلاحية.';
} else {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "SELECT email FROM password_resets
         WHERE token = ? AND expires_at > NOW()
         LIMIT 1"
    );
    // Hash the incoming token before lookup — the DB never holds the raw token
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch();

    if ($row) {
        $valid = true;
        $email = $row['email'];
    } else {
        $error = 'رابط الاستعادة منتهي الصلاحية أو غير صحيح. يرجى طلب رابط جديد.';
    }
}

// ─── Process the new-password form (only if token validation already succeeded) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $password = $_POST['password']         ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل'; $error_key = 'pass_min_8';
    } elseif ($password !== $confirm) {
        $error = 'كلمتا المرور غير متطابقتين'; $error_key = 'pass_mismatch';
    } else {
        $pdo  = getDB();
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Persist the new bcrypt hash for the account tied to this token
        $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")
            ->execute([$hash, $email]);

        // Single-use token: delete after consumption so the same link can't be reused
        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")
            ->execute([$email]);

        $success = true;
    }
}
// ─────────────────────────────────────────────────────────────────────────
// END PHP LOGIC — BEGIN HTML VIEW (success / expired / form states)
// ─────────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور — ميزان</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
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
        padding: 22px 18px;
        text-align: center;
    }

    .success-box .s-icon {
        font-size: 44px;
        display: block;
        margin-bottom: 12px;
    }

    .success-box h3 {
        color: var(--g, #006C35);
        margin: 0 0 8px;
    }

    .success-box p {
        color: var(--mut, #5A6478);
        margin: 0;
        font-size: 14px;
        line-height: 1.7;
    }

    .error-box {
        background: rgba(239, 68, 68, .08);
        border: 1.5px solid rgba(239, 68, 68, .25);
        border-radius: 14px;
        padding: 22px 18px;
        text-align: center;
    }

    .error-box .e-icon {
        font-size: 44px;
        display: block;
        margin-bottom: 12px;
    }

    .error-box p {
        color: var(--txt, #0F172A);
        margin: 0 0 16px;
        font-size: 14px;
        line-height: 1.7;
    }

    .strength-bar {
        height: 4px;
        border-radius: 2px;
        background: var(--s2, #EDE9DF);
        margin-top: 6px;
        overflow: hidden;
    }

    .strength-fill {
        height: 100%;
        border-radius: 2px;
        transition: width .3s, background .3s;
        width: 0%;
    }

    .strength-label {
        font-size: 11px;
        margin-top: 4px;
        color: var(--mut, #5A6478);
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
    </style>
</head>

<body class="auth-page">

    <div class="auth-container">
        <div class="auth-card">

            <div class="auth-icon-wrap">🔐</div>

            <?php if ($success): ?>
            <!-- ─── نجاح ─── -->
            <div class="success-box">
                <span class="s-icon">✅</span>
                <h3 data-i18n="reset_password_success">تم تغيير كلمة المرور!</h3>
                <p data-i18n="reset_password_success_msg">كلمة المرور الجديدة حُفظت بنجاح. يمكنك الآن تسجيل الدخول.</p>
            </div>
            <a href="login.php" class="btn btn-primary btn-full" data-i18n="login_btn"
                style="display:block;text-align:center;margin-top:20px;text-decoration:none;">
                تسجيل الدخول ←
            </a>

            <?php elseif (!$valid): ?>
            <!-- ─── رابط منتهي ─── -->
            <div class="error-box">
                <span class="e-icon">⏰</span>
                <p><?= htmlspecialchars($error) ?></p>
                <a href="forgot-password.php" class="btn btn-primary" data-i18n="request_new_link"
                    style="display:inline-block;text-decoration:none;">
                    طلب رابط جديد
                </a>
            </div>
            <a href="login.php" class="back-link" data-i18n="back_to_login">← العودة لتسجيل الدخول</a>

            <?php else: ?>
            <!-- ─── النموذج ─── -->
            <h1 style="text-align:center;margin-bottom:6px;" data-i18n="new_password_title">كلمة مرور جديدة</h1>
            <p class="auth-logo-sub" style="text-align:center;margin-bottom:22px;" data-i18n="new_password_subtitle">
                اختر كلمة مرور قوية لحسابك
            </p>

            <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <span data-i18n="<?= htmlspecialchars($error_key) ?>"><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>

            <!-- New-password form — token stays in the query string so POST handler can rebind to the same account -->
            <form method="POST" action="?token=<?= htmlspecialchars($token) ?>">
                <div class="form-group">
                    <label data-i18n="new_password_label">كلمة المرور الجديدة</label>
                    <input type="password" name="password" id="pw" placeholder="••••••••" minlength="8" required
                        oninput="checkStrength(this.value)">
                    <div class="strength-bar">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                    <div class="strength-label" id="strengthLabel"></div>
                </div>
                <div class="form-group">
                    <label data-i18n="confirm_password_label">تأكيد كلمة المرور</label>
                    <input type="password" name="confirm_password" placeholder="••••••••" minlength="8" required>
                </div>
                <button type="submit" class="btn btn-primary btn-full" data-i18n="save_new_password">
                    حفظ كلمة المرور الجديدة ✓
                </button>
            </form>

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
    <script>
    // Live password-strength meter — runs on every keystroke in the password field.
    // Scores length + character classes, then paints the bar (red→green) and updates the label.
    function checkStrength(val) {
        const fill = document.getElementById('strengthFill');
        const label = document.getElementById('strengthLabel');
        if (!fill) return;

        let score = 0;
        if (val.length >= 8) score++;
        if (val.length >= 12) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const levels = [{
                w: '0%',
                bg: 'transparent',
                txt: ''
            },
            {
                w: '25%',
                bg: '#EF4444',
                txt: 'ضعيفة'
            },
            {
                w: '50%',
                bg: '#F97316',
                txt: 'متوسطة'
            },
            {
                w: '75%',
                bg: '#EAB308',
                txt: 'جيدة'
            },
            {
                w: '100%',
                bg: '#22C55E',
                txt: 'ممتازة ✓'
            },
        ];
        const lv = levels[Math.min(score, 4)];
        fill.style.width = lv.w;
        fill.style.background = lv.bg;
        label.textContent = lv.txt;
        label.style.color = lv.bg;
    }
    </script>
</body>

</html>