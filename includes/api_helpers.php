<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: api_helpers.php
 * PURPOSE: Shared API response, JSON, and error-handling utilities.
 * OWNER: Mishal Al-jumaih - ScrumMaster & Development Member (Full Stack All Rounder)
 * ========================================================================
 */
require_once __DIR__ . '/security.php';

if (!function_exists('json_out')) {
    /** Emit a JSON response and terminate. */
    function json_out(array $payload, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/**
 * Calculate how much of the budget has been consumed (0–100).
 * Returns 0 when budget is not set.
 */
function calc_budget_pct(float $total_cost, float $budget): int {
    if ($budget <= 0) return 0;
    return min(100, (int) round(($total_cost / $budget) * 100));
}
