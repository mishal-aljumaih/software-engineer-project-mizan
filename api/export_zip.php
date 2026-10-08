<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: export_zip.php
 * PURPOSE: Bundles a project's files into a downloadable ZIP archive.
 * OWNER: Mishal Al-jumaih - ScrumMaster & Development Member (Full Stack All Rounder)
 * ========================================================================
 */
require_once __DIR__ . "/../includes/security.php";
mz_send_security_headers();
mz_session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'err_unauthorized']);
    exit;
}

// PHP's zip extension is optional. Fail loud + early so the user gets a
// real error instead of a 500 with "Class ZipArchive not found".
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => 'إضافة ZIP غير مفعّلة على الخادم (php_zip). الرجاء تفعيلها في php.ini.',
    ]);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$user_id    = (int) $_SESSION['user_id'];
$project_id = (int) ($_GET['project_id'] ?? 0);
$pdo        = getDB();

if ($project_id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'معرّف المشروع مطلوب']);
    exit;
}

// IDOR guard — only the project's owner can download its ZIP; a forged ?project_id= for another user's project returns 403
$s = $pdo->prepare('SELECT name FROM projects WHERE id = ? AND user_id = ?');
$s->execute([$project_id, $user_id]);
$project = $s->fetch();

if (!$project) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'err_unauthorized']);
    exit;
}

// Collect all file paths from three sources
$paths = [];
$base  = realpath(__DIR__ . '/..');

// 1. Invoices (via expenses → project)
$s = $pdo->prepare('
    SELECT i.file_path FROM invoices i
    JOIN expenses e ON i.expense_id = e.id
    WHERE e.project_id = ?
');
$s->execute([$project_id]);
while ($r = $s->fetch()) {
    if ($r['file_path']) $paths[] = $r['file_path'];
}

// 2. Warranty files
$s = $pdo->prepare('
    SELECT w.file_path FROM warranties w
    JOIN expenses e ON w.expense_id = e.id
    WHERE e.project_id = ?
      AND w.file_path IS NOT NULL AND w.file_path <> ""
');
$s->execute([$project_id]);
while ($r = $s->fetch()) {
    if ($r['file_path']) $paths[] = $r['file_path'];
}

// 3. General files
$s = $pdo->prepare('SELECT file_path FROM files WHERE project_id = ?');
$s->execute([$project_id]);
while ($r = $s->fetch()) {
    if ($r['file_path']) $paths[] = $r['file_path'];
}

$paths = array_unique(array_filter($paths));

if (empty($paths)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'لا توجد ملفات للتصدير']);
    exit;
}

// Build ZIP in a writable temp file. Prefer the project-local /tmp/zip
// directory so we don't depend on sys_get_temp_dir() permissions in
// shared hosting / XAMPP environments. Auto-create on first run.
$tmp_dir = $base . '/tmp/zip';
if (!is_dir($tmp_dir)) {
    @mkdir($tmp_dir, 0777, true);
}
if (!is_dir($tmp_dir) || !is_writable($tmp_dir)) {
    // Fallback to system temp.
    $tmp_dir = sys_get_temp_dir();
}

$tmp = tempnam($tmp_dir, 'mzn_');
if ($tmp === false) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => 'تعذّر إنشاء ملف مؤقّت — تأكّد من صلاحيات الكتابة على ' . $tmp_dir,
    ]);
    exit;
}

$zip = new ZipArchive();
// CREATE|OVERWRITE handles both "file just created by tempnam" and
// "file does not yet exist" cases. ZipArchive::open returns either
// true on success or one of the ZipArchive::ER_* error codes.
$rc  = $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
if ($rc !== true) {
    @unlink($tmp);
    $err_map = [
        ZipArchive::ER_EXISTS => 'الملف موجود مسبقاً',
        ZipArchive::ER_INCONS => 'أرشيف غير متّسق',
        ZipArchive::ER_INVAL  => 'وسيط غير صالح',
        ZipArchive::ER_MEMORY => 'فشل تخصيص الذاكرة',
        ZipArchive::ER_NOENT  => 'الملف غير موجود',
        ZipArchive::ER_NOZIP  => 'ليس أرشيف ZIP',
        ZipArchive::ER_OPEN   => 'تعذّر فتح الملف للكتابة',
        ZipArchive::ER_READ   => 'خطأ في القراءة',
        ZipArchive::ER_SEEK   => 'خطأ في seek',
    ];
    $msg = $err_map[$rc] ?? "ZipArchive error code {$rc}";
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => "تعذّر إنشاء ملف ZIP: {$msg}"]);
    exit;
}

$added        = 0;
$uploads_root = realpath($base . '/uploads');
foreach ($paths as $rel) {
    $full = realpath($base . '/' . $rel);
    // Sandboxed: must be an absolute path under uploads/.
    if (!$full || !$uploads_root || strpos($full, $uploads_root) !== 0 || !is_file($full)) {
        continue;
    }
    // Entry name: preserve subfolder structure under uploads/.
    // ZipArchive::addFile takes an ABSOLUTE filesystem path ($full)
    // and a relative archive entry name ($entry).
    $entry = str_replace('\\', '/', substr($full, strlen($uploads_root) + 1));
    if (!$zip->addFile($full, $entry)) {
        // Skip silently; we'll fail loudly only if zero files end up added.
        continue;
    }
    $added++;
}

if (!$zip->close()) {
    @unlink($tmp);
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'تعذّر إغلاق ملف ZIP']);
    exit;
}

if ($added === 0) {
    @unlink($tmp);
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'لا توجد ملفات فعلية على الخادم']);
    exit;
}

// Stream the ZIP
if (!file_exists($tmp)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'ZIP file not found']);
    exit;
}

$safe_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $project['name']);
$filename  = "mizan_{$safe_name}_{$project_id}.zip";

// Clear EVERY active output buffer — a single stray byte (UTF-8 BOM,
// PHP notice, trailing whitespace from an include) corrupts the ZIP.
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/zip');
header('Content-Description: File Transfer');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
flush();

logActivity($pdo, $user_id, 'export_data', $project['name']);
readfile($tmp);
@unlink($tmp);
exit;