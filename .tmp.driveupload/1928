<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: reports.php
 * PURPOSE: Reports API: chart datasets and aggregated analytics.
 * OWNER: Alwaleed Alzahrani - Development & Database Admin (Lead Programmer)
 * ========================================================================
 */
// Returns aggregated analytics data for the reports dashboard.
// All queries are ownership-scoped via $_SESSION['user_id'].
//
// Parameters (GET or JSON body):
//   start_date  — optional YYYY-MM-DD (defaults to 12 months ago)
//   end_date    — optional YYYY-MM-DD (defaults to today)
//
// Response shape:
// {
//   success: true,
//   data: {
//     summary:    { total_spent, active_projects, expiring_warranties, top_category },
//     monthly:    [ { month, total } ... ],
//     categories: [ { category, total } ... ],
//     budgets:    [ { name, budget, actual } ... ],
//     warranties: { active, expiring, expired }
//   }
// }
// ============================================================

require_once __DIR__ . "/../includes/security.php";
mz_send_security_headers();
mz_session_start();
header('Content-Type: application/json; charset=utf-8');

// Reports are personal — block anonymous access; every downstream query is scoped to this user_id
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'err_unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$user_id = (int) $_SESSION['user_id'];
$pdo     = getDB();

// ── Date range parsing ──────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$raw_start  = $input['start_date']  ?? $_GET['start_date']  ?? '';
$raw_end    = $input['end_date']    ?? $_GET['end_date']    ?? '';
$project_id = (int) ($input['project_id'] ?? $_GET['project_id'] ?? 0);

// Validate YYYY-MM-DD or fall back to sensible defaults
function valid_date(string $d): bool {
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
}

$end_date   = valid_date($raw_end)   ? $raw_end   : date('Y-m-d');
$start_date = valid_date($raw_start) ? $raw_start : date('Y-m-d', strtotime('-12 months'));

// Clamp: start must not exceed end
if ($start_date > $end_date) {
    [$start_date, $end_date] = [$end_date, $start_date];
}

// ── Optional project filter ─────────────────────────────────
// When $project_id > 0, all expense-based queries restrict to that project.
$pid_clause = $project_id > 0 ? 'AND p.id = ?' : '';

// ── 1. Summary Statistics ───────────────────────────────────

// Total spent in range
$params = $project_id > 0
    ? [$user_id, $start_date, $end_date, $project_id]
    : [$user_id, $start_date, $end_date];
$s = $pdo->prepare("
    SELECT COALESCE(SUM(e.amount), 0) AS total_spent
    FROM expenses e
    JOIN projects p ON e.project_id = p.id
    WHERE p.user_id = ?
      AND e.purchase_date BETWEEN ? AND ?
      $pid_clause
");
$s->execute($params);
$total_spent = (float) $s->fetchColumn();

// Active projects count
$cnt_params = $project_id > 0
    ? [$user_id, 'active', $project_id]
    : [$user_id, 'active'];
$pid_p_clause = $project_id > 0 ? 'AND id = ?' : '';
$s = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE user_id = ? AND status = ? $pid_p_clause");
$s->execute($cnt_params);
$active_projects = (int) $s->fetchColumn();

// Warranties expiring within 30 days
$w_params = $project_id > 0 ? [$user_id, $project_id] : [$user_id];
$s = $pdo->prepare("
    SELECT COUNT(*) FROM warranties w
    JOIN expenses e ON w.expense_id = e.id
    JOIN projects p ON e.project_id = p.id
    WHERE p.user_id = ?
      AND w.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
      $pid_clause
");
$s->execute($w_params);
$expiring_warranties = (int) $s->fetchColumn();

// Most expensive category in range
$s = $pdo->prepare("
    SELECT e.category, SUM(e.amount) AS cat_total
    FROM expenses e
    JOIN projects p ON e.project_id = p.id
    WHERE p.user_id = ?
      AND e.purchase_date BETWEEN ? AND ?
      $pid_clause
    GROUP BY e.category
    ORDER BY cat_total DESC
    LIMIT 1
");
$s->execute($params);
$top_row = $s->fetch();
$top_category = $top_row ? ['name' => $top_row['category'], 'total' => (float) $top_row['cat_total']] : null;

// ── 2. Monthly Spending (Line Chart) ────────────────────────
$s = $pdo->prepare("
    SELECT DATE_FORMAT(e.purchase_date, '%Y-%m') AS month,
           SUM(e.amount) AS total
    FROM expenses e
    JOIN projects p ON e.project_id = p.id
    WHERE p.user_id = ?
      AND e.purchase_date BETWEEN ? AND ?
      $pid_clause
    GROUP BY month
    ORDER BY month ASC
");
$s->execute($params);
$monthly = $s->fetchAll();

// Fill in missing months so the line chart is continuous
$filled_monthly = [];
$cursor = new DateTime($start_date);
$end_dt = new DateTime($end_date);
$cursor->modify('first day of this month');
$end_dt->modify('first day of this month');

$monthly_map = [];
foreach ($monthly as $row) {
    $monthly_map[$row['month']] = (float) $row['total'];
}

while ($cursor <= $end_dt) {
    $key = $cursor->format('Y-m');
    $filled_monthly[] = [
        'month' => $key,
        'total' => $monthly_map[$key] ?? 0,
    ];
    $cursor->modify('+1 month');
}

// ── 3. Category Breakdown (Donut Chart) ─────────────────────
$s = $pdo->prepare("
    SELECT e.category, SUM(e.amount) AS total
    FROM expenses e
    JOIN projects p ON e.project_id = p.id
    WHERE p.user_id = ?
      AND e.purchase_date BETWEEN ? AND ?
      $pid_clause
    GROUP BY e.category
    ORDER BY total DESC
");
$s->execute($params);
$categories = $s->fetchAll();

// Cast totals to float
$categories = array_map(function ($r) {
    return ['category' => $r['category'], 'total' => (float) $r['total']];
}, $categories);

// ── 4. Budget vs Actual (Bar Chart) ─────────────────────────
$bgt_params = $project_id > 0
    ? [$start_date, $end_date, $user_id, $project_id]
    : [$start_date, $end_date, $user_id];
$s = $pdo->prepare("
    SELECT p.name,
           p.budget,
           COALESCE(SUM(e.amount), 0) AS actual
    FROM projects p
    LEFT JOIN expenses e ON e.project_id = p.id
             AND e.purchase_date BETWEEN ? AND ?
    WHERE p.user_id = ?
      AND p.budget > 0
      $pid_clause
    GROUP BY p.id, p.name, p.budget
    ORDER BY actual DESC
    LIMIT 15
");
$s->execute($bgt_params);
$budgets_raw = $s->fetchAll();
$budgets = array_map(function ($r) {
    return [
        'name'   => $r['name'],
        'budget' => (float) $r['budget'],
        'actual' => (float) $r['actual'],
    ];
}, $budgets_raw);

// ── 5. Warranty Status (Pie Chart) ──────────────────────────
// Active  = end_date > today + 30 days
// Expiring = end_date between today and today + 30
// Expired  = end_date < today

$s = $pdo->prepare("
    SELECT
        SUM(CASE WHEN w.end_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN w.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS expiring,
        SUM(CASE WHEN w.end_date < CURDATE() THEN 1 ELSE 0 END) AS expired
    FROM warranties w
    JOIN expenses e ON w.expense_id = e.id
    JOIN projects p ON e.project_id = p.id
    WHERE p.user_id = ?
      $pid_clause
");
$s->execute($w_params);
$wstat = $s->fetch();
$warranties = [
    'active'   => (int) ($wstat['active']   ?? 0),
    'expiring' => (int) ($wstat['expiring'] ?? 0),
    'expired'  => (int) ($wstat['expired']  ?? 0),
];

// ── Response ────────────────────────────────────────────────
echo json_encode([
    'success' => true,
    'data'    => [
        'date_range'  => ['start' => $start_date, 'end' => $end_date],
        'summary'     => [
            'total_spent'          => $total_spent,
            'active_projects'      => $active_projects,
            'expiring_warranties'  => $expiring_warranties,
            'top_category'         => $top_category,
        ],
        'monthly'     => $filled_monthly,
        'categories'  => $categories,
        'budgets'     => $budgets,
        'warranties'  => $warranties,
    ],
], JSON_UNESCAPED_UNICODE);