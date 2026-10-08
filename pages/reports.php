<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: reports.php
 * PURPOSE: Reports dashboard with charts and date-range filtering.
 * ========================================================================
 */
require_once '../includes/auth.php';
require_once '../config/db.php';

$page_title = 'التقارير';
require_once '../includes/header.php';
// ─────────────────────────────────────────────────────────────────────────
// END PHP BOOTSTRAP — BEGIN HTML VIEW (charts hydrated by /api/reports.php)
// ─────────────────────────────────────────────────────────────────────────
?>

<!-- ===== Page Header with Controls ===== -->
<div class="rpt-header reveal">
    <div class="rpt-header-text">
        <h1 class="page-title" data-i18n="reports">التقارير</h1>
        <p class="text-muted" data-i18n="reports_desc">تحليلات مالية شاملة لمشاريعك</p>
    </div>
    <div class="rpt-controls">
        <!-- Project Filter -->
        <select id="rptProjectFilter" class="rpt-date-input" style="min-width:180px;">
            <option value="0" data-i18n="all_projects">كل المشاريع</option>
        </select>
        <!-- Flatpickr Date Range -->
        <div class="rpt-date-wrap">
            <span class="rpt-date-icon">📅</span>
            <input type="text" id="dateRange" class="rpt-date-input" placeholder="اختر الفترة..."
                data-i18n="select_period">
        </div>
        <!-- Action Buttons -->
        <!-- "Export CSV" button — downloads the currently-filtered dataset as a CSV file -->
        <button class="btn btn-ghost btn-sm rpt-action-btn" onclick="exportCSV()" title="تصدير CSV">
            <span>📥</span> <span data-i18n="export_csv">تصدير CSV</span>
        </button>
        <button class="btn btn-ghost btn-sm rpt-action-btn" id="rptPrintBtn" onclick="window.print()" title="طباعة / PDF">
            <span>🖨️</span> <span data-i18n="print_pdf">طباعة / PDF</span>
        </button>
    </div>
</div>

<style>
/* Fixed-height wrappers prevent Chart.js infinite stretching */
.rpt-chart-body { position: relative; height: 300px; }
.rpt-chart-body-donut { height: 320px; }
.rpt-chart-body canvas { max-height: 100%; }

/* Flatpickr day contrast fix */
.flatpickr-day { color: var(--text) !important; }
.flatpickr-day.disabled,
.flatpickr-day.flatpickr-disabled,
.flatpickr-day.prevMonthDay,
.flatpickr-day.nextMonthDay { opacity: 0.35; }
.flatpickr-day.selected,
.flatpickr-day.startRange,
.flatpickr-day.endRange { color: #fff !important; }

/* Print stylesheet — hide chrome, show only the report */
@media print {
    body { background: #fff !important; }
    .navbar, nav, .sidebar, #sidebar, .topbar, .ph-actions,
    .rpt-controls, #rptPrintBtn, .toast-container, #bg-canvas,
    #mz-shapes, .modal, footer { display: none !important; }
    .rpt-chart-body { height: 260px !important; page-break-inside: avoid; }
    .rpt-chart-card, .rpt-stat-card { box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>

<!-- ===== Summary Cards ===== -->
<div class="rpt-stats" id="statsGrid">
    <div class="rpt-stat-card" data-accent="var(--primary)">
        <div class="rpt-stat-icon" style="background:rgba(0,108,53,0.10); color:var(--primary);">💰</div>
        <div class="rpt-stat-body">
            <span class="rpt-stat-label" data-i18n="total_spent_range">إجمالي المصروفات</span>
            <span class="rpt-stat-value" id="statTotalSpent">—</span>
        </div>
    </div>
    <div class="rpt-stat-card" data-accent="var(--secondary)">
        <div class="rpt-stat-icon" style="background:rgba(3,105,161,0.10); color:var(--secondary);">📂</div>
        <div class="rpt-stat-body">
            <span class="rpt-stat-label" data-i18n="active_projects">مشاريع نشطة</span>
            <span class="rpt-stat-value" id="statActiveProjects">—</span>
        </div>
    </div>
    <div class="rpt-stat-card" data-accent="var(--warning)">
        <div class="rpt-stat-icon" style="background:rgba(217,119,6,0.10); color:var(--warning);">⏰</div>
        <div class="rpt-stat-body">
            <span class="rpt-stat-label" data-i18n="expiring_warranties">ضمانات تنتهي قريباً</span>
            <span class="rpt-stat-value" id="statExpiring">—</span>
        </div>
    </div>
    <div class="rpt-stat-card" data-accent="var(--danger)">
        <div class="rpt-stat-icon" style="background:rgba(239,68,68,0.10); color:var(--danger);">🏷️</div>
        <div class="rpt-stat-body">
            <span class="rpt-stat-label" data-i18n="top_category">أعلى فئة إنفاقاً</span>
            <span class="rpt-stat-value rpt-stat-value-sm" id="statTopCat">—</span>
        </div>
    </div>
</div>

<!-- ===== Charts Grid ===== -->
<div class="rpt-charts">
    <!-- Line Chart: Monthly Spending -->
    <div class="rpt-chart-card rpt-chart-wide" id="chartMonthlyWrap">
        <div class="rpt-chart-header">
            <h3 class="rpt-chart-title" data-i18n="monthly_spending">المصروفات الشهرية</h3>

        </div>
        <div class="rpt-chart-body">
            <div class="rpt-skeleton rpt-skeleton-line" id="skelMonthly"></div>
            <canvas id="chartMonthly" style="display:none;"></canvas>
        </div>
    </div>

    <!-- Donut Chart: Category Breakdown -->
    <div class="rpt-chart-card" id="chartCategoryWrap">
        <div class="rpt-chart-header">
            <h3 class="rpt-chart-title" data-i18n="category_breakdown">توزيع الفئات</h3>
        </div>
        <div class="rpt-chart-body rpt-chart-body-donut">
            <div class="rpt-skeleton rpt-skeleton-donut" id="skelCategory"></div>
            <canvas id="chartCategory" style="display:none;"></canvas>
        </div>
    </div>

    <!-- Pie Chart: Warranty Status -->
    <div class="rpt-chart-card" id="chartWarrantyWrap">
        <div class="rpt-chart-header">
            <h3 class="rpt-chart-title" data-i18n="warranty_status">حالة الضمانات</h3>
        </div>
        <div class="rpt-chart-body rpt-chart-body-donut">
            <div class="rpt-skeleton rpt-skeleton-donut" id="skelWarranty"></div>
            <canvas id="chartWarranty" style="display:none;"></canvas>
        </div>
    </div>

    <!-- Bar Chart: Budget vs Actual -->
    <div class="rpt-chart-card rpt-chart-wide" id="chartBudgetWrap">
        <div class="rpt-chart-header">
            <h3 class="rpt-chart-title" data-i18n="budget_vs_actual">الميزانية مقابل الفعلي</h3>
        </div>
        <div class="rpt-chart-body">
            <div class="rpt-skeleton rpt-skeleton-bar" id="skelBudget"></div>
            <canvas id="chartBudget" style="display:none;"></canvas>
        </div>
    </div>
</div>

<!-- ===== Empty State ===== -->
<div class="rpt-empty" id="emptyState" style="display:none;">
    <div class="rpt-empty-icon">📊</div>
    <h3 data-i18n="no_data">لا توجد بيانات</h3>
    <p class="text-muted" data-i18n="no_data_desc">أضف مشاريع ومصروفات لعرض التقارير</p>
</div>

<!-- ===== Dependencies ===== -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">

<!--
  All visual styling for the reports page lives in the global
  /assets/css/style.css under the "REPORTS PAGE (.rpt-*)" section.
  Removing the previous inline <style> block lets the global
  body gradient, dot/line grid, glow orbs, #bg-canvas particle
  network, and #mz-shapes drifters render through correctly so
  the page no longer reads as flat white.
-->

<!-- ===== JavaScript ===== -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js"></script>
<script>
// ============================================================
// Reports Dashboard — Controller
// ============================================================
(() => {
    'use strict';

    const API = '/api/reports.php';

    // ── Neon palette for charts ──────────────────────────────────
    const NEON = [
        '#22c55e', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6',
        '#06b6d4', '#ec4899', '#14b8a6', '#f97316', '#6366f1',
        '#84cc16', '#e11d48',
    ];

    // ── Chart instances ──────────────────────────────────────────
    let chartMonthly = null;
    let chartCategory = null;
    let chartBudget = null;
    let chartWarranty = null;

    // ── Cached data for CSV export ───────────────────────────────
    let lastData = null;

    // ── Theme-aware colors ───────────────────────────────────────
    function isDark() {
        return document.documentElement.getAttribute('data-theme') === 'dark';
    }

    // Defines the gridColor routine.
    function gridColor() {
        return isDark() ? 'rgba(255,255,255,0.06)' : 'rgba(15,23,42,0.06)';
    }

    // Defines the textColor routine.
    function textColor() {
        return isDark() ? '#94A3B8' : '#64748B';
    }

    // Defines the surfaceColor routine.
    function surfaceColor() {
        return isDark() ? '#1E293B' : '#E8E4DA';
    }

    // Defines the borderClr routine.
    function borderClr() {
        return isDark() ? '#334155' : '#E2DDD6';
    }

    // ── Flatpickr ────────────────────────────────────────────────
    const fp = flatpickr('#dateRange', {
        mode: 'range',
        dateFormat: 'Y-m-d',
        defaultDate: [
            new Date(Date.now() - 365 * 86400000).toISOString().slice(0, 10),
            new Date().toISOString().slice(0, 10),
        ],
        locale: (localStorage.getItem('mizan_lang') || 'ar') === 'ar' ? 'ar' : 'default',
        theme: isDark() ? 'dark' : 'light',
        onChange(dates) {
            if (dates.length === 2) fetchData();
        },
    });

    // ── Stagger entrance animation ───────────────────────────────
    function staggerEntrance() {
        document.querySelectorAll('.rpt-stat-card, .rpt-chart-card').forEach((el, i) => {
            setTimeout(() => el.classList.add('entered'), 80 + i * 100);
        });
    }

    // ── Skeleton helpers ─────────────────────────────────────────
    function showSkeletons() {
        document.querySelectorAll('.rpt-skeleton').forEach(s => s.style.display = '');
        document.querySelectorAll('.rpt-chart-body canvas').forEach(c => c.style.display = 'none');
    }

    // Defines the hideSkeletons routine.
    function hideSkeletons() {
        document.querySelectorAll('.rpt-skeleton').forEach(s => s.style.display = 'none');
        document.querySelectorAll('.rpt-chart-body canvas').forEach(c => c.style.display = '');
    }

    // ── Custom Tooltip Plugin ────────────────────────────────────
    function customTooltip(context) {
        let el = document.getElementById('rptTooltip');
        if (!el) {
            el = document.createElement('div');
            el.id = 'rptTooltip';
            el.className = 'rpt-tooltip';
            document.body.appendChild(el);
        }
        const tooltip = context.tooltip;
        if (tooltip.opacity === 0) {
            el.style.opacity = '0';
            return;
        }

        el.textContent = '';
        if (tooltip.title && tooltip.title.length) {
            const title = document.createElement('div');
            title.className = 'rpt-tooltip-title';
            title.textContent = String(tooltip.title[0]);
            el.appendChild(title);
        }
        (tooltip.body || []).forEach((item, i) => {
            const row = document.createElement('div');
            row.className = 'rpt-tooltip-value';

            const dot = document.createElement('span');
            dot.className = 'rpt-tooltip-dot';
            dot.style.background = String(tooltip.labelColors?.[i]?.backgroundColor || '#999');
            row.appendChild(dot);

            const textNode = document.createTextNode(String((item.lines || []).join('')));
            row.appendChild(textNode);
            el.appendChild(row);
        });
        el.style.opacity = '1';

        const pos = context.chart.canvas.getBoundingClientRect();
        const left = pos.left + window.scrollX + tooltip.caretX;
        const top = pos.top + window.scrollY + tooltip.caretY - el.offsetHeight - 12;
        el.style.position = 'absolute';
        el.style.left = left + 'px';
        el.style.top = top + 'px';
        el.style.zIndex = '10000';
    }

    // ── Format helpers ───────────────────────────────────────────
    function fmtNum(n) {
        return Number(n).toLocaleString('en-US', {
            maximumFractionDigits: 0
        });
    }

    // Defines the fmtCurrency routine.
    function fmtCurrency(n) {
        const sym = typeof window.getCurrencySymbol === 'function' ? window.getCurrencySymbol() : '﷼';
        return fmtNum(n) + ' ' + sym;
    }

    // Defines the fmtMonth routine.
    function fmtMonth(ym) {
        const [y, m] = ym.split('-');
        const lang = localStorage.getItem('mizan_lang') || 'ar';
        const d = new Date(+y, +m - 1);
        return d.toLocaleDateString(lang === 'ar' ? 'ar-SA' : 'en-US', {
            month: 'short',
            year: '2-digit'
        });
    }

    // ── Count-up animation ───────────────────────────────────────
    function countUp(el, target, isCurrency) {
        const duration = 900;
        const start = performance.now();
        const from = 0;

        // Defines the step routine.
        function step(now) {
            const p = Math.min((now - start) / duration, 1);
            const ease = 1 - Math.pow(1 - p, 4);
            const val = from + (target - from) * ease;
            el.textContent = isCurrency ? fmtCurrency(val) : fmtNum(val);
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    // ============================================================
    // Fetch & Render
    // ============================================================
    function fetchData() {
        const dates = fp.selectedDates;
        if (dates.length < 2) return;

        showSkeletons();

        const start_date = dates[0].toISOString().slice(0, 10);
        const end_date = dates[1].toISOString().slice(0, 10);
        const pid = parseInt((document.getElementById('rptProjectFilter') || {}).value, 10) || 0;
        const pidParam = pid > 0 ? `&project_id=${pid}` : '';

        mizanFetch(`${API}?start_date=${start_date}&end_date=${end_date}${pidParam}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    showToast(t(res.error) || t('fetch_error'), 'error');
                    return;
                }
                lastData = res.data;
                renderAll(res.data);
            })
            .catch(() => showToast(t('server_error'), 'error'))
            .finally(() => hideSkeletons());
    }

    // Renders the all.
    function renderAll(d) {
        // Check if there is any data at all
        const hasData = d.summary.total_spent > 0 || d.categories.length > 0 || d.budgets.length > 0;
        document.getElementById('emptyState').style.display = hasData ? 'none' : '';
        document.getElementById('statsGrid').style.display = hasData ? '' : 'none';
        document.querySelector('.rpt-charts').style.display = hasData ? '' : 'none';

        renderSummary(d.summary);
        renderMonthlyChart(d.monthly);
        renderCategoryChart(d.categories);
        renderBudgetChart(d.budgets);
        renderWarrantyChart(d.warranties);
        staggerEntrance();
    }

    // ── Summary Cards ────────────────────────────────────────────
    function renderSummary(s) {
        countUp(document.getElementById('statTotalSpent'), s.total_spent, true);
        countUp(document.getElementById('statActiveProjects'), s.active_projects, false);
        countUp(document.getElementById('statExpiring'), s.expiring_warranties, false);

        const topEl = document.getElementById('statTopCat');
        if (s.top_category) {
            topEl.textContent = s.top_category.name + ' — ' + fmtCurrency(s.top_category.total);
        } else {
            topEl.textContent = '—';
        }
    }

    // ── 1. Monthly Spending — Stripe-style Line ──────────────────
    function renderMonthlyChart(monthly) {
        const ctx = document.getElementById('chartMonthly').getContext('2d');

        // Dynamic gradient fill
        const gradient = ctx.createLinearGradient(0, 0, 0, ctx.canvas.parentElement.clientHeight || 280);
        gradient.addColorStop(0, isDark() ? 'rgba(34,197,94,0.25)' : 'rgba(0,108,53,0.18)');
        gradient.addColorStop(0.7, isDark() ? 'rgba(34,197,94,0.04)' : 'rgba(0,108,53,0.02)');
        gradient.addColorStop(1, 'transparent');

        const lineColor = isDark() ? '#22c55e' : '#006C35';

        const labels = monthly.map(m => fmtMonth(m.month));
        const values = monthly.map(m => m.total);

        const config = {
            labels,
            datasets: [{
                label: 'المصروفات',
                data: values,
                borderColor: lineColor,
                backgroundColor: gradient,
                borderWidth: 2.5,
                tension: 0.4,
                fill: true,
                pointRadius: 0,
                pointHoverRadius: 6,
                pointHoverBackgroundColor: lineColor,
                pointHoverBorderColor: surfaceColor(),
                pointHoverBorderWidth: 3,
            }],
        };

        if (chartMonthly) {
            chartMonthly.data = config;
            chartMonthly.options.scales.y.grid.color = gridColor();
            chartMonthly.options.scales.x.grid.color = gridColor();
            chartMonthly.options.scales.y.ticks.color = textColor();
            chartMonthly.options.scales.x.ticks.color = textColor();
            chartMonthly.update('none');
            return;
        }

        chartMonthly = new Chart(ctx, {
            type: 'line',
            data: config,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: false,
                        external: customTooltip,
                        callbacks: {
                            label: ctx => fmtCurrency(ctx.parsed.y),
                        },
                    },
                },
                scales: {
                    x: {
                        grid: {
                            color: gridColor(),
                            drawBorder: false,
                            drawTicks: false
                        },
                        ticks: {
                            color: textColor(),
                            font: {
                                family: 'Tajawal',
                                size: 12
                            },
                            maxRotation: 0,
                            padding: 8
                        },
                        border: {
                            display: false
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: gridColor(),
                            drawBorder: false,
                            drawTicks: false,
                            borderDash: [4, 4]
                        },
                        ticks: {
                            color: textColor(),
                            font: {
                                family: 'Tajawal',
                                size: 12
                            },
                            padding: 12,
                            callback: v => fmtNum(v),
                        },
                        border: {
                            display: false
                        },
                    },
                },
                layout: {
                    padding: {
                        top: 8
                    }
                },
            },
        });
    }

    // ── 2. Category Breakdown — Apple Fitness Donut ──────────────
    function renderCategoryChart(categories) {
        const ctx = document.getElementById('chartCategory').getContext('2d');

        const labels = categories.map(c => c.category);
        const values = categories.map(c => c.total);
        const colors = categories.map((_, i) => NEON[i % NEON.length]);

        const config = {
            labels,
            datasets: [{
                data: values,
                backgroundColor: colors,
                borderWidth: 0,
                hoverOffset: 8,
            }],
        };

        if (chartCategory) {
            chartCategory.data = config;
            chartCategory.update('none');
            return;
        }

        chartCategory = new Chart(ctx, {
            type: 'doughnut',
            data: config,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: textColor(),
                            font: {
                                family: 'Tajawal',
                                size: 12
                            },
                            padding: 14,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        },
                    },
                    tooltip: {
                        enabled: false,
                        external: customTooltip,
                        callbacks: {
                            label: ctx => {
                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = total ? Math.round(ctx.parsed / total * 100) : 0;
                                return `${ctx.label}: ${fmtCurrency(ctx.parsed)} (${pct}%)`;
                            },
                        },
                    },
                },
            },
        });
    }

    // ── 3. Budget vs Actual — Bar Chart ──────────────────────────
    function renderBudgetChart(budgets) {
        const ctx = document.getElementById('chartBudget').getContext('2d');

        const labels = budgets.map(b => b.name);
        const budgetVals = budgets.map(b => b.budget);
        const actualVals = budgets.map(b => b.actual);

        const config = {
            labels,
            datasets: [{
                    label: 'الميزانية',
                    data: budgetVals,
                    backgroundColor: isDark() ? 'rgba(59,130,246,0.25)' : 'rgba(3,105,161,0.18)',
                    borderColor: isDark() ? '#3b82f6' : '#0369A1',
                    borderWidth: 1.5,
                    borderRadius: 6,
                    borderSkipped: false,
                },
                {
                    label: 'الفعلي',
                    data: actualVals,
                    backgroundColor: isDark() ? 'rgba(34,197,94,0.35)' : 'rgba(0,108,53,0.22)',
                    borderColor: isDark() ? '#22c55e' : '#006C35',
                    borderWidth: 1.5,
                    borderRadius: 6,
                    borderSkipped: false,
                },
            ],
        };

        if (chartBudget) {
            chartBudget.data = config;
            chartBudget.options.scales.y.grid.color = gridColor();
            chartBudget.options.scales.x.grid.color = gridColor();
            chartBudget.options.scales.y.ticks.color = textColor();
            chartBudget.options.scales.x.ticks.color = textColor();
            chartBudget.update('none');
            return;
        }

        chartBudget = new Chart(ctx, {
            type: 'bar',
            data: config,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: textColor(),
                            font: {
                                family: 'Tajawal',
                                size: 12
                            },
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 14
                        },
                    },
                    tooltip: {
                        enabled: false,
                        external: customTooltip,
                        callbacks: {
                            label: ctx => `${ctx.dataset.label}: ${fmtCurrency(ctx.parsed.y)}`
                        },
                    },
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: textColor(),
                            font: {
                                family: 'Tajawal',
                                size: 12
                            },
                            maxRotation: 45
                        },
                        border: {
                            display: false
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: gridColor(),
                            drawBorder: false,
                            drawTicks: false,
                            borderDash: [4, 4]
                        },
                        ticks: {
                            color: textColor(),
                            font: {
                                family: 'Tajawal',
                                size: 12
                            },
                            padding: 12,
                            callback: v => fmtNum(v)
                        },
                        border: {
                            display: false
                        },
                    },
                },
            },
        });
    }

    // ── 4. Warranty Status — Vibrant Pie ─────────────────────────
    function renderWarrantyChart(w) {
        const ctx = document.getElementById('chartWarranty').getContext('2d');
        const lang = localStorage.getItem('mizan_lang') || 'ar';

        const labels = lang === 'ar' ? ['ساري', 'ينتهي قريباً', 'منتهي'] : ['Active', 'Expiring', 'Expired'];
        const values = [w.active, w.expiring, w.expired];
        const colors = ['#22c55e', '#f59e0b', '#ef4444'];

        // If all zeros, show a placeholder ring
        const total = values.reduce((a, b) => a + b, 0);

        const config = {
            labels: total ? labels : [lang === 'ar' ? 'لا توجد ضمانات' : 'No warranties'],
            datasets: [{
                data: total ? values : [1],
                backgroundColor: total ? colors : [isDark() ? '#334155' : '#E2DDD6'],
                borderWidth: 0,
                hoverOffset: 6,
            }],
        };

        if (chartWarranty) {
            chartWarranty.data = config;
            chartWarranty.update('none');
            return;
        }

        chartWarranty = new Chart(ctx, {
            type: 'doughnut',
            data: config,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: textColor(),
                            font: {
                                family: 'Tajawal',
                                size: 12
                            },
                            padding: 14,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        },
                    },
                    tooltip: {
                        enabled: total > 0,
                        ...(total > 0 ? {
                            enabled: false,
                            external: customTooltip
                        } : {}),
                        callbacks: {
                            label: ctx => `${ctx.label}: ${ctx.parsed}`,
                        },
                    },
                },
            },
        });
    }

    // ============================================================
    // Theme Reactivity — re-render charts when theme toggles
    // ============================================================
    const origApplyTheme = window.applyTheme;
    window.applyTheme = function(theme) {
        origApplyTheme?.(theme);
        // Defer so CSS variables have settled
        requestAnimationFrame(() => {
            Chart.defaults.color = textColor();
            if (lastData) renderAll(lastData);
        });
    };

    // ============================================================
    // CSV Export
    // ============================================================
    function exportCSV() {
        if (!lastData || !lastData.monthly.length) {
            showToast(t('no_export_data'), 'error');
            return;
        }
        const lang = localStorage.getItem('mizan_lang') || 'ar';
        const header = lang === 'ar' ? 'الشهر,المبلغ' : 'Month,Amount';
        const rows = lastData.monthly.map(m => `${m.month},${m.total}`);

        // Add category breakdown
        const catHeader = lang === 'ar' ? '\n\nالفئة,المبلغ' : '\n\nCategory,Amount';
        const catRows = lastData.categories.map(c => `${c.category},${c.total}`);

        const csv = '\uFEFF' + header + '\n' + rows.join('\n') + catHeader + '\n' + catRows.join('\n');
        const blob = new Blob([csv], {
            type: 'text/csv;charset=utf-8;'
        });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `mizan_report_${new Date().toISOString().slice(0,10)}.csv`;
        a.click();
        URL.revokeObjectURL(url);
        showToast(t('report_exported'), 'success');
    }
    window.exportCSV = exportCSV;

    // ============================================================
    // Init
    // ============================================================
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.btn').forEach((btn) => {
            btn.addEventListener('pointerdown', () => btn.classList.add('is-pressed'));
            btn.addEventListener('pointerup', () => btn.classList.remove('is-pressed'));
            btn.addEventListener('pointerleave', () => btn.classList.remove('is-pressed'));
        });

        // Populate project filter dropdown
        mizanFetch('/api/projects.php?action=list')
            .then(r => r.json())
            .then(res => {
                if (!res.success || !Array.isArray(res.projects)) return;
                const sel = document.getElementById('rptProjectFilter');
                res.projects.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = String(p.id);
                    opt.textContent = p.name;
                    sel.appendChild(opt);
                });
            })
            .catch(() => {});
        document.getElementById('rptProjectFilter').addEventListener('change', fetchData);

        fetchData();
    });

})();
</script>

<?php require_once '../includes/footer.php'; ?>
