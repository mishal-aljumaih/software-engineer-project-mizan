<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: header.php
 * PURPOSE: Shared top navigation, sidebar, and per-page JS bootstrap variables.
 * OWNER: Abdullah Radhi - Development Member, DB Admin & UI/UX Lead
 * ========================================================================
 */
// includes/header.php
// يُضمَّن في أول كل صفحة داخلية بعد auth.php
// الاستخدام: require_once '../includes/header.php';
// أو من الجذر: require_once 'includes/header.php';

$current_page = basename($_SERVER['PHP_SELF'], '.php');

$nav_items = [
    ['id' => 'index',          'icon' => '⊞',  'key' => 'dashboard',  'label' => 'لوحة التحكم', 'href' => '/index.php'],
    ['id' => 'projects',       'icon' => '◈',  'key' => 'projects',   'label' => 'المشاريع',    'href' => '/pages/projects.php'],
    ['id' => 'files',          'icon' => '◫',  'key' => 'files',      'label' => 'ملفاتي',      'href' => '/pages/files.php'],
    ['id' => 'reports',        'icon' => '◷',  'key' => 'reports',    'label' => 'التقارير',    'href' => '/pages/reports.php'],
    ['id' => 'support',        'icon' => '◇',  'key' => 'support',    'label' => 'الدعم',       'href' => '/pages/support.php'],
];

// Unread-notification count drives the red bell badge in the navbar
// — wrapped in try/catch so a missing notifications table never breaks page load on fresh installs
$notif_count = 0;
try {
    $pdo_h = getDB();
    $stmt_h = $pdo_h->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt_h->execute([$current_user_id]);
    $notif_count = $stmt_h->fetchColumn();
} catch (Exception $e) { /* تجاهل إذا الجدول غير موجود */ }

// Active announcement for banner
$_ann = null;
try {
    $_ann = $pdo_h->query("
        SELECT id, message_ar, message_en,
               COALESCE(bg_color,'')   bg_color,
               COALESCE(text_color,'') text_color,
               COALESCE(font_size, 14) font_size
        FROM   announcements
        WHERE  is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())
        ORDER  BY created_at DESC LIMIT 1
    ")->fetch();
} catch (Exception $e) { /* table not yet created */ }
?>
<?php
// Read saved language from cookie so the server-rendered <html lang/dir>
// matches what main.js will apply, preventing a flash of the wrong locale.
$saved_lang = ($_COOKIE['mizan_lang'] ?? '') === 'en' ? 'en' : 'ar';
// ─────────────────────────────────────────────────────────────────────────
// END PHP DATA-FETCHING — BEGIN SHARED HTML SHELL (navbar + sidebar + main)
// ─────────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="<?= $saved_lang ?>" dir="<?= $saved_lang === 'en' ? 'ltr' : 'rtl' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'ميزان', ENT_QUOTES, 'UTF-8') ?> — ميزان</title>
    <!-- Favicons — place favicon.ico, favicon-32x32.png, apple-touch-icon.png
         in /assets/icons/ to activate. Browser silently ignores missing icons. -->
    <link  rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/icons/android-chrome-192x192.png">
    <link rel="manifest" href="/assets/icons/site.webmanifest">

    <!-- Early-load theme + language: runs before any paint to prevent FOUC.
         Reads localStorage and sets data-theme / lang / dir on <html> so the
         first frame already matches the user's saved preference. Charts,
         SweetAlerts, and modals all inherit from <html data-theme>. -->
    <script>
    (function () {
        try {
            var t = localStorage.getItem('mizan_theme');
            if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', t);
            var l = localStorage.getItem('mizan_lang');
            if (l !== 'ar' && l !== 'en') l = 'ar';
            document.documentElement.setAttribute('lang', l);
            document.documentElement.setAttribute('dir', l === 'ar' ? 'rtl' : 'ltr');
            // Keep cookie in sync so PHP can render the right lang on next request.
            document.cookie = 'mizan_lang=' + l + '; path=/; max-age=31536000; SameSite=Lax';
        } catch (e) { /* localStorage blocked — defaults will apply */ }
    })();
    </script>
    <?php
        // Resolve currency: session → DB lookup (cached) → default SAR
        if (empty($_SESSION['currency']) && !empty($current_user_id)) {
            try {
                $cstmt = $pdo_h->prepare("SELECT currency FROM users WHERE id = ? LIMIT 1");
                $cstmt->execute([$current_user_id]);
                $_SESSION['currency'] = $cstmt->fetchColumn() ?: 'SAR';
            } catch (\Throwable $e) { $_SESSION['currency'] = 'SAR'; }
        }
        $__currency = strtoupper($_SESSION['currency'] ?? 'SAR');
    ?>
    <script>
        window.MIZAN_MUST_CHANGE_PASSWORD = <?= !empty($_SESSION['must_change_password']) ? 'true' : 'false' ?>;
        window.MIZAN_CURRENCY = <?= json_encode($__currency) ?>;
    </script>

    <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <link rel="stylesheet" href="/assets/css/animations.css?v=<?= filemtime(__DIR__ . '/../assets/css/animations.css') ?>">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <!-- SEO / Open Graph / Twitter Card -->
    <meta name="description" content="ميزان — أرشفة الفواتير، تتبع المصاريف، وإدارة الضمانات في مكان واحد.">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= htmlspecialchars($page_title ?? 'ميزان', ENT_QUOTES, 'UTF-8') ?> — ميزان">
    <meta property="og:description" content="ميزان — أرشفة الفواتير، تتبع المصاريف، وإدارة الضمانات في مكان واحد.">
    <meta name="image" content="/assets/icons/meta-banner.png">
    <meta property="og:image" content="/assets/icons/meta-banner.png">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($page_title ?? 'ميزان', ENT_QUOTES, 'UTF-8') ?> — ميزان">
    <meta name="twitter:description" content="ميزان — أرشفة الفواتير، تتبع المصاريف، وإدارة الضمانات في مكان واحد.">
    <meta name="twitter:image" content="/assets/icons/meta-banner.png">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/mathjs@13.0.0/lib/browser/math.js"></script>
    <!-- SweetAlert2 — loaded GLOBALLY so every page can use Swal.fire(). -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/assets/js/utils/math.js" defer></script>
    <!-- Flatpickr — lightweight date picker with Arabic locale & RTL support -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js" defer></script>
</head>

<body>

    <!-- Keyboard-only skip link → jumps over the navbar/sidebar straight to main. -->
    <a href="#mainContent" class="skip-link">Skip to main content</a>

    <?php if ($_ann): ?>
    <?php
        $ann_style = '';
        if (!empty($_ann['bg_color']))   $ann_style .= 'background:' . htmlspecialchars($_ann['bg_color']) . ';';
        if (!empty($_ann['text_color'])) $ann_style .= 'color:' . htmlspecialchars($_ann['text_color']) . ';';
        if (!empty($_ann['font_size']))  $ann_style .= 'font-size:' . (int)$_ann['font_size'] . 'px;';
    ?>
    <div class="ann-banner" id="annBanner" role="alert" aria-live="polite"
         data-ann-id="<?= (int)$_ann['id'] ?>"
         <?= $ann_style ? 'style="' . $ann_style . '"' : '' ?>>
        <span class="ann-icon" aria-hidden="true">📢</span>
        <span class="ann-text" id="annText">
            <span class="ann-ar"><?= htmlspecialchars($_ann['message_ar']) ?></span>
            <span class="ann-en" style="display:none;"><?= htmlspecialchars($_ann['message_en']) ?></span>
        </span>
        <button class="ann-close" onclick="dismissAnnBanner()" aria-label="Dismiss announcement" title="إغلاق">✕</button>
    </div>
    <style>
    .ann-banner {
        position: sticky;
        top: 0;
        z-index: 200;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 11px 20px;
        background: linear-gradient(90deg, var(--primary), #0ea5e9);
        color: #fff;
        font-size: 15px;
        font-weight: 700;
        line-height: 1.5;
        letter-spacing: .01em;
    }
    .ann-icon  { flex-shrink: 0; font-size: 16px; }
    .ann-text  { flex: 1; }
    .ann-close {
        flex-shrink: 0; background: rgba(255,255,255,.2); border: none;
        border-radius: 6px; color: #fff; font-size: 13px; padding: 3px 8px;
        cursor: pointer; font-family: inherit; transition: background .2s;
    }
    .ann-close:hover { background: rgba(255,255,255,.35); }
    </style>
    <?php endif; ?>

    <?php if (basename($_SERVER['PHP_SELF']) !== 'welcome.php'): ?>
    <canvas id="bg-canvas" aria-hidden="true"></canvas>
    <?php endif; ?>

    <!-- ===== NAVBAR ===== -->
    <nav class="navbar" id="mainNavbar" aria-label="Primary">
        <div class="navbar-right">
            <!-- زر الـ Sidebar على الجوال -->
            <button class="navbar-icon-btn" id="sidebarToggle" onclick="toggleSidebar()"
                    type="button" aria-label="Toggle navigation menu" aria-expanded="false"
                    aria-controls="mainSidebar" title="القائمة">
                <span class="hamburger" id="hamburgerIcon" aria-hidden="true">
                    <span></span><span></span><span></span>
                </span>
            </button>
            <a href="/welcome.php" class="navbar-brand" aria-label="Mizan home">
                <img src="/assets/icons/logo.png" alt="Mizan" class="brand-logo" style="height:28px;width:auto;vertical-align:middle;">
                <span class="brand-name" data-i18n="app_name">ميزان</span>
            </a>
        </div>

        <!-- ═══ Global Search (navbar-center) ═══ -->
        <div class="global-search" id="globalSearchWrap" role="search">
            <span class="gs-icon" aria-hidden="true">🔍</span>
            <input type="text" id="navGlobalSearch" class="gs-input" data-i18n-placeholder="search_placeholder"
                placeholder="بحث في المشاريع، العمليات، الملفات..." autocomplete="off"
                aria-label="Search projects, expenses, and files"
                aria-controls="navSearchDropdown">
            <kbd class="gs-kbd" aria-hidden="true">Ctrl K</kbd>
            <div class="gs-dropdown" id="navSearchDropdown" role="listbox" aria-label="Search results"></div>
        </div>

        <div class="navbar-left">
            <!-- Notifications bell — opens the slide-in panel showing the user's recent alerts -->
            <button class="navbar-icon-btn notif-trigger" onclick="toggleNotifPanel()"
                    type="button" aria-label="Notifications" aria-haspopup="true" aria-expanded="false"
                    title="التنبيهات">
                <span aria-hidden="true">🔔</span>
                <?php if ($notif_count > 0): ?>
                <span class="notif-badge" aria-label="<?= (int)$notif_count ?> unread notifications"><?= (int)$notif_count ?></span>
                <?php endif; ?>
            </button>

            <!-- Theme toggle — flips light/dark mode and persists choice in localStorage -->
            <button class="navbar-icon-btn" id="themeToggle" onclick="toggleTheme()"
                    type="button" aria-label="Toggle dark mode" aria-pressed="false"
                    data-i18n-title="theme_label" title="الوضع الليلي">🌙</button>

            <!-- Native Language Toggle — instant EN/AR swap via the i18n
                 dictionary in main.js (no third-party widget). -->
            <button class="navbar-icon-btn lang-toggle" id="langToggle" onclick="toggleLang()"
                    type="button" aria-label="Switch language between Arabic and English"
                    title="Switch language">EN</button>

            <!-- User menu — avatar + dropdown linking to Settings and Logout -->
            <div class="navbar-user" onclick="toggleUserMenu()">
                <div class="avatar" id="userAvatar">
                    <?= mb_substr($current_user_name, 0, 1) ?>
                </div>
                <span class="user-name-short" id="userNameNav"><?= htmlspecialchars($current_user_name) ?></span>
                <span style="font-size:11px; color:rgba(255,255,255,0.7);">▾</span>

                <div class="user-dropdown" id="userDropdown">
                    <a href="/pages/settings.php" class="dropdown-item">⚙️ <span
                            data-i18n="settings">الإعدادات</span></a>
                    <div class="dropdown-divider"></div>
                    <a href="/logout.php" class="dropdown-item danger">🚪 <span data-i18n="logout">تسجيل
                            الخروج</span></a>
                </div>
            </div>
        </div>
    </nav>

    <!-- ===== SIDEBAR (mobile) ===== -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()" aria-hidden="true"></div>
    <aside class="sidebar" id="mainSidebar" aria-label="Mobile navigation">
        <nav class="sidebar-inner" aria-label="Mobile primary navigation">
            <?php foreach ($nav_items as $item): ?>
            <a href="<?= $item['href'] ?>" class="sidebar-link <?= ($current_page === $item['id']) ? 'active' : '' ?>"
                data-i18n="<?= $item['key'] ?>"
                <?= ($current_page === $item['id']) ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-icon" aria-hidden="true"><?= $item['icon'] ?></span>
                <span class="sidebar-label-text"><?= $item['label'] ?></span>
            </a>
            <?php endforeach; ?>

            <div class="sidebar-footer">
                <a href="/logout.php" class="sidebar-link sidebar-logout">
                    <span class="sidebar-icon" aria-hidden="true">🚪</span>
                    <span class="sidebar-label-text" data-i18n="logout">تسجيل الخروج</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- ===== NOTIFICATIONS PANEL ===== -->
    <div class="notif-panel" id="notifPanel" role="dialog" aria-label="Notifications" aria-modal="false" aria-hidden="true">
        <div class="notif-panel-header">
            <span data-i18n="notifications">التنبيهات</span>
            <button onclick="markAllRead()" class="notif-mark-all" type="button"
                    data-i18n="mark_all_read" aria-label="Mark all notifications as read">تحديد كمقروء</button>
        </div>
        <div class="notif-list" id="notifList" role="list">
            <div class="notif-loading" data-i18n="loading">جاري التحميل...</div>
        </div>
    </div>
    <div class="notif-overlay" id="notifOverlay" onclick="closeNotifPanel()" aria-hidden="true"></div>

    <!-- ===== GLOBAL SEARCH OVERLAY ===== -->
    <div class="search-overlay" id="searchOverlay" role="dialog" aria-label="Global search" aria-modal="true" aria-hidden="true">
        <div class="search-container">
            <div class="search-header">
                <span class="search-icon" aria-hidden="true">🔍</span>
                <input type="text" id="globalSearchInput" class="search-input"
                    data-i18n-placeholder="search_placeholder" placeholder="بحث في المشاريع، العمليات، الملفات..."
                    autocomplete="off" autofocus
                    aria-label="Search across projects, expenses, and files"
                    aria-controls="searchResults">
                <kbd class="search-kbd" aria-hidden="true">ESC</kbd>
            </div>
            <div class="search-results" id="searchResults" role="listbox" aria-label="Search results">
                <div class="search-hint" data-i18n="search_hint">اكتب للبحث... أو استخدم Ctrl+K لفتح البحث</div>
            </div>
        </div>
    </div>

    <!-- ===== LAYOUT WRAPPER ===== -->
    <div class="layout">
        <aside class="sidebar-desktop" id="sidebarDesktop" aria-label="Primary navigation">
            <nav aria-label="Desktop primary navigation">
                <?php foreach ($nav_items as $item): ?>
                <a href="<?= $item['href'] ?>" class="sidebar-link <?= ($current_page === $item['id']) ? 'active' : '' ?>"
                    data-i18n="<?= $item['key'] ?>"
                    <?= ($current_page === $item['id']) ? 'aria-current="page"' : '' ?>>
                    <span class="sidebar-icon" aria-hidden="true"><?= $item['icon'] ?></span>
                    <span class="sidebar-label-text"><?= $item['label'] ?></span>
                </a>
                <?php endforeach; ?>
            </nav>
        </aside>
        <main class="main-content" id="mainContent" role="main">
            <!-- Smart Upload modal include removed — file kept on disk at includes/smart_upload_modal.php for future restoration -->
            <!-- ── Site rating popup trigger (fires 3.5s after page load) ── -->
    <script>
    window.addEventListener('load', function () {
        setTimeout(function () {
            if (typeof mizanFetch !== 'function' || typeof showSiteRatingPopup !== 'function') return;
            mizanFetch('/api/support.php?action=check_rating_prompt')
                .then(function (r) { return r.json(); })
                .then(function (d) { if (d.show) showSiteRatingPopup(); })
                .catch(function () {});
        }, 3500);
    });
    </script>

    <!-- محتوى الصفحة يبدأ هنا -->