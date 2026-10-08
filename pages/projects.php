<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: projects.php
 * PURPOSE: Projects listing page with filters, search, and quick actions.
 * ========================================================================
 */
require_once '../includes/auth.php';
require_once '../config/db.php';

$page_title = 'المشاريع';
require_once '../includes/header.php';
// ─────────────────────────────────────────────────────────────────────────
// END PHP BOOTSTRAP — BEGIN HTML VIEW (project grid, filters, modals).
// Data is loaded asynchronously via /api/projects.php?action=list, not server-rendered,
// so this file is mostly markup + skeleton placeholders.
// ─────────────────────────────────────────────────────────────────────────
?>

<!-- ===== Page Header ===== -->
<div class="prj-header reveal">
    <div class="prj-header-text">
        <h1 class="page-title" data-i18n="projects">المشاريع</h1>
        <p class="text-muted" data-i18n="projects_desc">جميع مشاريعك في مكان واحد</p>
    </div>
    <div class="prj-controls">
        <div class="prj-search-wrap">
            <span class="prj-search-icon">🔍</span>
            <input type="text" id="projectSearch" class="prj-search-input" placeholder="بحث في المشاريع..."
                data-i18n="search_projects" oninput="filterProjects()">
        </div>
        <select class="prj-filter-select" id="statusFilter" onchange="filterProjects()">
            <option value="all" data-i18n="all_statuses">جميع الحالات</option>
            <option value="active" data-i18n="filter_active">نشط</option>
            <option value="done" data-i18n="filter_done">مكتمل</option>
            <option value="archived" data-i18n="filter_archived">مؤرشف</option>
        </select>
        <!-- "Upload File" button — opens the shared Smart Upload modal (from includes/smart_upload_modal.php) -->
        <button class="btn-mz-ghost" onclick="openSmartUpload()">
            <span>☁️</span> <span data-i18n="upload_file">رفع ملف جديد</span>
        </button>
        <!-- "New Project" button — opens the create-project flow (defined in main.js) -->
        <button class="btn-mz-primary" onclick="window.openProjectCreate()">
            <span>+</span> <span data-i18n="add_project">مشروع جديد</span>
        </button>
    </div>
</div>

<!-- ===== Projects Grid ===== -->
<div class="prj-grid" id="projectsGrid">
    <!-- Skeleton placeholders — shown on initial load -->
    <div class="prj-card-skel" id="skelWrap">
        <div class="prj-skel-card">
            <div class="prj-skel-icon skeleton"></div>
            <div class="prj-skel-lines">
                <div class="skeleton" style="width:60%;height:16px;margin-bottom:10px;"></div>
                <div class="skeleton" style="width:40%;height:12px;margin-bottom:14px;"></div>
                <div class="skeleton" style="width:100%;height:8px;border-radius:4px;"></div>
            </div>
        </div>
        <div class="prj-skel-card">
            <div class="prj-skel-icon skeleton"></div>
            <div class="prj-skel-lines">
                <div class="skeleton" style="width:55%;height:16px;margin-bottom:10px;"></div>
                <div class="skeleton" style="width:35%;height:12px;margin-bottom:14px;"></div>
                <div class="skeleton" style="width:100%;height:8px;border-radius:4px;"></div>
            </div>
        </div>
        <div class="prj-skel-card">
            <div class="prj-skel-icon skeleton"></div>
            <div class="prj-skel-lines">
                <div class="skeleton" style="width:70%;height:16px;margin-bottom:10px;"></div>
                <div class="skeleton" style="width:45%;height:12px;margin-bottom:14px;"></div>
                <div class="skeleton" style="width:100%;height:8px;border-radius:4px;"></div>
            </div>
        </div>
        <div class="prj-skel-card">
            <div class="prj-skel-icon skeleton"></div>
            <div class="prj-skel-lines">
                <div class="skeleton" style="width:50%;height:16px;margin-bottom:10px;"></div>
                <div class="skeleton" style="width:38%;height:12px;margin-bottom:14px;"></div>
                <div class="skeleton" style="width:100%;height:8px;border-radius:4px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- ===== Empty State ===== -->
<div class="prj-empty" id="emptyState" style="display:none;">
    <div class="prj-empty-icon">📂</div>
    <h3 data-i18n="no_projects">لا توجد مشاريع</h3>
    <p class="text-muted" data-i18n="no_projects_desc">ابدأ بإنشاء مشروعك الأول لتتبع مصاريفك</p>
    <button class="btn btn-primary btn-lg" onclick="window.openProjectCreate()" style="margin-top:16px;">
        <span>+</span> <span data-i18n="add_project">مشروع جديد</span>
    </button>
</div>

<!-- ===== Counter Strip ===== -->
<div class="prj-counter" id="counterStrip" style="display:none;">
    <span class="prj-counter-item"><strong id="cntTotal">0</strong> <span data-i18n="total">إجمالي</span></span>
    <span class="prj-counter-sep">·</span>
    <span class="prj-counter-item text-success"><strong id="cntActive">0</strong> <span
            data-i18n="status_active">نشط</span></span>
    <span class="prj-counter-sep">·</span>
    <span class="prj-counter-item text-secondary"><strong id="cntDone">0</strong> <span
            data-i18n="status_done">مكتمل</span></span>
    <span class="prj-counter-sep">·</span>
    <span class="prj-counter-item" style="color:var(--text-muted);"><strong id="cntArchived">0</strong> <span
            data-i18n="status_archived">مؤرشف</span></span>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* ============================================================
   Projects Listing — Premium Card Grid
   ============================================================ */

/* ── Header ───────────────────────────────────────────────── */
.prj-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 20px;
}

.prj-controls {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.prj-search-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.prj-search-icon {
    position: absolute;
    inset-inline-start: 12px;
    font-size: 14px;
    pointer-events: none;
    z-index: 1;
    opacity: 0.6;
}

.prj-search-input {
    padding: 8px 14px 8px 36px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text);
    font-family: 'Tajawal', sans-serif;
    font-size: 13px;
    font-weight: 500;
    min-width: 180px;
    transition: border-color 0.2s;
}

.prj-search-input:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(0, 108, 53, 0.08);
}

.prj-filter-select {
    padding: 8px 14px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text);
    font-family: 'Tajawal', sans-serif;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
}

/* ── Counter Strip ────────────────────────────────────────── */
.prj-counter {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0 0;
    font-size: 14px;
    color: var(--text-muted);
}

.prj-counter-item strong {
    color: var(--text);
}

.prj-counter-sep {
    opacity: 0.3;
    font-size: 18px;
}

/* ── Grid ─────────────────────────────────────────────────── */
.prj-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 22px;
    margin-bottom: 8px;
}

@media (max-width: 640px) {
    .prj-grid {
        grid-template-columns: 1fr;
    }
}

/* ── Project Card ─────────────────────────────────────────── */
.prj-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 24px;
    cursor: pointer;
    position: relative;
    overflow: visible !important;
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    /* Stagger entrance */
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 0.55s cubic-bezier(0.22, 1, 0.36, 1),
        transform 0.55s cubic-bezier(0.22, 1, 0.36, 1),
        border-color 0.25s, box-shadow 0.3s;
}

.prj-card.entered {
    opacity: 1;
    transform: translateY(0);
}

.prj-card:hover {
    border-color: var(--primary);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.07);
    transform: translateY(-4px);
}

.prj-card.menu-open {
    z-index: 10;
}

.prj-card.entered:hover {
    transform: translateY(-4px);
}

/* Color accent strip at top — clipped to card's rounded corners */
.prj-card::before {
    content: '';
    position: absolute;
    top: 0;
    inset-inline-start: 0;
    inset-inline-end: 0;
    height: 4px;
    background: var(--card-accent, var(--primary));
    border-top-left-radius: var(--radius-lg);
    border-top-right-radius: var(--radius-lg);
    pointer-events: none;
    z-index: 1;
}

/* ── Card Inner Layout ────────────────────────────────────── */
.prj-card-top {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 16px;
}

.prj-card-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
    transition: transform 0.3s;
}

.prj-card:hover .prj-card-icon {
    transform: scale(1.08);
}

.prj-card-info {
    flex: 1;
    min-width: 0;
}

.prj-card-name {
    font-size: 16px;
    font-weight: 700;
    color: var(--text);
    margin: 0 0 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.prj-card-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 12px;
    color: var(--text-muted);
}

/* Status badges */
.prj-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.prj-badge-active {
    background: rgba(0, 108, 53, 0.10);
    color: var(--success);
}

.prj-badge-done {
    background: rgba(3, 105, 161, 0.10);
    color: var(--info);
}

.prj-badge-archived {
    background: var(--surface2);
    color: var(--text-muted);
}

/* ── Budget Progress Bar ──────────────────────────────────── */
.prj-budget-section {
    margin-top: 6px;
}

.prj-budget-header {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: var(--text-muted);
    margin-bottom: 6px;
}

.prj-budget-header strong {
    color: var(--text);
    font-weight: 600;
}

.prj-progress-track {
    height: 6px;
    background: var(--surface2);
    border-radius: 3px;
    overflow: hidden;
}

.prj-progress-fill {
    height: 100%;
    border-radius: 3px;
    width: 0;
    transition: width 1s cubic-bezier(0.22, 1, 0.36, 1),
        background 0.4s;
}

/* ── Stats Row ────────────────────────────────────────────── */
.prj-card-stats {
    display: flex;
    gap: 16px;
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
    font-size: 13px;
}

.prj-stat {
    display: flex;
    align-items: center;
    gap: 5px;
    color: var(--text-muted);
}

.prj-stat strong {
    color: var(--text);
    font-weight: 600;
}

/* ── Card Action Buttons (top-corner) ─────────────────────── */
.prj-card-actions {
    position: absolute;
    top: 14px;
    inset-inline-end: 14px;
    display: flex;
    gap: 4px;
    z-index: 5;
}

.prj-menu-trigger {
    background: var(--surface2);
    border: 1px solid var(--bdr);
    font-size: 18px;
    line-height: 1;
    color: var(--text-muted);
    cursor: pointer;
    padding: 2px 8px 6px 8px;
    border-radius: 6px;
    transition: all 0.2s;
    font-weight: 700;
}

.prj-menu-trigger:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--surface);
}

.prj-card-menu {
    position: absolute;
    top: calc(100% + 6px);
    inset-inline-end: 0;
    min-width: 180px;
    background: var(--surface);
    border: 1px solid var(--bdr);
    border-radius: var(--radius);
    box-shadow: var(--gs-strong);
    padding: 6px;
    opacity: 0;
    pointer-events: none;
    transform: translateY(-6px);
    transition: all 0.18s ease;
    z-index: 9999 !important;
}

.prj-card-menu.open {
    opacity: 1;
    pointer-events: all;
    transform: translateY(0);
}

.prj-menu-item {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 8px 12px;
    background: transparent;
    border: none;
    color: var(--text);
    font-family: 'Tajawal', sans-serif;
    font-size: 14px;
    text-align: start;
    cursor: pointer;
    border-radius: 6px;
    transition: background 0.15s;
}

.prj-menu-item:hover {
    background: rgba(0, 108, 53, 0.08);
}

.prj-menu-danger {
    color: var(--danger) !important;
}

.prj-menu-danger:hover {
    background: rgba(239, 68, 68, 0.08) !important;
}

.prj-menu-sep {
    height: 1px;
    background: var(--border);
    margin: 4px 0;
}

.prj-card-act-btn {
    background: var(--surface2);
    border: 1px solid var(--border);
    font-size: 14px;
    color: var(--text-muted);
    cursor: pointer;
    padding: 4px 7px;
    border-radius: 6px;
    transition: all 0.2s;
    line-height: 1;
}

.prj-card-act-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--surface);
}

.prj-card-act-danger:hover {
    border-color: var(--danger);
    color: var(--danger);
    background: rgba(239, 68, 68, 0.06);
}

/* ── Skeleton Cards ───────────────────────────────────────── */
.prj-card-skel {
    display: contents;
}

.prj-skel-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 24px;
    display: flex;
    gap: 14px;
    align-items: flex-start;
}

.prj-skel-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    flex-shrink: 0;
}

.prj-skel-lines {
    flex: 1;
}

/* ── Empty State ──────────────────────────────────────────── */
.prj-empty {
    text-align: center;
    padding: 80px 20px;
}

.prj-empty-icon {
    font-size: 64px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.prj-empty h3 {
    margin-bottom: 8px;
}

/* ── SweetAlert2 Overrides ────────────────────────────────── */
.swal2-popup {
    font-family: 'Tajawal', sans-serif !important;
    border-radius: var(--radius-lg) !important;
}

/* ── Print ────────────────────────────────────────────────── */
@media print {

    .navbar,
    .sidebar,
    .sidebar-desktop,
    .controls-bar,
    .prj-controls,
    .sidebar-overlay,
    .notif-panel,
    .notif-overlay {
        display: none !important;
    }

    .layout {
        display: block !important;
    }

    .main-content {
        padding: 0 !important;
    }

    .prj-card {
        break-inside: avoid;
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
}
</style>

<script>
// ============================================================
// Projects Listing — Controller
// ============================================================
(() => {
    'use strict';

    const API = '/api/projects.php';
    let allProjects = [];

    const TYPE_ICONS = {
        car: '🚗',
        house: '🏠',
        occasion: '🎉',
        work: '💼',
        devices: '📦',
        custom: '✏️'
    };
    const TYPE_LABELS_AR = {
        car: 'سيارة',
        house: 'منزل',
        occasion: 'مناسبة',
        work: 'عمل',
        devices: 'أجهزة',
        custom: 'مخصص'
    };
    const TYPE_LABELS_EN = {
        car: 'Car',
        house: 'House',
        occasion: 'Occasion',
        work: 'Work',
        devices: 'Devices',
        custom: 'Custom'
    };

    // Defines the lang routine.
    function lang() {
        return localStorage.getItem('mizan_lang') || 'ar';
    }

    // Defines the fmtNum routine.
    function fmtNum(n) {
        return Number(n).toLocaleString('en-US', {
            maximumFractionDigits: 0
        });
    }

    // Defines the fmtCurrency routine.
    function fmtCurrency(n) {
        return fmtNum(n) + ' ' + (typeof getCurrencySymbol === 'function' ? getCurrencySymbol() : (lang() === 'ar' ? '﷼' : 'SAR'));
    }

    // Defines the fmtDate routine.
    function fmtDate(d) {
        if (!d) return '';
        return new Date(d).toLocaleDateString(lang() === 'ar' ? 'ar-SA' : 'en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    // ── Progress bar color ───────────────────────────────────────
    function progressColor(pct) {
        if (pct > 90) return 'var(--danger)';
        if (pct > 75) return 'var(--warning)';
        return 'var(--primary)';
    }

    // ── Status badge ─────────────────────────────────────────────
    function statusBadge(status) {
        const labels_ar = {
            active: 'نشط',
            done: 'مكتمل',
            archived: 'مؤرشف'
        };
        const labels_en = {
            active: 'Active',
            done: 'Done',
            archived: 'Archived'
        };
        const cls = {
            active: 'prj-badge-active',
            done: 'prj-badge-done',
            archived: 'prj-badge-archived'
        };
        const label = lang() === 'ar' ? labels_ar[status] : labels_en[status];
        return `<span class="prj-badge ${cls[status] || 'prj-badge-active'}">${label || status}</span>`;
    }

    // ── Build a single project card ──────────────────────────────
    function buildCard(p) {
        const icon = TYPE_ICONS[p.type] || '✏️';
        const color = p.color || '#3b82f6';
        const budget = p.budget || 0;
        const pct = p.budget_pct || 0;

        const budgetHTML = budget > 0 ? `
        <div class="prj-budget-section">
            <div class="prj-budget-header">
                <span>${fmtCurrency(p.total_spent)} / ${fmtCurrency(budget)}</span>
                <strong>${pct}%</strong>
            </div>
            <div class="prj-progress-track">
                <div class="prj-progress-fill" data-pct="${pct}" style="background:${progressColor(pct)};"></div>
            </div>
        </div>
    ` : `<div class="prj-budget-section"><div class="prj-budget-header"><span>${fmtCurrency(p.total_spent)}</span><span class="text-muted">${lang() === 'ar' ? 'بدون ميزانية' : 'No budget'}</span></div></div>`;

        return `
    <div class="prj-card" data-id="${p.id}" data-status="${p.status}"
         data-name="${(p.name || '').toLowerCase()}" style="--card-accent:${color};"
         onclick="navigateProject(event, ${p.id})">
        <div class="prj-card-actions">
            <button class="prj-card-act-btn prj-menu-trigger" onclick="toggleCardMenu(event, ${p.id})" title="${lang() === 'ar' ? 'إجراءات' : 'Actions'}">⋮</button>
            <div class="prj-card-menu" id="cardMenu-${p.id}" onclick="event.stopPropagation()">
                <button class="prj-menu-item" onclick="showEditModal(event, ${p.id})">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    <span>${lang() === 'ar' ? 'تعديل' : 'Edit'}</span>
                </button>
                <div class="prj-menu-sep"></div>
                <button class="prj-menu-item" onclick="goInvoices(event, ${p.id})">🧾 <span>${lang() === 'ar' ? 'الفواتير' : 'Invoices'}</span></button>
                <button class="prj-menu-item" onclick="goWarranties(event, ${p.id})">🛡️ <span>${lang() === 'ar' ? 'الضمانات' : 'Warranties'}</span></button>
                <button class="prj-menu-item" onclick="exportZip(event, ${p.id})">📦 <span>${lang() === 'ar' ? 'تصدير ZIP' : 'Export ZIP'}</span></button>
                <button class="prj-menu-item" onclick="shareLink(event, ${p.id})">🔗 <span>${lang() === 'ar' ? 'رابط مشاركة' : 'Share Link'}</span></button>
                <div class="prj-menu-sep"></div>
                <button class="prj-menu-item prj-menu-danger" onclick="deleteProject(event, ${p.id}, ${JSON.stringify(p.name||'')})">🗑️ <span>${lang() === 'ar' ? 'حذف' : 'Delete'}</span></button>
            </div>
        </div>
        <div class="prj-card-top">
            <div class="prj-card-icon" style="background:${color}18; border:2px solid ${color}35;">
                ${icon}
            </div>
            <div class="prj-card-info">
                <h3 class="prj-card-name">${escHtml(p.name)}</h3>
                <div class="prj-card-meta">
                    ${statusBadge(p.status)}
                    <span>${fmtDate(p.created_at)}</span>
                </div>
            </div>
        </div>
        ${budgetHTML}
        <div class="prj-card-stats">
            <span class="prj-stat">📝 <strong>${p.expense_count}</strong> ${lang() === 'ar' ? 'عملية' : 'expenses'}</span>
            <span class="prj-stat">🛡️ <strong>${p.warranty_count}</strong> ${lang() === 'ar' ? 'ضمان' : 'warranties'}</span>
            ${p.expiring_warranties > 0 ? `<span class="prj-stat" style="color:var(--warning);">⚠️ ${p.expiring_warranties}</span>` : ''}
        </div>
    </div>`;
    }

    // Defines the escHtml routine.
    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    // ── Navigate to project detail ───────────────────────────────
    window.navigateProject = function(e, id) {
        // Don't navigate if an action button was clicked
        if (e.target.closest('.prj-card-actions')) return;
        window.location.href = `project-detail.php?id=${id}`;
    };

    // ── Render all cards ─────────────────────────────────────────
    function renderProjects(projects) {
        const grid = document.getElementById('projectsGrid');
        const empty = document.getElementById('emptyState');
        const counter = document.getElementById('counterStrip');

        // Remove skeletons
        const skel = document.getElementById('skelWrap');
        if (skel) skel.remove();

        if (!projects.length) {
            grid.innerHTML = '';
            grid.style.display = 'none';
            empty.style.display = '';
            counter.style.display = 'none';
            return;
        }

        grid.style.display = '';
        empty.style.display = 'none';
        counter.style.display = 'flex';

        grid.innerHTML = projects.map(p => buildCard(p)).join('');
        // Apply cascade stagger to the freshly-rendered cards.
        grid.classList.add('stagger-enter');
        Array.from(grid.children).forEach((child, i) => child.style.setProperty('--i', i));

        // Update counters
        document.getElementById('cntTotal').textContent = projects.length;
        document.getElementById('cntActive').textContent = projects.filter(p => p.status === 'active').length;
        document.getElementById('cntDone').textContent = projects.filter(p => p.status === 'done').length;
        document.getElementById('cntArchived').textContent = projects.filter(p => p.status === 'archived').length;

        // Stagger entrance
        grid.querySelectorAll('.prj-card').forEach((card, i) => {
            setTimeout(() => card.classList.add('entered'), 60 + i * 80);
        });

        // Animate progress bars after entrance
        setTimeout(() => {
            grid.querySelectorAll('.prj-progress-fill').forEach(bar => {
                bar.style.width = bar.dataset.pct + '%';
            });
        }, 200);
    }

    // ── Filter by search + status ────────────────────────────────
    window.filterProjects = function() {
        const query = document.getElementById('projectSearch').value.trim().toLowerCase();
        const status = document.getElementById('statusFilter').value;

        const filtered = allProjects.filter(p => {
            const nameMatch = !query || p.name.toLowerCase().includes(query);
            const statusMatch = status === 'all' || p.status === status;
            return nameMatch && statusMatch;
        });
        renderProjects(filtered);
    };

    // ── Fetch projects from API ──────────────────────────────────
    function fetchProjects() {
        mizanFetch(`${API}?action=list`)
            .then(r => r.json())
            .then(d => {
                if (!d.success) return showToast(t(d.error) || t('generic_error'), 'error');
                allProjects = d.projects;
                renderProjects(allProjects);
            })
            .catch(() => showToast(t('cannot_load_projects'), 'error'));
    }

    // ── 3-dot menu toggle ────────────────────────────────────────
    window.toggleCardMenu = function(e, id) {
        e.stopPropagation();
        document.querySelectorAll('.prj-card-menu.open').forEach(m => {
            if (m.id !== `cardMenu-${id}`) {
                m.classList.remove('open');
                m.closest('.prj-card')?.classList.remove('menu-open');
            }
        });
        const menu = document.getElementById(`cardMenu-${id}`);
        if (menu) {
            menu.classList.toggle('open');
            menu.closest('.prj-card')?.classList.toggle('menu-open', menu.classList.contains('open'));
        }
    };
    document.addEventListener('click', (e) => {
        if (e.target.closest('.prj-card-actions')) return;
        document.querySelectorAll('.prj-card-menu.open').forEach(m => {
            m.classList.remove('open');
            m.closest('.prj-card')?.classList.remove('menu-open');
        });
    });

    // ── Delete project (with SweetAlert2) ────────────────────────
    window.deleteProject = function(e, id, name) {
        e.stopPropagation();

        Swal.fire({
            title: t('delete_project_title'),
            html: lang() === 'ar' ?
                `سيتم حذف <strong>${name}</strong> وجميع بياناته نهائياً.` :
                `<strong>${name}</strong> and all its data will be permanently deleted.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#EF4444',
            confirmButtonText: t('delete_permanently'),
            cancelButtonText: t('cancel'),
        }).then(result => {
            if (!result.isConfirmed) return;
            mizanFetch(`${API}?action=delete&id=${id}`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        showToast(t('project_deleted'), 'success');
                        allProjects = allProjects.filter(p => p.id !== id);
                        filterProjects();
                    } else {
                        showToast(t(d.error) || t('generic_error'), 'error');
                    }
                })
                .catch(() => showToast(t('cannot_delete'), 'error'));
        });
    };

    // Renders the live checklist of suggested expenses inside the
    // create-project modal whenever the user picks a starter template.
    // Only checked items are actually inserted by the API.
    window.renderTemplateChecklist = function(key) {
        const box = document.getElementById('swalTemplateChecklist');
        if (!box) return;
        if (!key || !TEMPLATE_PRESETS[key]) {
            box.style.display = 'none';
            box.innerHTML = '';
            return;
        }
        const isAr = lang() === 'ar';
        box.style.display = 'block';
        box.innerHTML =
            `<div class="mz-checklist-head">${t('suggested_expenses_head')}</div>` +
            TEMPLATE_PRESETS[key].map((item, i) =>
                `<label class="mz-check">
                    <input type="checkbox" value="${item}" checked>
                    <span>${item}</span>
                 </label>`
            ).join('');
    };

    // ── Template presets (rendered as a UI checklist) ────────────
    const TEMPLATE_PRESETS = {
        car: ['صيانة', 'تأمين', 'فحص', 'استبدال إطارات', 'تغيير زيت'],
        house: ['سباكة', 'كهرباء', 'عظم', 'دهان', 'تشطيبات', 'أثاث'],
        software: ['سيرفرات', 'دومين', 'تصميم UI/UX', 'تسويق'],
        wedding: ['القاعة', 'الضيافة', 'الدعوات', 'التصوير'],
        travel: ['تذاكر طيران', 'فندق', 'مواصلات', 'أنشطة'],
    };
    const TEMPLATE_LABELS_AR = {
        car: '🚗 سيارة',
        house: '🏠 منزل',
        software: '💻 برمجيات',
        wedding: '💍 زفاف',
        travel: '✈️ سفر',
    };
    const TEMPLATE_LABELS_EN = {
        car: '🚗 Car',
        house: '🏠 House',
        software: '💻 Software',
        wedding: '💍 Wedding',
        travel: '✈️ Travel',
    };

    // ── Create project (SweetAlert2 modal) ───────────────────────
    window.showCreateModal = function() {
        const isAr = lang() === 'ar';
        const typeOptions = Object.entries(TYPE_ICONS).map(([val, icon]) => {
            const label = isAr ? TYPE_LABELS_AR[val] : TYPE_LABELS_EN[val];
            return `<option value="${val}">${icon} ${label}</option>`;
        }).join('');

        Swal.fire({
            title: t('new_project_title'),
            customClass: {
                popup: 'mz-modal-pop'
            },
            html: `
            <div class="mz-form" style="text-align:right; font-family:'Tajawal',sans-serif;">
                <div class="mz-row">
                    <label class="mz-label">${t('project_name_swal')} *</label>
                    <input id="swalName" class="mz-input" maxlength="200" placeholder="${t('project_name_ph_swal')}">
                </div>
                <div class="mz-grid-3">
                    <div>
                        <label class="mz-label">${t('type_swal')}</label>
                        <select id="swalType" class="mz-input">${typeOptions}</select>
                    </div>
                    <div>
                        <label class="mz-label">${t('nature_swal')}</label>
                        <select id="swalNature" class="mz-input">
                            <option value="personal">👤 ${t('nature_personal')}</option>
                            <option value="commercial">💼 ${t('nature_commercial')}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mz-label">${t('budget_swal')}</label>
                        <input id="swalBudget" type="number" class="mz-input" min="0" step="100" placeholder="0">
                    </div>
                </div>
                <div class="mz-row">
                    <label class="mz-label">${t('template_swal')}</label>
                    <select id="swalTemplate" class="mz-input" onchange="renderTemplateChecklist(this.value)">
                        <option value="">${t('no_template_opt')}</option>
                        <option value="car">${isAr ? TEMPLATE_LABELS_AR.car      : TEMPLATE_LABELS_EN.car}</option>
                        <option value="house">${isAr ? TEMPLATE_LABELS_AR.house    : TEMPLATE_LABELS_EN.house}</option>
                        <option value="software">${isAr ? TEMPLATE_LABELS_AR.software : TEMPLATE_LABELS_EN.software}</option>
                        <option value="wedding">${isAr ? TEMPLATE_LABELS_AR.wedding  : TEMPLATE_LABELS_EN.wedding}</option>
                        <option value="travel">${isAr ? TEMPLATE_LABELS_AR.travel   : TEMPLATE_LABELS_EN.travel}</option>
                    </select>
                    <div id="swalTemplateChecklist" class="mz-checklist" style="display:none;"></div>
                </div>
                <div class="mz-grid-2">
                    <div>
                        <label class="mz-label">${t('color_swal')}</label>
                        <input id="swalColor" type="color" value="#3b82f6" class="mz-color">
                    </div>
                    <div>
                        <label class="mz-label">${t('notes_label')}</label>
                        <input id="swalNotes" class="mz-input" maxlength="500" placeholder="${t('optional_ph')}">
                    </div>
                </div>
            </div>
        `,

            showCancelButton: true,
            confirmButtonColor: 'var(--primary)',
            confirmButtonText: t('create_btn'),
            cancelButtonText: t('cancel'),
            focusConfirm: false,

            didOpen: () => {
                document.getElementById('swalName')?.focus();

                if (new URL(location.href).searchParams.get('new')) {
                    history.replaceState({}, '', location.pathname);
                }
            },

            preConfirm: () => {
                const name = document.getElementById('swalName').value.trim();

                if (!name) {
                    Swal.showValidationMessage(t('project_name_required_msg'));
                    return false;
                }

                const tKey = document.getElementById('swalTemplate').value;

                const tItems = [
                    ...document.querySelectorAll(
                        '#swalTemplateChecklist input[type="checkbox"]:checked')
                ].map(c => c.value);

                return {
                    name,
                    type: document.getElementById('swalType').value,
                    nature: document.getElementById('swalNature').value || 'personal',
                    budget: parseFloat(document.getElementById('swalBudget').value) || 0,
                    color: document.getElementById('swalColor').value,
                    notes: document.getElementById('swalNotes').value.trim(),
                    template: tKey,
                    template_items: tItems,
                };
            }

        }).then(result => {
            // Always release the page lock first — Swal manages its own
            // backdrop, but we belt-and-brace it here so an uncaught error
            // inside fetchProjects() can't strand body[overflow:hidden].
            if (typeof window.closeModals === 'function') window.closeModals();

            if (!result.isConfirmed) return;

            mizanFetch(`${API}?action=create`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(result.value),
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        showToast(t('project_created'), 'success');
                        fetchProjects();
                    } else {
                        showToast(t(d.error) || t('generic_error'), 'error');
                    }
                })
                .catch(() => showToast(t('cannot_create'), 'error'))
                .finally(() => {
                    if (typeof window.closeModals === 'function') window.closeModals();
                });
        });
    };

    // ── Edit Project (SweetAlert2 modal, pre-filled) ─────────────
    window.showEditModal = function(e, id) {
        if (e && typeof e.stopPropagation === 'function') e.stopPropagation();
        const isAr = lang() === 'ar';
        const p = allProjects.find(p => p.id === id);
        if (!p) return showToast(t('project_not_found'), 'error');

        const typeOptions = Object.entries(TYPE_ICONS).map(([val, icon]) => {
            const label = isAr ? TYPE_LABELS_AR[val] : TYPE_LABELS_EN[val];
            return `<option value="${val}"${p.type === val ? ' selected' : ''}>${icon} ${label}</option>`;
        }).join('');

        Swal.fire({
            title: t('edit_project_title'),
            customClass: { popup: 'mz-modal-pop' },
            html: `
            <div class="mz-form" style="text-align:right; font-family:'Tajawal',sans-serif;">
                <div class="mz-row">
                    <label class="mz-label">${t('project_name_swal')} *</label>
                    <input id="editName" class="mz-input" maxlength="200" value="${escHtml(p.name || '')}">
                </div>
                <div class="mz-grid-3">
                    <div>
                        <label class="mz-label">${t('type_swal')}</label>
                        <select id="editType" class="mz-input">${typeOptions}</select>
                    </div>
                    <div>
                        <label class="mz-label">${t('nature_swal')}</label>
                        <select id="editNature" class="mz-input">
                            <option value="personal"${(p.nature || 'personal') === 'personal' ? ' selected' : ''}>👤 ${t('nature_personal')}</option>
                            <option value="commercial"${(p.nature || 'personal') === 'commercial' ? ' selected' : ''}>💼 ${t('nature_commercial')}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mz-label">${t('budget_swal')}</label>
                        <input id="editBudget" type="number" class="mz-input" min="0" step="100" value="${p.budget || 0}">
                    </div>
                </div>
                <div class="mz-grid-2">
                    <div>
                        <label class="mz-label">${t('color_swal')}</label>
                        <input id="editColor" type="color" value="${escHtml(p.color || '#3b82f6')}" class="mz-color">
                    </div>
                    <div>
                        <label class="mz-label">${t('notes_label')}</label>
                        <input id="editNotes" class="mz-input" maxlength="500" value="${escHtml(p.notes || '')}">
                    </div>
                </div>
            </div>`,
            showCancelButton: true,
            confirmButtonColor: 'var(--primary)',
            confirmButtonText: t('save_edits_btn'),
            cancelButtonText: t('cancel'),
            focusConfirm: false,
            didOpen: () => document.getElementById('editName')?.focus(),
            preConfirm: () => {
                const name = document.getElementById('editName').value.trim();
                if (!name) {
                    Swal.showValidationMessage(t('project_name_required_msg'));
                    return false;
                }
                return {
                    id,
                    name,
                    type:   document.getElementById('editType').value,
                    nature: document.getElementById('editNature').value,
                    budget: parseFloat(document.getElementById('editBudget').value) || 0,
                    color:  document.getElementById('editColor').value,
                    notes:  document.getElementById('editNotes').value.trim(),
                };
            },
        }).then(result => {
            if (!result.isConfirmed) return;
            const data = result.value;

            mizanFetch(`${API}?action=update`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    const idx = allProjects.findIndex(p => p.id === id);
                    if (idx !== -1) {
                        const old = allProjects[idx];
                        const newBudget = data.budget;
                        allProjects[idx] = Object.assign({}, old, {
                            name:       data.name,
                            type:       data.type,
                            nature:     data.nature,
                            budget:     newBudget,
                            color:      data.color,
                            notes:      data.notes,
                            budget_pct: newBudget > 0
                                ? Math.min(100, Math.round((old.total_spent / newBudget) * 100))
                                : 0,
                        });
                    }
                    showToast(t('project_updated'), 'success');
                    filterProjects();
                } else {
                    showToast(t(d.error) || t('generic_error'), 'error');
                }
            })
            .catch(() => showToast(t('update_failed'), 'error'));
        });
    };

    // ── Navigation ───────────────────────────────────────────────
    window.goInvoices = function(e, id) {
        e.stopPropagation();
        window.location.href = `invoices.php?project=${id}`;
    };

    window.goWarranties = function(e, id) {
        e.stopPropagation();
        window.location.href = `warranties.php?project=${id}`;
    };

    // exportZip is provided globally by main.js (fetch + blob + hidden
    // anchor) so server-side errors surface as toasts instead of a
    // broken JSON page. We only need to stop the card-click here.
    // The global handler accepts (event, id).

    // ── Share Link (server-issued random token) ──────────────────
    // Three-layer fallback: navigator.clipboard.writeText → execCommand
    // copy via temp textarea → Swal text input. Guarantees the user
    // always ends up with a copyable URL, even on insecure-origin
    // browsers where clipboard API is unavailable.
    window.shareLink = async function(e, id) {
        if (e && typeof e.stopPropagation === 'function') e.stopPropagation();
        const isAr = lang() === 'ar';

        // 1. Request the token from the API.
        let url = '';
        try {
            const res = await mizanFetch(`${API}?action=create_share_link`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ project_id: id }),
            });
            const d = await res.json();
            if (!d.success || !d.url) {
                showToast(t(d.error) || t('share_link_failed'), 'error');
                return;
            }
            url = String(d.url);
        } catch (_) {
            showToast(t('share_link_failed'), 'error');
            return;
        }

        // 2. Try the modern Clipboard API first (only works on secure
        //    contexts: https or localhost).
        if (navigator.clipboard && window.isSecureContext) {
            try {
                await navigator.clipboard.writeText(url);
                showToast(t('share_link_copied'), 'success');
                return;
            } catch (_) { /* fall through */ }
        }

        // 3. Legacy execCommand('copy') via a hidden textarea — works on
        //    http://localhost / xampp where the Clipboard API is blocked.
        try {
            const ta = document.createElement('textarea');
            ta.value = url;
            ta.style.position = 'fixed';
            ta.style.opacity  = '0';
            document.body.appendChild(ta);
            ta.select();
            const ok = document.execCommand('copy');
            document.body.removeChild(ta);
            if (ok) {
                showToast(t('share_link_copied'), 'success');
                return;
            }
        } catch (_) { /* fall through */ }

        // 4. Final fallback: present the URL in a Swal dialog so the
        //    user can copy it manually.
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: t('share_link_modal_title'),
                input: 'text',
                inputValue: url,
                showConfirmButton: false,
                showCancelButton: true,
                cancelButtonText: t('close_btn'),
            });
        } else {
            window.prompt(t('copy_link_prompt'), url);
        }
    }

    // ── Init ─────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        fetchProjects();
        // Auto-open the create modal if the navbar shortcut landed here.
        if (new URL(location.href).searchParams.get('new') === '1') {
            setTimeout(() => {
                if (typeof window.showCreateModal === 'function') {
                    window.showCreateModal();
                }
            }, 100);
        }
    });

})();
</script>

<?php require_once '../includes/footer.php'; ?>
