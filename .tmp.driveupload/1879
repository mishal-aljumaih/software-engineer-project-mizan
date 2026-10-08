<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: logout.php
 * PURPOSE: Destroys the active session and redirects to login.
 * OWNER: Mishal Al-jumaih - ScrumMaster & Development Member (Full Stack All Rounder)
 * ========================================================================
 */
require_once 'includes/security.php';
mz_send_security_headers();
mz_session_start();
require_once 'config/db.php';

// Perform the actual logout when confirmed via POST (form submit) or ?confirm=1 (auto-logout countdown)
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['confirm'])) {
    // Audit-log the logout BEFORE we wipe the session so we still know who is leaving
    if (!empty($_SESSION['user_id'])) {
        logActivity(getDB(), (int) $_SESSION['user_id'], 'user_logout', '');
    }
    // حذف كل بيانات الجلسة
    $_SESSION = [];

    // Expire the session cookie on the client too — server-side destroy alone is not enough
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
    header("Location: welcome.php");
    exit;
}

// إذا ما كان مسجلاً أصلاً
if (!isset($_SESSION['user_id'])) {
    header("Location: welcome.php");
    exit;
}

$user_name = $_SESSION['user_name'] ?? 'المستخدم';
// ─────────────────────────────────────────────────────────────────────────
// END PHP LOGIC — BEGIN HTML VIEW (logout confirmation card + 30s auto-logout)
// ─────────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الخروج — ميزان</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
    /* ── Logout page styles ── */
    .logout-wrap {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .logout-card {
        background: var(--surf, #fff);
        border: 1.5px solid var(--bdr, #DDD8CE);
        border-radius: 24px;
        padding: 44px 36px;
        max-width: 440px;
        width: 100%;
        text-align: center;
        box-shadow: 0 12px 48px rgba(0, 0, 0, .08);
        animation: cardIn .5s cubic-bezier(.22, 1, .36, 1) both;
    }

    @keyframes cardIn {
        from {
            opacity: 0;
            transform: translateY(24px) scale(.97);
        }

        to {
            opacity: 1;
            transform: none;
        }
    }

    .lo-avatar {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, rgba(0, 108, 53, .12), rgba(0, 108, 53, .06));
        border: 2px solid rgba(0, 108, 53, .2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 36px;
        margin: 0 auto 20px;
    }

    .lo-title {
        font-size: 22px;
        font-weight: 900;
        color: var(--txt, #0F172A);
        margin: 0 0 8px;
    }

    .lo-sub {
        font-size: 14px;
        color: var(--mut, #5A6478);
        margin: 0 0 28px;
        line-height: 1.7;
    }

    .lo-user {
        display: inline-block;
        background: var(--glow, rgba(0, 108, 53, .1));
        color: var(--g, #006C35);
        padding: 4px 14px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 14px;
        margin-bottom: 28px;
    }

    .lo-btns {
        display: flex;
        gap: 12px;
        justify-content: center;
        flex-wrap: wrap;
    }

    .btn-logout {
        padding: 13px 28px;
        background: #EF4444;
        color: #fff;
        border: none;
        border-radius: 11px;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: all .25s;
        font-family: 'Tajawal', sans-serif;
        box-shadow: 0 4px 16px rgba(239, 68, 68, .3);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-logout:hover {
        background: #DC2626;
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(239, 68, 68, .4);
    }

    .btn-logout:active {
        transform: scale(.97);
    }

    .btn-stay {
        padding: 13px 28px;
        background: var(--surf, #fff);
        color: var(--txt, #0F172A);
        border: 2px solid var(--bdr, #DDD8CE);
        border-radius: 11px;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: all .25s;
        font-family: 'Tajawal', sans-serif;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-stay:hover {
        border-color: var(--g, #006C35);
        color: var(--g, #006C35);
        transform: translateY(-2px);
    }

    .lo-divider {
        width: 48px;
        height: 3px;
        background: linear-gradient(90deg, var(--g, #006C35), var(--gold, #C9A84C));
        border-radius: 2px;
        margin: 0 auto 24px;
    }

    /* Auto countdown */
    .lo-countdown {
        font-size: 12px;
        color: var(--mut, #5A6478);
        margin-top: 20px;
    }

    .lo-countdown span {
        color: var(--g, #006C35);
        font-weight: 700;
    }
    </style>
</head>

<body>

    <div class="logout-wrap">
        <div class="logout-card">

            <div class="lo-avatar">👋</div>

            <h1 class="lo-title" data-i18n="logout_page_title">تسجيل الخروج</h1>
            <div class="lo-divider"></div>

            <div class="lo-user">👤 <?= htmlspecialchars($user_name) ?></div>

            <p class="lo-sub">
                <span data-i18n="logout_confirm">هل أنت متأكد أنك تريد الخروج من حسابك في ميزان؟</span><br>
                <span data-i18n="logout_data_saved">بياناتك محفوظة وستجدها عند عودتك.</span>
            </p>

            <div class="lo-btns">
                <!-- Logout button — POST request triggers session_destroy and cookie expiry -->
                <form method="POST" action="logout.php" style="margin:0;">
                    <button type="submit" class="btn-logout" data-i18n="logout_btn">
                        خروج
                    </button>
                </form>

                <!-- "Stay" button — returns to dashboard without ending the session -->
                <a href="index.php" class="btn-stay" data-i18n="logout_stay">
                    ابقَ في الحساب
                </a>
            </div>

            <p class="lo-countdown" id="countdown">
                <span data-i18n="logout_countdown">سيتم الخروج تلقائياً خلال</span>
                <span id="sec">30</span>
                <span data-i18n="logout_sec">ثانية</span>
            </p>

        </div>
    </div>

    <!-- أزرار التحكم -->
    <div class="controls-bar">
        <button class="control-btn" id="themeToggle" onclick="toggleTheme()" title="الوضع الليلي">🌙</button>
        <button class="control-btn" id="langToggle" onclick="toggleLang()" title="English">EN</button>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
    // 30-second auto-logout countdown — if the user neither confirms nor cancels, force the POST
    let secs = 30;
    const secEl = document.getElementById('sec');

    const timer = setInterval(() => {
        secs--;
        if (secEl) secEl.textContent = secs;

        if (secs <= 0) {
            clearInterval(timer);
            // تسجيل خروج تلقائي
            const f = document.createElement('form');
            f.method = 'POST';
            f.action = 'logout.php';
            document.body.appendChild(f);
            f.submit();
        }
    }, 1000);

    // إذا ضغط "ابقَ" يوقف العداد
    document.querySelectorAll('.btn-stay').forEach(btn => {
        btn.addEventListener('click', () => clearInterval(timer));
    });
    </script>
</body>

</html>