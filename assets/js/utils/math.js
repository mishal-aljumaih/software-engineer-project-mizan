/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: math.js
 * PURPOSE: Small math utilities used across the frontend.
 * ========================================================================
 */
(function (root) {
    'use strict';

    // Fallback to native Math if mathjs is missing
    const MJS = root.math || null;
    // Defines the N routine.
    const N = (v) => (typeof v === 'number' && isFinite(v)) ? v : 0;

    /** Round a number to n decimal places (half-away-from-zero). */
    function round(v, decimals) {
        const d = decimals == null ? 2 : decimals;
        if (MJS) return MJS.round(N(v), d);
        const m = Math.pow(10, d);
        return Math.round(N(v) * m) / m;
    }

    /** Sum an array of numbers (skips null/NaN/strings safely). */
    function sum(arr) {
        const cleaned = (arr || []).map(N);
        if (MJS) return MJS.sum(cleaned);
        return cleaned.reduce((a, b) => a + b, 0);
    }

    /** Arithmetic mean. Returns 0 for empty arrays. */
    function mean(arr) {
        const cleaned = (arr || []).map(N);
        if (cleaned.length === 0) return 0;
        if (MJS) return MJS.mean(cleaned);
        return sum(cleaned) / cleaned.length;
    }

    /** Median value. */
    function median(arr) {
        const cleaned = (arr || []).map(N).sort((a, b) => a - b);
        if (cleaned.length === 0) return 0;
        if (MJS) return MJS.median(cleaned);
        const mid = Math.floor(cleaned.length / 2);
        return cleaned.length % 2 ? cleaned[mid] : (cleaned[mid - 1] + cleaned[mid]) / 2;
    }

    /** Population standard deviation. */
    function std(arr) {
        const cleaned = (arr || []).map(N);
        if (cleaned.length < 2) return 0;
        if (MJS) return MJS.std(cleaned, 'uncorrected');
        const m = mean(cleaned);
        return Math.sqrt(mean(cleaned.map(v => (v - m) * (v - m))));
    }

    /** Min / Max. */
    function min(arr) {
        const cleaned = (arr || []).map(N);
        return cleaned.length ? Math.min.apply(null, cleaned) : 0;
    }
    // Defines the max routine.
    function max(arr) {
        const cleaned = (arr || []).map(N);
        return cleaned.length ? Math.max.apply(null, cleaned) : 0;
    }

    /** Percentage of part / whole (returns rounded percent, not fraction). */
    function percentage(part, whole, decimals) {
        const w = N(whole);
        if (w === 0) return 0;
        return round((N(part) / w) * 100, decimals == null ? 1 : decimals);
    }

    /** Profit/loss: sellPrice − totalCost. Negative = loss. */
    function profitLoss(sellPrice, totalCost) {
        return round(N(sellPrice) - N(totalCost), 2);
    }

    /** Profit/loss percentage relative to cost. */
    function profitLossPct(sellPrice, totalCost) {
        const cost = N(totalCost);
        if (cost === 0) return 0;
        return percentage(N(sellPrice) - cost, cost, 2);
    }

    /** Aggregate items by a key, summing the `valueKey` field. */
    function aggregateBy(items, groupKey, valueKey) {
        const out = {};
        (items || []).forEach(it => {
            const k = it[groupKey] ?? 'uncategorized';
            out[k] = (out[k] || 0) + N(it[valueKey]);
        });
        return out;
    }

    /** Count items by a grouping key. */
    function countBy(items, groupKey) {
        const out = {};
        (items || []).forEach(it => {
            const k = it[groupKey] ?? 'uncategorized';
            out[k] = (out[k] || 0) + 1;
        });
        return out;
    }

    /** Filter items by date range. monthsBack from `now`, inclusive. */
    function filterByMonthsBack(items, dateKey, monthsBack, now) {
        const ref = now ? new Date(now) : new Date();
        const cutoff = new Date(ref.getFullYear(), ref.getMonth() - monthsBack, ref.getDate());
        return (items || []).filter(it => {
            const d = new Date(it[dateKey]);
            return !isNaN(d) && d >= cutoff && d <= ref;
        });
    }

    /**
     * Group totals into monthly buckets, returned as an ordered array of
     * `{ month: 'YYYY-MM', total: number }` (oldest → newest). monthsBack
     * defaults to inferring from the data range, capped at 12.
     */
    function monthlyTotals(items, dateKey, valueKey, monthsBack) {
        const list = items || [];
        let back = monthsBack;
        if (!back) {
            // Infer span from data; default 6, max 12.
            const dates = list.map(it => new Date(it[dateKey])).filter(d => !isNaN(d));
            if (dates.length) {
                const oldest = new Date(Math.min.apply(null, dates));
                const now = new Date();
                back = Math.min(12, Math.max(1, (now.getFullYear()-oldest.getFullYear())*12 + (now.getMonth()-oldest.getMonth()) + 1));
            } else {
                back = 6;
            }
        }
        const now = new Date();
        const buckets = {};
        const order = [];
        for (let i = back - 1; i >= 0; i--) {
            const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
            const key = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
            buckets[key] = 0;
            order.push(key);
        }
        list.forEach(it => {
            const d = new Date(it[dateKey]);
            if (isNaN(d)) return;
            const key = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
            if (buckets[key] !== undefined) buckets[key] += N(it[valueKey]);
        });
        return order.map(k => ({ month: k, total: buckets[k] }));
    }

    /** Format a number as currency for display. */
    function formatCurrency(v, currency) {
        const cur = currency || 'SAR';
        try {
            return new Intl.NumberFormat('ar-SA', {
                style: 'currency', currency: cur, maximumFractionDigits: 2
            }).format(N(v));
        } catch (e) {
            return round(v, 2) + ' ' + cur;
        }
    }

    root.MizanMath = {
        round, sum, mean, median, std, min, max,
        percentage, profitLoss, profitLossPct,
        aggregateBy, countBy,
        filterByMonthsBack, monthlyTotals,
        formatCurrency
    };
})(typeof window !== 'undefined' ? window : this);
