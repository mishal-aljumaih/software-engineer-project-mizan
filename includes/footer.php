<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: footer.php
 * PURPOSE: Shared footer markup and global script include block.
 * ========================================================================
 */
// includes/footer.php
// يُضمَّن في نهاية كل صفحة داخلية
// الاستخدام: require_once '../includes/footer.php';
?>

</main><!-- end main-content -->
</div><!-- end layout -->

<?php
// Smart Upload Modal — included ONCE globally so any page can call
// window.openSmartUpload({...}) without rendering the modal inline.
// The modal itself is hidden by default via #smartUploadModal CSS
// (assets/css/style.css L1580+) and only shown when JS adds .active.
if (isset($current_user_id)) {
    require_once __DIR__ . '/smart_upload_modal.php';
}
?>

<!-- ── Cookie Consent Banner ──────────────────────────────── -->
<style>
#cookieBanner {
    position: fixed; bottom: 0; left: 0; right: 0;
    z-index: 9998; padding: 0 16px 16px;
    display: none;
    transform: translateY(130%);
    transition: transform .42s cubic-bezier(.34,1.3,.64,1);
    pointer-events: none;
}
#cookieBanner.cookie-visible { transform: translateY(0); pointer-events: auto; }
.cookie-inner {
    max-width: 740px; margin: 0 auto;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 16px 20px;
    display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
    box-shadow: 0 -2px 20px rgba(0,0,0,.07), 0 8px 32px rgba(0,0,0,.10);
    backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
}
.cookie-icon { flex-shrink: 0; color: var(--primary); opacity: .85; }
.cookie-text { flex: 1; min-width: 180px; }
.cookie-text strong { display: block; font-size: 13px; font-weight: 800; color: var(--text); margin-bottom: 3px; }
.cookie-text p { font-size: 12px; color: var(--text-muted); line-height: 1.6; margin: 0; }
.cookie-btns { display: flex; gap: 8px; flex-shrink: 0; flex-wrap: wrap; }
.cookie-btn-all {
    padding: 8px 18px; background: var(--primary); border: none; border-radius: 10px;
    color: #fff; font-size: 12.5px; font-weight: 700; font-family: inherit;
    cursor: pointer; white-space: nowrap; transition: opacity .2s, transform .15s;
}
.cookie-btn-all:hover { opacity: .88; transform: translateY(-1px); }
.cookie-btn-essential {
    padding: 7px 14px; background: none;
    border: 1px solid var(--border); border-radius: 10px;
    color: var(--text-muted); font-size: 12.5px; font-weight: 600; font-family: inherit;
    cursor: pointer; white-space: nowrap; transition: border-color .2s, color .2s;
}
.cookie-btn-essential:hover { border-color: var(--primary); color: var(--primary); }
@media (max-width: 480px) {
    .cookie-inner { padding: 14px 16px; gap: 10px; }
    .cookie-btns { width: 100%; }
    .cookie-btn-all, .cookie-btn-essential { flex: 1; text-align: center; }
}
</style>

<!-- Cookie consent banner — shown until the user clicks one of the two buttons (state persisted in localStorage) -->
<div id="cookieBanner" role="dialog" aria-live="polite" aria-labelledby="cookieBannerTitle">
    <div class="cookie-inner">
        <svg class="cookie-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/>
            <path d="M8.56 2.75c4.37 6.03 6.02 9.42 8.03 17.72m2.54-15.38c-1.66 2.1-4.18 3.51-7.13 3.51M10.22 4.1c-1.08 3.12-3.06 5.49-5.77 7.11"/>
        </svg>
        <div class="cookie-text">
            <strong id="cookieBannerTitle" data-i18n="cookie_title">تفضيلات ملفات الارتباط</strong>
            <p data-i18n="cookie_desc">نستخدم ملفات الارتباط الأساسية فقط لتشغيل النظام وتذكر تفضيلاتك. لا توجد ملفات تتبع أو إعلانات.</p>
        </div>
        <div class="cookie-btns">
            <button class="cookie-btn-all" onclick="acceptCookies('all')" data-i18n="cookie_accept_all">قبول الكل</button>
            <button class="cookie-btn-essential" onclick="acceptCookies('essential')" data-i18n="cookie_essential_only">الأساسية فقط</button>
        </div>
    </div>
</div>

<script src="/assets/js/main.js?v=<?= filemtime(__DIR__ . '/../assets/js/main.js') ?>"></script>
<script>
// ── Flatpickr: auto-apply to all date inputs ──────────────────
document.addEventListener('DOMContentLoaded', function () {
    if (typeof flatpickr === 'undefined') return;
    const isAr = (localStorage.getItem('mizan_lang') || 'ar') === 'ar';
    const cfg = {
        locale: isAr ? 'ar' : 'en',
        dateFormat: 'Y-m-d',
        allowInput: true,
        disableMobile: false,
    };
    document.querySelectorAll('input[type="date"]:not(.no-flatpickr)').forEach(function (el) {
        flatpickr(el, cfg);
    });
    // Re-apply when language changes
    window.addEventListener('mizan:lang-change', function (ev) {
        document.querySelectorAll('input[type="date"]:not(.no-flatpickr)._flatpickr').forEach(function (el) {
            if (el._flatpickr) {
                el._flatpickr.set('locale', ev.detail.lang === 'ar' ? 'ar' : 'en');
            }
        });
    });
});

// ── Announcement banner dismiss ───────────────────────────────
function dismissAnnBanner() {
    const banner = document.getElementById('annBanner');
    if (!banner) return;
    const id = banner.dataset.annId;
    try { localStorage.setItem('mz_ann_dismissed_' + id, '1'); } catch (e) {}
    banner.style.transition = 'opacity .25s, max-height .3s';
    banner.style.opacity = '0';
    banner.style.maxHeight = '0';
    banner.style.overflow  = 'hidden';
    setTimeout(function () { banner.remove(); }, 300);
}

// Auto-dismiss if this announcement was already dismissed
(function () {
    const banner = document.getElementById('annBanner');
    if (!banner) return;
    const id = banner.dataset.annId;
    try {
        if (localStorage.getItem('mz_ann_dismissed_' + id) === '1') {
            banner.remove();
        }
    } catch (e) {}
    // Show correct language text
    function syncAnnLang() {
        const lang = localStorage.getItem('mizan_lang') || 'ar';
        const ar = banner.querySelector('.ann-ar');
        const en = banner.querySelector('.ann-en');
        if (ar) ar.style.display = lang === 'ar' ? '' : 'none';
        if (en) en.style.display = lang === 'en' ? '' : 'none';
    }
    syncAnnLang();
    window.addEventListener('mizan:lang-change', syncAnnLang);
})();
</script>

</body>

</html>
