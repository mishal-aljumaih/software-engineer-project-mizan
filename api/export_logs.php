<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: export_logs.php
 * PURPOSE: Exports user activity and audit logs as CSV.
 * OWNER: Mishal Al-jumaih - ScrumMaster & Development Member (Full Stack All Rounder)
 * ========================================================================
 */
require_once __DIR__ . "/../includes/security.php";
mz_send_security_headers();
mz_session_start();

// Admin-only export — activity_logs contain sensitive cross-user data; non-admins must never see this
if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'err_unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$pdo = getDB();

$lang = in_array($_GET['lang'] ?? '', ['ar', 'en']) ? $_GET['lang'] : 'ar';

$stmt = $pdo->query('
    SELECT a.action_type, a.details, a.ip_address, a.created_at,
           u.name, u.email
    FROM   activity_logs a
    JOIN   users u ON a.user_id = u.id
    ORDER  BY a.created_at DESC
    LIMIT  50000
');
$rows = $stmt->fetchAll();

$labels = $lang === 'en' ? [
    'user_login'     => 'Login',
    'user_logout'    => 'Logout',
    'create_project' => 'Create Project',
    'export_data'    => 'Export Data',
] : [
    'user_login'     => 'تسجيل دخول',
    'user_logout'    => 'تسجيل خروج',
    'create_project' => 'إنشاء مشروع',
    'export_data'    => 'تصدير بيانات',
];

$csv_headers = $lang === 'en'
    ? ['Name', 'Email', 'Date & Time', 'Action', 'Details', 'IP Address']
    : ['الاسم', 'البريد الإلكتروني', 'التاريخ والوقت', 'نوع النشاط', 'التفاصيل', 'عنوان IP'];

// Clear any output buffers — prevents BOM corruption from stray whitespace
while (ob_get_level() > 0) {
    ob_end_clean();
}

$filename = 'mizan_all_logs_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// UTF-8 BOM — required for Excel to auto-detect Arabic encoding
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

fputcsv($out, $csv_headers);

foreach ($rows as $row) {
    fputcsv($out, [
        $row['name'],
        $row['email'],
        $row['created_at'],
        $labels[$row['action_type']] ?? $row['action_type'],
        $row['details'],
        $row['ip_address'],
    ]);
}

fclose($out);
exit;
