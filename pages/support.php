<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: support.php
 * PURPOSE: Support center: policies area and contact/tickets area (SPA toggle).
 * OWNER: Alwaleed Alzahrani - Development & Database Admin (Lead Programmer)
 * ========================================================================
 */
require_once '../includes/auth.php';
require_once '../config/db.php';

$page_title = 'الدعم';
require_once '../includes/header.php';
// ─────────────────────────────────────────────────────────────────────────
// END PHP BOOTSTRAP — BEGIN HTML VIEW (policies area + tickets SPA toggle)
// ─────────────────────────────────────────────────────────────────────────
?>

<div class="page-header reveal">
    <h1 class="page-title" data-i18n="support_title">الدعم والمساعدة</h1>
    <p class="text-muted" data-i18n="support_desc">أسئلة شائعة ونموذج تواصل</p>
</div>

<!-- ════════════════════════════════════════════════════════════
     AREA 1 — Policies grid (3 columns @ lg, LTR English content)
     Equivalent to Tailwind: grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8
═════════════════════════════════════════════════════════════ -->
<style>
/* Support page — Two-area switcher (SPA-style) */
.sup-switcher {
    display: inline-flex;
    margin: 0 auto 24px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 999px;
    padding: 5px;
    box-shadow: 0 2px 10px rgba(15,23,42,.05);
}
.sup-switcher-wrap { display: flex; justify-content: center; margin-bottom: 8px; }
.sup-sw-btn {
    appearance: none; border: none; background: transparent;
    color: var(--text-muted); font-family: inherit;
    font-size: 14px; font-weight: 600;
    padding: 10px 22px; border-radius: 999px; cursor: pointer;
    transition: background-color .25s ease, color .25s ease, box-shadow .25s ease;
    white-space: nowrap;
}
.sup-sw-btn:hover { color: var(--text); }
.sup-sw-btn.active {
    background: linear-gradient(135deg, #0F1F3D 0%, #1d4ed8 100%);
    color: #fff;
    box-shadow: 0 4px 14px rgba(29,78,216,.30);
}
.sup-area.hidden { display: none !important; }

.sup-policy-stack {
    display: grid; grid-template-columns: 1fr; gap: 20px;
    max-width: 920px; margin: 0 auto;
}
.sup-policy-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 26px 26px 22px;
    box-shadow: 0 2px 14px rgba(15,23,42,.05);
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
}
.sup-policy-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 28px rgba(29,78,216,.12);
    border-color: rgba(29,78,216,.35);
}
.sup-policy-head { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.sup-policy-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: inline-flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #0F1F3D 0%, #1d4ed8 100%);
    color: #fff; font-size: 20px; flex-shrink: 0;
}
.sup-policy-title { margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--text); letter-spacing: -0.01em; }
.sup-policy-sub   { margin: 2px 0 0; font-size: 0.875rem; color: var(--text-muted); }

.sup-faq-list { list-style: none; padding: 0; margin: 0; }
.sup-faq-list li {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 12px 0;
    border-bottom: 1px dashed var(--border);
    font-size: 0.95rem; font-weight: 700; color: var(--text); line-height: 1.6;
}
.sup-faq-list li:last-child { border-bottom: none; padding-bottom: 0; }
.sup-faq-list li::before {
    color: #1d4ed8; font-weight: 900; flex-shrink: 0; min-width: 26px;
}
html[lang='ar'] .sup-faq-list li::before { content: 'س.'; }
html[lang='en'] .sup-faq-list li::before { content: 'Q.'; }
.sup-block { margin-bottom: 14px; }
.sup-block:last-child { margin-bottom: 0; }
.sup-block-title { margin: 0 0 6px; font-size: 0.95rem; font-weight: 800; color: #1d4ed8; }
.sup-block-text  { margin: 0; font-size: 0.9rem; line-height: 1.8; color: var(--text-muted); }
</style>

<!-- TAB SWITCHER -->
<div class="sup-switcher-wrap reveal">
    <div class="sup-switcher" role="tablist">
        <button type="button" class="sup-sw-btn active" role="tab"
                aria-controls="supArea1" aria-selected="true"
                data-area="info" onclick="switchSupportArea('info')"
                data-i18n="sp_tab_info">
            مركز المعلومات والسياسات
        </button>
        <button type="button" class="sup-sw-btn" role="tab"
                aria-controls="supArea2" aria-selected="false"
                data-area="contact" onclick="switchSupportArea('contact')"
                data-i18n="sp_tab_contact">
            التواصل والدعم الفني
        </button>
    </div>
</div>

<!-- AREA 1 — Information & Policies (default visible) -->
<section id="supArea1" class="sup-area reveal" role="tabpanel" aria-label="مركز المعلومات والسياسات">
    <div class="sup-policy-stack">

        <article class="sup-policy-card">
            <header class="sup-policy-head">
                <span class="sup-policy-icon" aria-hidden="true">📘</span>
                <div>
                    <h2 class="sup-policy-title" data-i18n="faq">الأسئلة الشائعة</h2>
                    <p class="sup-policy-sub" data-i18n="sp_faq_section_sub">أبرز الأسئلة التي يطرحها مستخدمو ميزان</p>
                </div>
            </header>
            <ul class="sup-faq-list">
                <li data-i18n="sp_faq_q1">هل ميزان مجاني بالكامل؟</li>
                <li data-i18n="sp_faq_q2">كيف أصدّر بياناتي (CSV / ZIP)؟</li>
                <li data-i18n="sp_faq_q3">كيف تعمل تنبيهات الضمان؟</li>
                <li data-i18n="sp_faq_q4">كيف أحذف حسابي نهائياً؟</li>
                <li data-i18n="sp_faq_q5">كم درجة أمان بياناتي في السحابة؟</li>
                <li data-i18n="sp_faq_q6">كيف أشارك مشروعاً مع شخص آخر؟</li>
                <li data-i18n="sp_faq_q7">كيف يحسب ميزان الربح والخسارة؟</li>
                <li data-i18n="sp_faq_q8">هل يمكنني إرفاق الفواتير والملفات؟</li>
            </ul>
        </article>

        <article class="sup-policy-card">
            <header class="sup-policy-head">
                <span class="sup-policy-icon" aria-hidden="true">📜</span>
                <div>
                    <h2 class="sup-policy-title" data-i18n="tos_title">شروط الخدمة</h2>
                    <p class="sup-policy-sub" data-i18n="sp_tos_sub">الاتفاقية المنظّمة لاستخدامك لمنصة ميزان</p>
                </div>
            </header>
            <div class="sup-block">
                <h3 class="sup-block-title" data-i18n="tos_c1_title">الاستخدام المقبول</h3>
                <p class="sup-block-text" data-i18n="tos_c1_body">يُحظَر استخدام منصة ميزان في أي نشاط أو عملية غير مشروعة. المنصة مُخصّصة فقط للإدارة المالية الشخصية وأعمال المشاريع المشروعة. أي إساءة استخدام تؤدي إلى تعليق الحساب فوراً ودون إشعار مسبق.</p>
            </div>
            <div class="sup-block">
                <h3 class="sup-block-title" data-i18n="tos_c2_title">إخلاء المسؤولية</h3>
                <p class="sup-block-text" data-i18n="tos_c2_body">ميزان أداة لتتبّع المصاريف فقط ولا تُقدّم استشارات ضريبية أو قانونية. لا تتحمّل المنصة أي مسؤولية قانونية عن أخطاء الحسابات الضريبية أو المالية الناتجة عن البيانات التي يُدخلها المستخدم.</p>
            </div>
            <div class="sup-block">
                <h3 class="sup-block-title" data-i18n="tos_c3_title">أمن كلمة المرور</h3>
                <p class="sup-block-text" data-i18n="tos_c3_body">أنت المسؤول الكامل عن سرية بيانات تسجيل الدخول الخاصة بك. لا تُشارك كلمة المرور مع أي طرف ثالث. وفي حال الاشتباه بأي اختراق، غيّر كلمة المرور فوراً وتواصل مع فريق الدعم.</p>
            </div>
        </article>

        <article class="sup-policy-card">
            <header class="sup-policy-head">
                <span class="sup-policy-icon" aria-hidden="true">🔒</span>
                <div>
                    <h2 class="sup-policy-title" data-i18n="pp_title">سياسة الخصوصية وحفظ البيانات</h2>
                    <p class="sup-policy-sub" data-i18n="sp_pp_sub">كيف نحمي بياناتك والمدة التي نحتفظ بها</p>
                </div>
            </header>
            <div class="sup-block">
                <h3 class="sup-block-title" data-i18n="pp_c1_title">البيانات التي نجمعها</h3>
                <p class="sup-block-text" data-i18n="pp_c1_body">نجمع فقط البيانات اللازمة لتشغيل الخدمة: البريد الإلكتروني والاسم والبيانات المالية التي تُدخلها. لا نجمع أي بيانات سلوكية أو بيانات تصفّح من أي نوع.</p>
            </div>
            <div class="sup-block">
                <h3 class="sup-block-title" data-i18n="pp_c2_title">المشاركة مع أطراف خارجية والإعلانات</h3>
                <p class="sup-block-text" data-i18n="pp_c2_body">صفر. لا نُشارك بياناتك مع أي طرف ثالث تحت أي ظرف. لا توجد إعلانات، ولا تتبُّع، ولا أدوات تحليلات خارجية من أي نوع.</p>
            </div>
            <div class="sup-block">
                <h3 class="sup-block-title" data-i18n="pp_c3_title">الاحتفاظ بالبيانات وحذفها</h3>
                <p class="sup-block-text" data-i18n="pp_c3_body">نحتفظ ببياناتك طوال فترة نشاط حسابك. وعند حذف الحساب، تُمحى جميع بياناتك (المشاريع، المصاريف، الملفات) من خوادمنا فوراً ونهائياً دون أي إمكانية للاسترداد.</p>
            </div>
        </article>

    </div>
</section>

<!-- AREA 2 — Contact & Support Tickets (hidden by default) -->
<section id="supArea2" class="sup-area hidden reveal" role="tabpanel" aria-label="التواصل والدعم الفني">

<!-- ===== TAB: Contact & Support ===== -->
    <div id="tabContact" class="support-wide reveal">

    <div class="glass-card">
        <div class="card-header">
            <h3 class="card-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <span data-i18n="contact_us">تواصل معنا</span>
            </h3>
        </div>
        <div class="card-body">
            <form id="contactForm" onsubmit="submitContact(event)">
                <div class="form-group">
                    <label class="form-label" for="contactSubject" data-i18n="contact_subject">الموضوع</label>
                    <select id="contactSubject" class="form-input" required>
                        <option value="" data-i18n="select_subject">اختر الموضوع...</option>
                        <option value="bug"     data-i18n="report_bug">الإبلاغ عن خطأ</option>
                        <option value="feature" data-i18n="feature_request">اقتراح ميزة</option>
                        <option value="account" data-i18n="account_issue">مشكلة في الحساب</option>
                        <option value="other"   data-i18n="other">أخرى</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="contactMessage" data-i18n="message">الرسالة</label>
                    <textarea id="contactMessage" class="form-input" rows="5" required
                        data-i18n-placeholder="write_message_here"
                        placeholder="اكتب رسالتك هنا..."
                        maxlength="2000"></textarea>
                    <div class="form-hint"><span id="charCount">0</span> / 2000</div>
                </div>
                <button type="submit" class="btn btn-primary btn-full" id="contactBtn">
                    <span data-i18n="send_message">إرسال الرسالة</span>
                </button>
            </form>
            <div id="contactSuccess" style="display:none; text-align:center; padding:32px 16px;">
                <div style="font-size:52px; margin-bottom:14px;">✅</div>
                <h3 data-i18n="message_sent">تم إرسال رسالتك بنجاح</h3>
                <p class="text-muted" data-i18n="message_sent_desc">سنرد عليك في أقرب وقت ممكن.</p>
            </div>
        </div>
    </div><!-- /contact glass-card -->

    <!-- Tickets sub-section -->
    <div class="glass-card" style="margin-top:24px;" id="ticketsCard">
        <div class="card-header">
            <h3 class="card-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <span data-i18n="my_tickets">تذاكري</span>
            </h3>
            <button class="btn btn-outline btn-sm" onclick="loadTickets(true)" id="refreshTicketsBtn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                <span data-i18n="refresh">تحديث</span>
            </button>
        </div>
        <div id="ticketsContainer" style="padding:0 0 8px;">
            <div class="tickets-loading" style="padding:40px;text-align:center;color:var(--text-muted);">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="animation:spin 1s linear infinite;display:inline-block;margin-bottom:10px;" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke-opacity=".25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-opacity=".9"/></svg>
                <p data-i18n="loading">جاري التحميل...</p>
            </div>
        </div>
    </div><!-- /tickets glass-card -->

    </div><!-- /tabContact -->

</section><!-- /supArea2 -->

<style>
/* ── Support Tabs ── */
.support-tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 20px;
    border-bottom: 2px solid var(--border);
    padding-bottom: 0;
}

.s-tab {
    padding: 10px 20px;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-muted);
    font-family: inherit;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: color .2s, border-color .2s;
    border-radius: var(--radius-sm) var(--radius-sm) 0 0;
}

.s-tab:hover { color: var(--text); }
.s-tab.active { color: var(--primary); border-bottom-color: var(--primary); }

/* ── Wide panel ── */
.support-wide { max-width: 720px; width: 100%; }
.support-grid { display: flex; flex-direction: column; align-items: flex-start; gap: 24px; }

/* ── FAQ ── */
.faq-list { padding: 8px 0; }
.faq-item { border-bottom: 1px solid var(--border); }
.faq-item:last-child { border-bottom: none; }

.faq-question {
    width: 100%; padding: 15px 24px; background: none; border: none;
    cursor: pointer; display: flex; align-items: center; justify-content: space-between;
    font-family: inherit; font-size: 14.5px; font-weight: 600; color: var(--text);
    text-align: right; transition: background .15s; gap: 12px;
}
.faq-question:hover { background: var(--surface2); border-radius: 6px; }
.faq-arrow { flex-shrink: 0; color: var(--text-muted); transition: transform .25s ease; }
.faq-item.open .faq-arrow { transform: rotate(180deg); }
.faq-answer { max-height: 0; overflow: hidden; transition: max-height .3s ease, padding .3s ease; padding: 0 24px; }
.faq-item.open .faq-answer { max-height: 300px; padding: 4px 24px 18px; }
.faq-answer p { font-size: 14px; color: var(--text-muted); line-height: 1.85; margin: 0; }

/* ── Contact form ── */
.form-hint { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
#contactForm .form-label { display: block; font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
#contactForm .form-input {
    width: 100%; padding: 10px 14px; background: var(--surface2);
    border: 1px solid var(--border); border-radius: var(--radius-sm);
    color: var(--text); font-size: 14px; font-family: inherit;
    transition: border-color .2s; box-sizing: border-box;
}
#contactForm .form-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(3,105,161,.1); }
#contactForm textarea.form-input { resize: vertical; min-height: 120px; }

/* ── Ticket card ── */
.ticket-item { border-bottom: 1px solid var(--border); padding: 18px 24px; }
.ticket-item:last-child { border-bottom: none; }
.ticket-item-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 8px; }
.ticket-subject { font-size: 14.5px; font-weight: 700; color: var(--text); flex: 1; }
.ticket-date { font-size: 11.5px; color: var(--text-muted); white-space: nowrap; flex-shrink: 0; }
.ticket-message-preview { font-size: 13px; color: var(--text-muted); line-height: 1.65; margin-bottom: 10px; }
.ticket-status-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

.tk-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 11px; border-radius: 12px; font-size: 11px; font-weight: 700;
}
.tk-open   { background: rgba(245,158,11,.12); color: #B45309; }
.tk-closed { background: rgba(16,185,129,.10); color: var(--success); }
.tk-awaiting { background: rgba(139,92,246,.1); color: #7c3aed; }

/* ── Thread toggle button ── */
.btn-thread-toggle {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 12px; background: var(--surface2); border: 1px solid var(--border);
    border-radius: 8px; color: var(--text-muted); font-size: 12px; font-weight: 600;
    cursor: pointer; font-family: inherit; transition: border-color .2s, color .2s;
}
.btn-thread-toggle:hover { border-color: var(--primary); color: var(--primary); }
.btn-thread-toggle.open { border-color: var(--primary); color: var(--primary); }

/* ── Conversation thread ── */
.ticket-thread {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.msg-row { display: flex; flex-direction: column; }
.msg-row.msg-user  { align-items: flex-end; }
.msg-row.msg-admin { align-items: flex-start; }

.msg-label {
    font-size: 10.5px; font-weight: 700; color: var(--text-muted);
    margin-bottom: 4px; display: flex; align-items: center; gap: 5px;
}
.msg-user .msg-label { justify-content: flex-end; }

.msg-body {
    max-width: 86%;
    padding: 10px 14px;
    border-radius: 12px;
    font-size: 13.5px;
    line-height: 1.7;
    white-space: pre-wrap;
    word-break: break-word;
}

.msg-user  .msg-body { background: var(--primary); color: #fff; border-radius: 12px 12px 4px 12px; }
.msg-admin .msg-body {
    background: var(--surface2); color: var(--text);
    border: 1px solid var(--border);
    border-inline-start: 3px solid var(--primary);
    border-radius: 12px 12px 12px 4px;
}

.msg-time { font-size: 10.5px; color: var(--text-muted); margin-top: 3px; }

/* ── User reply area ── */
.user-reply-area {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed var(--border);
}

.reply-input-field {
    width: 100%; padding: 9px 12px;
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: 8px; color: var(--text); font-size: 13px;
    font-family: inherit; resize: vertical; min-height: 60px;
    box-sizing: border-box; transition: border-color .2s;
    margin-bottom: 8px;
}
.reply-input-field:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(3,105,161,.1); }

.btn-send-reply {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 16px; background: var(--primary); border: none;
    border-radius: 8px; color: #fff; font-size: 13px; font-weight: 700;
    font-family: inherit; cursor: pointer; transition: opacity .2s;
}
.btn-send-reply:hover { opacity: .9; }
.btn-send-reply:disabled { opacity: .5; cursor: not-allowed; }

/* ── Ticket star rating widget ── */
.ticket-rating-box {
    margin-top: 14px;
    padding: 14px 16px;
    background: rgba(245,158,11,.06);
    border: 1px solid rgba(245,158,11,.22);
    border-radius: 10px;
}
.rating-prompt { font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 10px; }
.tk-star-row { display: flex; gap: 4px; direction: ltr; margin-bottom: 10px; }
.tk-star {
    font-size: 26px; color: #d1d5db; background: none; border: none;
    cursor: pointer; padding: 0 2px; transition: color .12s, transform .1s; line-height: 1;
}
.tk-star.active  { color: #f59e0b; }
.tk-star:hover   { color: #f59e0b; transform: scale(1.15); }
.rating-comment-field {
    width: 100%; padding: 7px 10px; background: var(--surface2);
    border: 1px solid var(--border); border-radius: 8px; color: var(--text);
    font-size: 13px; font-family: inherit; box-sizing: border-box;
    margin-bottom: 8px; transition: border-color .2s; resize: none; height: 52px;
}
.rating-comment-field:focus { outline: none; border-color: var(--primary); }
.btn-submit-rating {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 16px; background: #f59e0b; border: none;
    border-radius: 8px; color: #fff; font-size: 13px; font-weight: 700;
    font-family: inherit; cursor: pointer; transition: opacity .2s;
}
.btn-submit-rating:hover { opacity: .88; }
.btn-submit-rating:disabled { opacity: .5; cursor: not-allowed; }

/* ── Existing rating display ── */
.rating-display {
    display: flex; align-items: center; gap: 8px;
    margin-top: 12px; font-size: 12px; color: var(--text-muted);
}
.rating-stars-display { color: #f59e0b; font-size: 15px; direction: ltr; }

/* ── Empty / loading states ── */
.tickets-empty { padding: 48px 24px; text-align: center; color: var(--text-muted); }
.tickets-empty svg { margin-bottom: 14px; opacity: .4; }
.tickets-empty h4 { font-size: 15px; font-weight: 700; margin: 0 0 6px; color: var(--text); }
.tickets-empty p  { font-size: 13px; margin: 0; }

@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* ── Policy sections ── */
.policy-section {
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}

.policy-clause {
    padding: 13px 16px;
    border-bottom: 1px solid var(--border);
    display: flex; gap: 12px; align-items: flex-start;
}
.policy-clause:last-child { border-bottom: none; }

.policy-clause-icon {
    width: 30px; height: 30px; flex-shrink: 0;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    margin-top: 1px;
}
.policy-clause-body { flex: 1; min-width: 0; }
.policy-clause-title { font-size: 12.5px; font-weight: 700; color: var(--text); margin-bottom: 3px; }
.policy-clause-text  { font-size: 12px; color: var(--text-muted); line-height: 1.85; margin: 0; }

.ci-blue   { background: rgba(59,130,246,.10);  color: #3b82f6; }
.ci-amber  { background: rgba(245,158,11,.10);  color: #d97706; }
.ci-danger { background: rgba(239,68,68,.10);   color: var(--danger); }
.ci-teal   { background: rgba(16,185,129,.10);  color: var(--success); }
.ci-violet { background: rgba(139,92,246,.10);  color: #7c3aed; }

@media (max-width: 600px) {
    .s-tab { padding: 10px 14px; font-size: 13px; }
    .ticket-item { padding: 14px 16px; }
    .faq-question { padding: 13px 16px; }
    .msg-body { max-width: 96%; }
    .policy-clause { padding: 11px 14px; }
}
</style>

<script>
// ── Two-area SPA switcher ─────────────────────────────────────
let __ticketsLoadedOnce = false;
// Switches the support area.
function switchSupportArea(area) {
    const a1 = document.getElementById('supArea1');
    const a2 = document.getElementById('supArea2');
    const isContact = area === 'contact';
    a1?.classList.toggle('hidden', isContact);
    a2?.classList.toggle('hidden', !isContact);
    document.querySelectorAll('.sup-sw-btn').forEach(b => {
        const on = b.dataset.area === area;
        b.classList.toggle('active', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    if (isContact && typeof loadTickets === 'function' && !__ticketsLoadedOnce) {
        __ticketsLoadedOnce = true;
        loadTickets();
    }
}
window.switchSupportArea = switchSupportArea;
// Backwards-compat shim for any leftover callers
function switchTab(tab, load) {
    switchSupportArea(tab === 'faq' ? 'info' : 'contact');
    if (load && typeof loadTickets === 'function') loadTickets();
}

// ── FAQ accordion ─────────────────────────────────────────────
function toggleFaq(btn) {
    const item = btn.closest('.faq-item');
    const wasOpen = item.classList.contains('open');
    document.querySelectorAll('.faq-item.open').forEach(el => el.classList.remove('open'));
    if (!wasOpen) item.classList.add('open');
}

// ── Character counter ─────────────────────────────────────────
document.getElementById('contactMessage').addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
});

// ── Submit contact form ───────────────────────────────────────
async function submitContact(e) {
    e.preventDefault();
    const isAr    = (localStorage.getItem('mizan_lang') || 'ar') === 'ar';
    const subject = document.getElementById('contactSubject').value;
    const message = document.getElementById('contactMessage').value.trim();
    const btn     = document.getElementById('contactBtn');

    if (!subject || !message) {
        if (typeof showToast === 'function') showToast(t('fill_all_fields'), 'warning');
        return;
    }

    setButtonBusy(btn, true);

    try {
        const res  = await mizanFetch('/api/support.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'submit_ticket',
                subject,
                message,
                csrf_token: (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
            })
        });
        const data = await res.json();

        if (data.success) {
            document.getElementById('contactForm').style.display    = 'none';
            document.getElementById('contactSuccess').style.display = '';
            if (typeof showToast === 'function') showToast(t('ticket_submitted'), 'success');
        } else {
            if (typeof showToast === 'function') showToast(t(data.error) || t('generic_error'), 'error');
            setButtonBusy(btn, false);
        }
    } catch {
        if (typeof showToast === 'function') showToast(t('server_error'), 'error');
        setButtonBusy(btn, false);
    }
}

// ── Load & render tickets ─────────────────────────────────────
async function loadTickets(showLoading) {
    const container = document.getElementById('ticketsContainer');
    const isAr      = (localStorage.getItem('mizan_lang') || 'ar') === 'ar';

    if (showLoading) {
        container.innerHTML = `
            <div style="padding:32px;text-align:center;color:var(--text-muted);">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     style="animation:spin 1s linear infinite;display:inline-block;margin-bottom:8px;" aria-hidden="true">
                    <circle cx="12" cy="12" r="10" stroke-opacity=".2"/>
                    <path d="M12 2a10 10 0 0 1 10 10" stroke-opacity=".9"/>
                </svg>
            </div>`;
    }

    try {
        const res  = await mizanFetch('/api/support.php?action=list_tickets');
        const data = await res.json();

        if (!data.success || !data.tickets?.length) {
            container.innerHTML = `
                <div class="tickets-empty">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <h4>${isAr ? 'لا توجد تذاكر بعد' : 'No tickets yet'}</h4>
                    <p>${isAr ? 'أرسل رسالة لفريق الدعم من تبويب "تواصل معنا"' : 'Send a message via the Contact tab'}</p>
                </div>`;
            return;
        }

        const subjectLabels = {
            bug:     isAr ? 'الإبلاغ عن خطأ'       : 'Bug Report',
            feature: isAr ? 'اقتراح ميزة'           : 'Feature Request',
            account: isAr ? 'مشكلة في الحساب'       : 'Account Issue',
            other:   isAr ? 'أخرى'                  : 'Other'
        };

        // Build each ticket card via innerHTML (all user content escaped via escHtml)
        const html = data.tickets.map(tk => buildTicketCard(tk, isAr, subjectLabels)).join('');
        container.innerHTML = html;

        // Attach star hover listeners after DOM is ready
        initTicketStars();

    } catch {
        container.innerHTML = `<div style="padding:24px;color:var(--danger);font-size:13px;text-align:center;">${isAr ? 'تعذّر تحميل التذاكر' : 'Could not load tickets'}</div>`;
    }
}

// ── Build a single ticket card HTML ──────────────────────────
function buildTicketCard(tk, isAr, subjectLabels) {
    const isOpen   = tk.status === 'open';
    const subject  = escHtml(subjectLabels[tk.subject] || tk.subject);
    const preview  = escHtml(tk.message.length > 160 ? tk.message.slice(0, 160) + '…' : tk.message);
    const date     = new Date(tk.created_at).toLocaleDateString(isAr ? 'ar-SA' : 'en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    const hasReplies = tk.replies && tk.replies.length > 0;

    // Status badge
    let badgeHtml;
    if (isOpen && hasReplies) {
        // Admin replied but thread is still open (awaiting user follow-up)
        badgeHtml = `<span class="tk-badge tk-awaiting">${isAr ? 'في الانتظار' : 'Awaiting'}</span>`;
    } else if (isOpen) {
        badgeHtml = `<span class="tk-badge tk-open">${isAr ? 'مفتوحة' : 'Open'}</span>`;
    } else {
        badgeHtml = `<span class="tk-badge tk-closed">✓ ${isAr ? 'مغلقة' : 'Closed'}</span>`;
    }

    // Thread toggle button (always shown so user can reply or read)
    const toggleLabel = hasReplies
        ? (isAr ? 'عرض المحادثة' : 'View Thread')
        : (isAr ? 'إضافة رد' : 'Add Reply');

    const toggleBtn = `
        <button class="btn-thread-toggle" id="toggle-${tk.id}" onclick="toggleThread(${tk.id})" aria-expanded="false">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <span id="toggle-label-${tk.id}">${escHtml(toggleLabel)}</span>
        </button>`;

    // Conversation thread HTML
    const threadHtml = buildThread(tk, isAr);

    // Star rating widget (only for closed + unrated tickets)
    let ratingHtml = '';
    if (!isOpen && tk.user_rating === null) {
        ratingHtml = buildRatingWidget(tk.id, isAr);
    } else if (tk.user_rating !== null) {
        const filledStars = '★'.repeat(tk.user_rating) + '☆'.repeat(5 - tk.user_rating);
        ratingHtml = `
            <div class="rating-display">
                <span class="rating-stars-display">${filledStars}</span>
                <span>${isAr ? 'تقييمك: ' + tk.user_rating + '/5' : 'Your rating: ' + tk.user_rating + '/5'}</span>
                ${tk.rating_comment ? `<span style="font-style:italic;">"${escHtml(tk.rating_comment)}"</span>` : ''}
            </div>`;
    }

    // User reply area
    const replyAreaHtml = `
        <div class="user-reply-area">
            <textarea class="reply-input-field" id="reply-field-${tk.id}"
                placeholder="${isAr ? 'أضف رداً على التذكرة...' : 'Add a reply...'}"
                rows="2" maxlength="2000"></textarea>
            <button class="btn-send-reply" id="reply-btn-${tk.id}" onclick="submitUserReply(${tk.id})">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                ${isAr ? 'إرسال' : 'Send'}
            </button>
        </div>`;

    return `
        <div class="ticket-item" id="ticket-card-${tk.id}">
            <div class="ticket-item-head">
                <div class="ticket-subject">${subject}</div>
                <div class="ticket-date">${escHtml(date)}</div>
            </div>
            <div class="ticket-message-preview">${preview}</div>
            <div class="ticket-status-row">
                ${badgeHtml}
                ${toggleBtn}
            </div>
            <div class="ticket-thread" id="thread-${tk.id}" style="display:none;">
                ${threadHtml}
                ${replyAreaHtml}
                ${ratingHtml}
            </div>
        </div>`;
}

// ── Build the conversation thread HTML ────────────────────────
function buildThread(tk, isAr) {
    const allMsgs = [];

    // Original user message always first
    allMsgs.push({
        sender_type: 'user',
        message: tk.message,
        created_at: tk.created_at
    });

    // Then all replies in chronological order
    if (tk.replies && tk.replies.length > 0) {
        tk.replies.forEach(r => allMsgs.push(r));
    }

    return allMsgs.map((msg, idx) => {
        const isUser   = msg.sender_type === 'user';
        const label    = isUser ? (isAr ? 'أنت' : 'You') : (isAr ? 'فريق الدعم' : 'Support Team');
        const msgDate  = msg.created_at ? new Date(msg.created_at).toLocaleString(isAr ? 'ar-SA' : 'en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';

        // SVG icon for label
        const iconSvg = isUser
            ? `<svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>`
            : `<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M12 2a5 5 0 0 1 5 5v2h1a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h1V7a5 5 0 0 1 5-5z"/></svg>`;

        return `
            <div class="msg-row msg-${isUser ? 'user' : 'admin'}" data-msg-idx="${idx}">
                <div class="msg-label">${iconSvg} ${escHtml(label)}</div>
                <div class="msg-body">${escHtml(msg.message)}</div>
                ${msgDate ? `<div class="msg-time">${escHtml(msgDate)}</div>` : ''}
            </div>`;
    }).join('');
}

// ── Build star rating widget HTML ─────────────────────────────
function buildRatingWidget(ticketId, isAr) {
    const stars = [1, 2, 3, 4, 5].map(n =>
        `<button type="button" class="tk-star" data-tid="${ticketId}" data-v="${n}" aria-label="${n} ${isAr ? 'نجوم' : 'stars'}">★</button>`
    ).join('');

    return `
        <div class="ticket-rating-box" id="rating-box-${ticketId}">
            <div class="rating-prompt">${isAr ? 'كيف كانت تجربتك مع فريق الدعم؟' : 'How was your support experience?'}</div>
            <div class="tk-star-row" id="star-row-${ticketId}" data-selected="0">${stars}</div>
            <textarea class="rating-comment-field" id="rating-comment-${ticketId}"
                placeholder="${isAr ? 'تعليق اختياري...' : 'Optional comment...'}"
                rows="2"></textarea>
            <button type="button" class="btn-submit-rating" id="rating-submit-${ticketId}"
                onclick="submitTicketRating(${ticketId})">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                ${isAr ? 'إرسال التقييم' : 'Submit Rating'}
            </button>
        </div>`;
}

// ── Toggle conversation thread ────────────────────────────────
function toggleThread(ticketId) {
    const thread  = document.getElementById('thread-' + ticketId);
    const toggleBtn = document.getElementById('toggle-' + ticketId);
    if (!thread) return;

    const isOpen = thread.style.display !== 'none';
    thread.style.display = isOpen ? 'none' : '';
    toggleBtn?.classList.toggle('open', !isOpen);
    toggleBtn?.setAttribute('aria-expanded', String(!isOpen));

    // Focus the reply textarea when opening
    if (!isOpen) {
        setTimeout(() => {
            document.getElementById('reply-field-' + ticketId)?.focus();
        }, 50);
    }
}

// ── Set star hover state for ticket rating ────────────────────
function initTicketStars() {
    document.querySelectorAll('.tk-star').forEach(star => {
        star.addEventListener('mouseenter', () => {
            const row = star.closest('.tk-star-row');
            const val = parseInt(star.dataset.v);
            row.querySelectorAll('.tk-star').forEach(s => {
                s.classList.toggle('active', parseInt(s.dataset.v) <= val);
            });
        });

        star.addEventListener('mouseleave', () => {
            const row = star.closest('.tk-star-row');
            const selected = parseInt(row.dataset.selected || '0');
            row.querySelectorAll('.tk-star').forEach(s => {
                s.classList.toggle('active', parseInt(s.dataset.v) <= selected);
            });
        });

        star.addEventListener('click', () => {
            const row = star.closest('.tk-star-row');
            const val = parseInt(star.dataset.v);
            row.dataset.selected = val;
            row.querySelectorAll('.tk-star').forEach(s => {
                s.classList.toggle('active', parseInt(s.dataset.v) <= val);
            });
        });
    });
}

// ── User submits a reply to a ticket ─────────────────────────
async function submitUserReply(ticketId) {
    const isAr   = (localStorage.getItem('mizan_lang') || 'ar') === 'ar';
    const field  = document.getElementById('reply-field-' + ticketId);
    const btn    = document.getElementById('reply-btn-' + ticketId);
    const message = (field?.value || '').trim();

    if (!message) {
        if (typeof showToast === 'function') showToast(t('write_message_required'), 'warning');
        return;
    }

    if (btn) { btn.disabled = true; }

    try {
        const res  = await mizanFetch('/api/support.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add_reply',
                ticket_id: ticketId,
                message,
                csrf_token: (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
            })
        });
        const data = await res.json();

        if (data.success) {
            // Clear field
            if (field) field.value = '';

            // Append new message bubble directly before the reply area
            const thread    = document.getElementById('thread-' + ticketId);
            const replyArea = thread?.querySelector('.user-reply-area');

            if (thread && replyArea) {
                const now     = new Date();
                const dateStr = now.toLocaleString(isAr ? 'ar-SA' : 'en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
                const icon    = `<svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>`;

                const row = document.createElement('div');
                row.className = 'msg-row msg-user';

                const label = document.createElement('div');
                label.className = 'msg-label';
                label.innerHTML = icon + ' ';
                const labelText = document.createElement('span');
                labelText.textContent = isAr ? 'أنت' : 'You';
                label.appendChild(labelText);

                const body = document.createElement('div');
                body.className = 'msg-body';
                body.textContent = message;

                const time = document.createElement('div');
                time.className = 'msg-time';
                time.textContent = dateStr;

                row.appendChild(label);
                row.appendChild(body);
                row.appendChild(time);
                thread.insertBefore(row, replyArea);
            }

            // Update status badge to "Open"
            const card = document.getElementById('ticket-card-' + ticketId);
            const badge = card?.querySelector('.tk-badge');
            if (badge) {
                badge.className = 'tk-badge tk-open';
                badge.textContent = isAr ? 'مفتوحة' : 'Open';
            }

            if (typeof showToast === 'function') showToast(t('reply_sent_ok'), 'success');
        } else {
            if (typeof showToast === 'function') showToast(t(data.error) || t('generic_error'), 'error');
        }
    } catch {
        if (typeof showToast === 'function') showToast(t('connection_error'), 'error');
    } finally {
        if (btn) btn.disabled = false;
    }
}

// ── Submit ticket satisfaction rating ────────────────────────
async function submitTicketRating(ticketId) {
    const isAr   = (localStorage.getItem('mizan_lang') || 'ar') === 'ar';
    const row    = document.getElementById('star-row-' + ticketId);
    const rating = parseInt(row?.dataset.selected || '0');
    const comment = (document.getElementById('rating-comment-' + ticketId)?.value || '').trim();
    const btn    = document.getElementById('rating-submit-' + ticketId);

    if (!rating) {
        if (typeof showToast === 'function') showToast(t('select_rating'), 'warning');
        return;
    }

    if (btn) btn.disabled = true;

    try {
        const res  = await mizanFetch('/api/support.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'rate_ticket',
                ticket_id: ticketId,
                rating,
                comment,
                csrf_token: (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
            })
        });
        const data = await res.json();

        if (data.success) {
            // Replace rating widget with a thank-you display
            const box = document.getElementById('rating-box-' + ticketId);
            if (box) {
                const filledStars = '★'.repeat(rating) + '☆'.repeat(5 - rating);
                const thanksDiv = document.createElement('div');
                thanksDiv.className = 'rating-display';
                thanksDiv.innerHTML = `
                    <span class="rating-stars-display">${filledStars}</span>
                    <span>${isAr ? 'شكراً لتقييمك ' + rating + '/5' : 'Thank you for your rating ' + rating + '/5'}</span>`;
                box.replaceWith(thanksDiv);
            }
            if (typeof showToast === 'function') showToast(t('rating_submitted_ok'), 'success');
        } else {
            if (typeof showToast === 'function') showToast(t(data.error) || t('generic_error'), 'error');
            if (btn) btn.disabled = false;
        }
    } catch {
        if (typeof showToast === 'function') showToast(t('connection_error'), 'error');
        if (btn) btn.disabled = false;
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
