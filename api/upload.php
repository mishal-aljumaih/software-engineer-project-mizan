<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: upload.php
 * PURPOSE: Secure file upload endpoint with MIME and size validation.
 * OWNER: Mishal Al-jumaih - ScrumMaster & Development Member (Full Stack All Rounder)
 * ========================================================================
 */
// ============================================================
// Mizan ميزان — api/upload.php
// ------------------------------------------------------------
// Unified upload + delete endpoint for:
//   - invoices   (type=invoice)  → rows in invoices + files
//   - warranties (type=warranty) → warranty.file_path + files
//   - generic    (type=file)     → files table only
//
// Upload storage layout (matches pages/project-detail.php):
//   uploads/{invoices|warranties|files}/{project_id}/{rand}_{time}.{ext}
//
// Max size: 500 MB (server php.ini must permit upload_max_filesize
// and post_max_size ≥ 500M — document this in README).
//
// All responses: { success: bool, error?: string, message?: string, data?: object }
//
// Auth: inline session check (JSON 401 on failure — do NOT use
// includes/auth.php, which redirects to register.php).
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';

require_once __DIR__ . "/../includes/security.php";
mz_send_security_headers();
mz_session_start();

// ── Config ──────────────────────────────────────────────────
// Hard ceiling enforced server-side regardless of php.ini — the client cannot bypass this by editing JS
const MAX_UPLOAD_BYTES = 500 * 1024 * 1024; // 500 MB

const ALLOWED_EXTENSIONS = [
    // images
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
    // docs
    'pdf',
    'doc', 'docx', 'odt', 'rtf', 'txt',
    // spreadsheets
    'xls', 'xlsx', 'csv', 'ods',
    // slides
    'ppt', 'pptx',
    // archives
    'zip', 'rar', '7z',
];
// SVG dropped from the whitelist on purpose: SVG is XML and can carry
// inline <script>, foreignObject, or onload= handlers that Chrome and
// Firefox happily execute when the file is opened directly. Re-add only
// behind an SVG-sanitizer.

// Map allowed extensions to expected MIME prefixes/exact matches. A file
// is rejected when finfo's detected MIME doesn't match the extension.
const EXT_TO_MIME = [
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'gif'  => ['image/gif'],
    'webp' => ['image/webp'],
    'bmp'  => ['image/bmp', 'image/x-ms-bmp'],
    'pdf'  => ['application/pdf'],
    'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/CDFV2'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'odt'  => ['application/vnd.oasis.opendocument.text', 'application/zip'],
    'rtf'  => ['application/rtf', 'text/rtf'],
    'txt'  => ['text/plain'],
    'xls'  => ['application/vnd.ms-excel', 'application/CDFV2', 'application/vnd.ms-office'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    'csv'  => ['text/csv', 'text/plain', 'application/csv'],
    'ods'  => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
    'ppt'  => ['application/vnd.ms-powerpoint', 'application/CDFV2', 'application/vnd.ms-office'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
    'zip'  => ['application/zip', 'application/x-zip-compressed'],
    'rar'  => ['application/x-rar-compressed', 'application/vnd.rar', 'application/octet-stream'],
    '7z'   => ['application/x-7z-compressed', 'application/octet-stream'],
];

// Filename patterns we ALWAYS refuse — even before extension parsing.
// Catches double extensions (file.php.jpg), null-byte injection
// (file.php\0.jpg), embedded server-side script extensions anywhere
// in the filename, and Windows reserved suffix tricks like file.jpg.
const FORBIDDEN_NAME_PATTERNS = [
    '/\x00/',                                                       // null byte
    '/\.(php\d*|phtml|phps|phar|pht)\./i',                          // .php. embedded
    '/\.(php\d*|phtml|phps|phar|pht|asp|aspx|jsp|cgi|pl|py|sh|exe|bat|cmd|js|htaccess)$/i', // dangerous trailing
    '/^\.+/',                                                        // leading dots
];

const ALLOWED_TYPES = ['invoice', 'warranty', 'file'];

// ── Backward-compat: does the files table have the file_kind column? ──
function files_has_kind_column(PDO $pdo): bool {
    static $has = null;
    if ($has !== null) return $has;
    try {
        $s = $pdo->query("SHOW COLUMNS FROM files LIKE 'file_kind'");
        $has = (bool) $s->fetchColumn();
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

// ── Ownership helpers ───────────────────────────────────────
function owns_project(PDO $pdo, int $project_id, int $user_id): bool {
    $s = $pdo->prepare('SELECT 1 FROM projects WHERE id = ? AND user_id = ?');
    $s->execute([$project_id, $user_id]);
    return (bool) $s->fetchColumn();
}

// Defines the owns expense routine.
function owns_expense(PDO $pdo, int $expense_id, int $user_id): bool {
    $s = $pdo->prepare('
        SELECT 1 FROM expenses e
        JOIN projects p ON p.id = e.project_id
        WHERE e.id = ? AND p.user_id = ?
    ');
    $s->execute([$expense_id, $user_id]);
    return (bool) $s->fetchColumn();
}

/** Sandboxed unlink: only touches files inside /uploads/. */
function unlink_rel(string $rel_path): void {
    if ($rel_path === '') return;
    $full = realpath(__DIR__ . '/../' . ltrim($rel_path, '/\\'));
    $root = realpath(__DIR__ . '/../uploads');
    if ($full && $root && str_starts_with($full, $root) && is_file($full)) {
        @unlink($full);
    }
}

// ── Auth gate ───────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    json_out(['success' => false, 'error' => 'err_unauthorized'], 401);
}

// CSRF validation for state-changing requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        json_out(['success' => false, 'error' => 'err_csrf_invalid'], 403);
    }
}

$current_user_id = (int) $_SESSION['user_id'];
$pdo             = getDB();
$action          = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Route ───────────────────────────────────────────────────
try {
    switch ($action) {

        // ══════════════════════════════════════════════════════
        // DELETE INVOICE
        // ══════════════════════════════════════════════════════
        case 'delete_invoice': {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                json_out(['success' => false, 'error' => 'معرّف غير صالح']);
            }

            // Ownership through expense→project chain
            $s = $pdo->prepare('
                SELECT i.id, i.file_path, i.expense_id
                FROM invoices i
                JOIN expenses e ON e.id = i.expense_id
                JOIN projects p ON p.id = e.project_id
                WHERE i.id = ? AND p.user_id = ?
            ');
            $s->execute([$id, $current_user_id]);
            $inv = $s->fetch();

            if (!$inv) {
                json_out(['success' => false, 'error' => 'err_unauthorized'], 403);
            }

            // Remove the DB rows first — both invoices AND the mirror row
            // in files (created at upload time) so the Files page stays clean.
            $pdo->prepare('DELETE FROM invoices WHERE id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM files WHERE expense_id = ? AND file_path = ?')
                ->execute([$inv['expense_id'], $inv['file_path']]);

            unlink_rel((string) $inv['file_path']);

            json_out(['success' => true, 'message' => 'تم حذف الفاتورة']);
        }

        // ══════════════════════════════════════════════════════
        // DELETE GENERIC FILE
        // ══════════════════════════════════════════════════════
        case 'delete_file': {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                json_out(['success' => false, 'error' => 'معرّف غير صالح']);
            }

            $s = $pdo->prepare('
                SELECT f.id, f.file_path
                FROM files f
                JOIN projects p ON p.id = f.project_id
                WHERE f.id = ? AND p.user_id = ?
            ');
            $s->execute([$id, $current_user_id]);
            $f = $s->fetch();

            if (!$f) {
                json_out(['success' => false, 'error' => 'err_unauthorized'], 403);
            }

            // If this file is also referenced as an invoice, clean that too.
            $pdo->prepare('DELETE FROM files    WHERE id = ?')       ->execute([$id]);
            $pdo->prepare('DELETE FROM invoices WHERE file_path = ?')->execute([$f['file_path']]);

            unlink_rel((string) $f['file_path']);

            json_out(['success' => true, 'message' => 'تم الحذف']);
        }

        // ══════════════════════════════════════════════════════
        // DEFAULT: UPLOAD A NEW FILE
        // ══════════════════════════════════════════════════════
        default: {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                json_out(['success' => false, 'error' => 'Method Not Allowed'], 405);
            }

            if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'] ?? '')) {
                json_out(['success' => false, 'error' => 'لم يتم استقبال أي ملف']);
            }

            $file       = $_FILES['file'];
            $raw_type   = (string) ($_POST['type'] ?? 'file');
            $type       = in_array($raw_type, ALLOWED_TYPES, true) ? $raw_type : 'file';
            $project_id = (int) ($_POST['project_id'] ?? 0);
            $expense_id = (int) ($_POST['expense_id'] ?? 0);

            // ── Smart Upload metadata (optional) ──
            $invoice_name     = trim((string)($_POST['invoice_name']     ?? ''));
            $invoice_category = trim((string)($_POST['invoice_category'] ?? ''));
            $purchase_date    = trim((string)($_POST['purchase_date']    ?? ''));
            $expiry_date      = trim((string)($_POST['expiry_date']      ?? ''));
            $contract_title   = trim((string)($_POST['contract_title']   ?? ''));
            $signed_date      = trim((string)($_POST['signed_date']      ?? ''));
            $custom_label     = trim((string)($_POST['custom_label']     ?? ''));
            $file_kind        = trim((string)($_POST['file_kind']        ?? '')); // 'contract' | 'other' | ''
            $price            = (float)   ($_POST['price']               ?? 0);   // Smart-Upload amount

            // ── PHP-level upload error codes ──
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $messages = [
                    UPLOAD_ERR_INI_SIZE   => 'الملف أكبر من الحد الأقصى للخادم',
                    UPLOAD_ERR_FORM_SIZE  => 'الملف أكبر من الحد المسموح في النموذج',
                    UPLOAD_ERR_PARTIAL    => 'تم رفع جزء من الملف فقط',
                    UPLOAD_ERR_NO_FILE    => 'لم يتم اختيار ملف',
                    UPLOAD_ERR_NO_TMP_DIR => 'مجلد مؤقت غير متوفر على الخادم',
                    UPLOAD_ERR_CANT_WRITE => 'فشلت الكتابة على القرص',
                    UPLOAD_ERR_EXTENSION  => 'تم إيقاف الرفع بواسطة إضافة PHP',
                ];
                json_out(['success' => false, 'error' => $messages[$file['error']] ?? 'خطأ في رفع الملف']);
            }

            // ── Size check ──
            $size = (int) $file['size'];
            if ($size <= 0 || $size > MAX_UPLOAD_BYTES) {
                json_out(['success' => false, 'error' => 'حجم الملف تجاوز الحد الأقصى (500MB)']);
            }

            // ── Filename safety: reject null bytes, dangerous extensions
            //    anywhere in the filename, and double-extensions like
            //    invoice.php.jpg. We strip any directory components first
            //    so an attacker can't sneak ../ into the original name.
            $orig_name = basename((string) $file['name']);
            foreach (FORBIDDEN_NAME_PATTERNS as $pat) {
                if (preg_match($pat, $orig_name)) {
                    json_out(['success' => false, 'error' => 'اسم الملف يحتوي امتداداً خطيراً']);
                }
            }

            // Reject filenames with multiple dot-separated extensions where
            // ANY non-final segment is a server-side script type, e.g.
            // "report.php.pdf" or "evil.phtml.jpeg". This catches the case
            // where Apache or a misconfigured proxy might still execute the
            // first matching handler.
            $name_segments = explode('.', $orig_name);
            if (count($name_segments) > 2) {
                $bad_inner = ['php','phtml','phps','phar','pht','php3','php4','php5','php7','asp','aspx','jsp','cgi','pl','py','sh','exe','bat','cmd','js','htaccess'];
                // Check every segment except the first (filename stem) and last (real ext)
                for ($i = 1; $i < count($name_segments) - 1; $i++) {
                    if (in_array(strtolower($name_segments[$i]), $bad_inner, true)) {
                        json_out(['success' => false, 'error' => 'امتداد مزدوج مرفوض']);
                    }
                }
            }

            // ── Extension whitelist check ──
            $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
            if ($ext === '' || !in_array($ext, ALLOWED_EXTENSIONS, true)) {
                json_out(['success' => false, 'error' => 'نوع الملف غير مسموح: .' . $ext]);
            }

            // ── MIME content sniff: confirm the bytes match the extension.
            //    finfo reads the actual file header so a renamed PHP shell
            //    (shell.php → shell.jpg) will be detected and rejected.
            if (!function_exists('finfo_open')) {
                json_out(['success' => false, 'error' => 'إضافة fileinfo غير مفعّلة على الخادم'], 500);
            }
            $finfo     = finfo_open(FILEINFO_MIME_TYPE);
            $real_mime = $finfo ? finfo_file($finfo, (string) $file['tmp_name']) : false;
            if ($finfo) finfo_close($finfo);

            if (!$real_mime) {
                json_out(['success' => false, 'error' => 'تعذّر التحقق من نوع الملف']);
            }
            $allowed_mimes = EXT_TO_MIME[$ext] ?? [];
            if (!in_array($real_mime, $allowed_mimes, true)) {
                json_out([
                    'success' => false,
                    'error'   => 'محتوى الملف لا يطابق الامتداد (' . $ext . ' ↔ ' . $real_mime . ')',
                ]);
            }

            // ── Ownership checks ──
            if ($project_id <= 0 || !owns_project($pdo, $project_id, $current_user_id)) {
                json_out(['success' => false, 'error' => 'المشروع غير موجود أو غير مصرح'], 403);
            }
            if ($expense_id > 0 && !owns_expense($pdo, $expense_id, $current_user_id)) {
                json_out(['success' => false, 'error' => 'العملية غير موجودة'], 403);
            }
            // Expense link is OPTIONAL — the modal labels it "(اختياري)".
            // If the caller passes invoice/warranty without an expense_id
            // we silently downgrade the upload to a project-level "file"
            // record so it still lands in the user's vault.
            if (($type === 'invoice' || $type === 'warranty') && $expense_id <= 0) {
                $type = 'file';
            }

            // ── Destination folder ──
            $folder  = match ($type) {
                'invoice'  => 'invoices',
                'warranty' => 'warranties',
                default    => 'files',
            };
            $dir_rel = "uploads/{$folder}/{$project_id}";
            $dir_abs = __DIR__ . '/../' . $dir_rel;

            if (!is_dir($dir_abs) && !mkdir($dir_abs, 0755, true) && !is_dir($dir_abs)) {
                json_out(['success' => false, 'error' => 'تعذر إنشاء مجلد الرفع']);
            }

            // ── Unique safe filename (no user-supplied chars in filesystem name) ──
            $new_name = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
            $path_abs = $dir_abs . '/' . $new_name;
            $path_rel = $dir_rel . '/' . $new_name;

            if (!move_uploaded_file($file['tmp_name'], $path_abs)) {
                json_out(['success' => false, 'error' => 'فشل نقل الملف']);
            }

            // ── Classification for invoices.file_type ──
            $is_image  = in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','svg'], true);
            $file_type = $is_image ? 'image' : ($ext === 'pdf' ? 'pdf' : 'doc');

            // ── Transactional DB write (rollback deletes the moved file) ──
            $pdo->beginTransaction();
            try {
                if ($type === 'invoice') {
                    // Write to invoices AND mirror into files so the Files
                    // page sees it (same pattern as pages/project-detail.php).
                    $pdo->prepare('
                        INSERT INTO invoices (expense_id, file_path, file_type)
                        VALUES (?, ?, ?)
                    ')->execute([$expense_id, $path_rel, $file_type]);

                    // Display name: user-provided invoice name overrides original
                    $display_name = $invoice_name !== '' ? ($invoice_name . '.' . $ext) : $orig_name;
                    if (files_has_kind_column($pdo)) {
                        $pdo->prepare('
                            INSERT INTO files (project_id, expense_id, file_name, file_path, file_size, file_kind)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ')->execute([$project_id, $expense_id, $display_name, $path_rel, $size, 'invoice']);
                    } else {
                        $pdo->prepare('
                            INSERT INTO files (project_id, expense_id, file_name, file_path, file_size)
                            VALUES (?, ?, ?, ?, ?)
                        ')->execute([$project_id, $expense_id, $display_name, $path_rel, $size]);
                    }

                    // Update expense amount if price was supplied with the invoice
                    if ($price > 0 && $expense_id > 0) {
                        $pdo->prepare('UPDATE expenses SET amount = ? WHERE id = ?')
                            ->execute([$price, $expense_id]);
                    }

                } elseif ($type === 'warranty') {
                    $w_start = $purchase_date !== '' ? $purchase_date : date('Y-m-d');
                    $w_end   = $expiry_date   !== '' ? $expiry_date   : date('Y-m-d', strtotime('+1 year'));

                    // Attach to existing warranty row or create a stub if absent.
                    $s = $pdo->prepare('SELECT id FROM warranties WHERE expense_id = ? LIMIT 1');
                    $s->execute([$expense_id]);
                    $wid = (int) $s->fetchColumn();

                    if ($wid > 0) {
                        $pdo->prepare('UPDATE warranties SET file_path = ?, start_date = ?, end_date = ? WHERE id = ?')
                            ->execute([$path_rel, $w_start, $w_end, $wid]);
                    } else {
                        $pdo->prepare('
                            INSERT INTO warranties (expense_id, start_date, end_date, file_path)
                            VALUES (?, ?, ?, ?)
                        ')->execute([$expense_id, $w_start, $w_end, $path_rel]);
                    }

                    if (files_has_kind_column($pdo)) {
                        $pdo->prepare('
                            INSERT INTO files (project_id, expense_id, file_name, file_path, file_size, file_kind)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ')->execute([$project_id, $expense_id, $orig_name, $path_rel, $size, 'warranty']);
                    } else {
                        $pdo->prepare('
                            INSERT INTO files (project_id, expense_id, file_name, file_path, file_size)
                            VALUES (?, ?, ?, ?, ?)
                        ')->execute([$project_id, $expense_id, $orig_name, $path_rel, $size]);
                    }

                    // Update expense amount if price was supplied with the warranty
                    if ($price > 0 && $expense_id > 0) {
                        $pdo->prepare('UPDATE expenses SET amount = ? WHERE id = ?')
                            ->execute([$price, $expense_id]);
                    }

                } else {
                    // Generic file (also handles contract/other from Smart Upload)
                    $display_name = $orig_name;
                    $kind_out     = 'other';
                    if ($file_kind === 'contract' && $contract_title !== '') {
                        $display_name = '[' . ($signed_date ?: date('Y-m-d')) . '] ' . $contract_title . '.' . $ext;
                        $kind_out     = 'contract';
                    } elseif ($file_kind === 'contract') {
                        $kind_out     = 'contract';
                    } elseif ($file_kind === 'other' && $custom_label !== '') {
                        $display_name = $custom_label . '.' . $ext;
                    }
                    if (files_has_kind_column($pdo)) {
                        $pdo->prepare('
                            INSERT INTO files (project_id, expense_id, file_name, file_path, file_size, file_kind)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ')->execute([
                            $project_id,
                            $expense_id > 0 ? $expense_id : null,
                            $display_name,
                            $path_rel,
                            $size,
                            $kind_out,
                        ]);
                    } else {
                        $pdo->prepare('
                            INSERT INTO files (project_id, expense_id, file_name, file_path, file_size)
                            VALUES (?, ?, ?, ?, ?)
                        ')->execute([
                            $project_id,
                            $expense_id > 0 ? $expense_id : null,
                            $display_name,
                            $path_rel,
                            $size,
                        ]);
                    }
                }

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                // Orphan cleanup: kill the file we just moved so FS & DB stay consistent.
                if (is_file($path_abs)) {
                    @unlink($path_abs);
                }
                throw $e;
            }

            json_out([
                'success' => true,
                'message' => 'تم الرفع بنجاح',
                'data'    => [
                    'path' => $path_rel,
                    'name' => $orig_name,
                    'size' => $size,
                    'type' => $file_type,
                    'kind' => $type,
                ],
            ]);
        }
    }
} catch (Throwable $e) {
    json_out([
        'success' => false,
        'error'   => 'خطأ في الخادم: ' . $e->getMessage(),
    ], 500);
}