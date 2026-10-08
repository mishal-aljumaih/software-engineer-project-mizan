<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: admin_login.php
 * PURPOSE: Dedicated admin portal login screen and credential validation.
 * OWNER: Mishal Al-jumaih - ScrumMaster & Development Member (Full Stack All Rounder)
 * ========================================================================
 */
require_once 'includes/security.php';
mz_send_security_headers();
mz_session_start(); // uses MIZANSESSID — must match dashboard.php

// Bypass the form for sessions that already hold admin privilege — skip straight to the dashboard
if (!empty($_SESSION['user_id']) && !empty($_SESSION['is_admin'])) {
    header('Location: /admin/dashboard.php');
    exit;
}

require_once 'config/db.php';
$pdo       = getDB();
$error     = '';
$error_key = '';

// Generate CSRF token once per session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// ── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF guard — constant-time compare of submitted vs session token
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        $error = 'انتهت صلاحية الجلسة — أعد المحاولة'; $error_key = 'err_session_expired';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'الرجاء إدخال الإيميل وكلمة المرور'; $error_key = 'err_fields_required';
        } else {
            // Restrict the lookup to accounts flagged is_admin=1 — non-admin users cannot log in here even with correct credentials
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_admin = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Verify password against bcrypt hash; on success bootstrap an admin session
            if ($user && password_verify($password, $user['password'])) {
                // Rotate the session ID after privilege escalation to defeat session-fixation
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['is_admin']  = 1;
                $_SESSION['role']      = $user['role'] ?? 'admin';
                header('Location: /admin/dashboard.php');
                exit;
            } else {
                $error = 'الإيميل أو كلمة المرور غير صحيحة، أو الحساب لا يملك صلاحيات المدير'; $error_key = 'err_admin_credentials';
            }
        }
    }
}
// ─────────────────────────────────────────────────────────────────────────
// END PHP LOGIC — BEGIN HTML VIEW (admin login card + animated bg canvas)
// ─────────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة المدير — ميزان</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
    <!-- SEO tags for admin login are intentionally minimal -->
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
    /* ── Admin login page overrides ── */
    .admin-login-wrap {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--bg);
        padding: 24px;
        position: relative;
    }

    #bg-canvas {
        position: fixed;
        inset: 0;
        width: 100%;
        height: 100%;
        z-index: 0;
        pointer-events: none;
        opacity: .35;
    }

    .admin-card {
        position: relative;
        z-index: 2;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 48px 52px 44px;
        width: 100%;
        max-width: 540px;
        box-shadow: 0 8px 40px rgba(0,0,0,.10);
        animation: cardIn .55s cubic-bezier(.22,1,.36,1) both;
    }

    @keyframes cardIn {
        from { opacity:0; transform: translateY(28px) scale(.97); }
        to   { opacity:1; transform: none; }
    }

    .admin-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(239,68,68,.10);
        color: var(--danger);
        border: 1px solid rgba(239,68,68,.22);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .5px;
        margin-bottom: 20px;
        text-transform: uppercase;
    }

    .admin-logo {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 6px;
    }

    .admin-logo img {
        height: 36px;
        width: auto;
        object-fit: contain;
    }

    .admin-logo-fallback {
        font-size: 28px;
        line-height: 1;
    }

    .admin-logo-text {
        font-size: 22px;
        font-weight: 900;
        color: var(--primary);
    }

    .admin-subtitle {
        font-size: 13px;
        color: var(--text-muted);
        margin-bottom: 28px;
    }

    .admin-card h1 {
        font-size: 20px;
        font-weight: 800;
        color: var(--text);
        margin: 0 0 24px;
    }

    .admin-form .form-group {
        margin-bottom: 16px;
    }

    .admin-form label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-muted);
        margin-bottom: 6px;
    }

    .admin-form input {
        width: 100%;
        padding: 11px 14px;
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        color: var(--text);
        font-size: 14px;
        font-family: inherit;
        box-sizing: border-box;
        transition: border-color .2s, box-shadow .2s;
    }

    .admin-form input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(3,105,161,.12);
    }

    .admin-submit {
        width: 100%;
        padding: 12px;
        background: var(--danger);
        border: none;
        border-radius: var(--radius-sm);
        color: #fff;
        font-size: 15px;
        font-weight: 700;
        font-family: inherit;
        cursor: pointer;
        transition: opacity .2s, transform .15s;
        margin-top: 8px;
    }

    .admin-submit:hover { opacity: .9; transform: translateY(-1px); }
    .admin-submit:active { transform: translateY(0); }

    .admin-back {
        display: block;
        text-align: center;
        margin-top: 20px;
        font-size: 13px;
        color: var(--text-muted);
        text-decoration: none;
        transition: color .2s;
    }

    .admin-back:hover { color: var(--primary); }

    .admin-error {
        background: rgba(239,68,68,.10);
        border: 1px solid rgba(239,68,68,.25);
        color: var(--danger);
        border-radius: var(--radius-sm);
        padding: 10px 14px;
        font-size: 13px;
        margin-bottom: 18px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .controls-bar {
        position: fixed;
        bottom: 20px;
        left: 20px;
        display: flex;
        gap: 8px;
        z-index: 100;
    }

    .control-btn {
        width: 36px;
        height: 36px;
        border-radius: 9px;
        background: var(--surface);
        border: 1px solid var(--border);
        cursor: pointer;
        font-size: 14px;
        color: var(--text-muted);
        font-family: inherit;
        font-weight: 700;
        transition: border-color .2s, color .2s;
    }

    .control-btn:hover { border-color: var(--primary); color: var(--primary); }
    </style>
</head>
<body>
<canvas id="bg-canvas" aria-hidden="true"></canvas>

<div class="admin-login-wrap">
    <div class="admin-card">
        <div class="admin-badge">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1a5 5 0 0 1 5 5v2h1a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V10a2 2 0 0 1 2-2h1V6a5 5 0 0 1 5-5zm0 11a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm0-9a3 3 0 0 0-3 3v2h6V6a3 3 0 0 0-3-3z"/></svg>
            <span data-i18n="admin_badge">وصول المدير فقط</span>
        </div>

        <div class="admin-logo">
            <img src="/assets/icons/logo.png" alt="ميزان"
                 onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'admin-logo-fallback',textContent:'⚖️'}))">
            <span class="admin-logo-text">ميزان</span>
        </div>
        <p class="admin-subtitle" data-i18n="admin_login_subtitle">لوحة التحكم — صلاحيات محدودة</p>

        <h1 data-i18n="admin_login_h1">تسجيل دخول المدير</h1>

        <?php if ($error): ?>
        <div class="admin-error">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            <span data-i18n="<?= htmlspecialchars($error_key) ?>"><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <!-- Admin login form — submits credentials for is_admin=1 verification -->
        <form method="POST" action="" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <div class="form-group">
                <label for="admin_email" data-i18n="admin_email_label">البريد الإلكتروني</label>
                <input type="email" id="admin_email" name="email" placeholder="admin@example.com" required
                       data-i18n-placeholder="admin_email_ph"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email">
            </div>
            <div class="form-group">
                <label for="admin_pass" data-i18n="admin_pass_label">كلمة المرور</label>
                <input type="password" id="admin_pass" name="password" placeholder="••••••••" required
                       data-i18n-placeholder="admin_pass_ph"
                       autocomplete="current-password">
            </div>
            <button type="submit" class="admin-submit"><span data-i18n="admin_login_btn">🔐 الدخول إلى لوحة التحكم</span></button>
        </form>

        <a href="/welcome.php" class="admin-back" data-i18n="admin_back">← العودة إلى الصفحة الرئيسية</a>
    </div>
</div>

<!-- Theme / lang controls -->
<div class="controls-bar">
    <button class="control-btn" id="themeToggle" onclick="toggleTheme()" title="الوضع الليلي">🌙</button>
    <button class="control-btn" id="langToggle" onclick="toggleLang()" title="Switch language">EN</button>
</div>

<script src="/assets/js/main.js"></script>
<script>
(function initBg() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const cv  = document.getElementById('bg-canvas');
    const ctx = cv.getContext('2d');
    let pts   = [];
    const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';

    // Defines the resize routine.
    function resize() { cv.width = innerWidth; cv.height = innerHeight; }

    // Defines the spawn routine.
    function spawn() {
        pts = [];
        const n = Math.max(18, Math.floor((cv.width * cv.height) / 20000));
        for (let i = 0; i < n; i++) {
            pts.push({ x: Math.random()*cv.width, y: Math.random()*cv.height,
                vx: (Math.random()-.5)*.4, vy: (Math.random()-.5)*.4, r: Math.random()*1.6+.7 });
        }
    }

    let raf;
    // Renders.
    function draw() {
        ctx.clearRect(0,0,cv.width,cv.height);
        const base = isDark() ? '52,211,153' : '29,30,82';
        const MD = 100;
        for (let i = 0; i < pts.length; i++) {
            const p = pts[i];
            p.x += p.vx; p.y += p.vy;
            if (p.x<0||p.x>cv.width)  p.vx*=-1;
            if (p.y<0||p.y>cv.height) p.vy*=-1;
            ctx.beginPath(); ctx.arc(p.x,p.y,p.r,0,Math.PI*2);
            ctx.fillStyle=`rgba(${base},.4)`; ctx.fill();
            for (let j=i+1; j<pts.length; j++) {
                const q=pts[j], dx=p.x-q.x, dy=p.y-q.y, d=Math.hypot(dx,dy);
                if (d<MD) {
                    ctx.beginPath(); ctx.moveTo(p.x,p.y); ctx.lineTo(q.x,q.y);
                    ctx.strokeStyle=`rgba(${base},${(.12*(1-d/MD)).toFixed(3)})`;
                    ctx.lineWidth=.7; ctx.stroke();
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
