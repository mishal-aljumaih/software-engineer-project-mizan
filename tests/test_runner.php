<?php
// ============================================================
// Mizan — tests/test_runner.php
// Lightweight smoke-test runner: DB connection, session, HTTP 200
// Usage: php tests/test_runner.php   (CLI)
//    or: http://localhost/tests/test_runner.php (browser)
// ============================================================

declare(strict_types=1);

$is_cli = php_sapi_name() === 'cli';
$nl     = $is_cli ? "\n" : "<br>";
$pass   = 0;
$fail   = 0;
$tests  = [];

function result(string $name, bool $ok, string $detail = ''): void {
    global $pass, $fail, $tests, $nl;
    $ok ? $pass++ : $fail++;
    $icon   = $ok ? '[PASS]' : '[FAIL]';
    $tests[] = compact('name', 'ok', 'detail');
    echo "{$icon} {$name}" . ($detail ? " — {$detail}" : '') . $nl;
}

if (!$is_cli) {
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Mizan Tests</title>'
       . '<style>body{font-family:monospace;padding:20px;background:#0f172a;color:#e2e8f0;line-height:2;}'
       . '[PASS]{color:#22c55e;}[FAIL]{color:#ef4444;}</style></head><body>'
       . '<h2>Mizan Test Runner</h2>';
}

echo "=== Mizan Smoke Tests ==={$nl}{$nl}";

// ── 1. Database Connection ──────────────────────────────────
echo "--- Database ---{$nl}";
try {
    require_once __DIR__ . '/../config/db.php';
    $pdo = getDB();
    $pdo->query('SELECT 1');
    result('DB Connection', true, 'PDO connected to mizan_db');
} catch (Throwable $e) {
    result('DB Connection', false, $e->getMessage());
}

// ── 2. Required Tables Exist ────────────────────────────────
$required_tables = ['users', 'projects', 'expenses', 'warranties', 'invoices', 'files', 'notifications', 'password_resets'];
try {
    $existing = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $existing[] = $row[0];
    }
    foreach ($required_tables as $t) {
        result("Table: {$t}", in_array($t, $existing), in_array($t, $existing) ? 'exists' : 'MISSING');
    }
} catch (Throwable $e) {
    result('Table Check', false, $e->getMessage());
}

// ── 3. Session Functionality ────────────────────────────────
echo "{$nl}--- Session ---{$nl}";
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['_test'] = 'mizan_ok';
    $ok = ($_SESSION['_test'] === 'mizan_ok');
    unset($_SESSION['_test']);
    result('Session Read/Write', $ok);
} catch (Throwable $e) {
    result('Session', false, $e->getMessage());
}

// ── 4. Required Files Exist ─────────────────────────────────
echo "{$nl}--- Required Files ---{$nl}";
$required_files = [
    'config/db.php',
    'config/mail.php',
    'includes/auth.php',
    'includes/header.php',
    'includes/footer.php',
    'includes/notifications_helper.php',
    'assets/js/main.js',
    'assets/css/style.css',
    'login.php',
    'register.php',
    'index.php',
    'api/projects.php',
    'api/expenses.php',
    'api/upload.php',
    'api/notifications.php',
    'api/settings.php',
    'pages/projects.php',
    'pages/project-detail.php',
    'pages/invoices.php',
    'pages/warranties.php',
    'pages/files.php',
    'pages/reports.php',
    'pages/settings.php',
];

$base = realpath(__DIR__ . '/../');
foreach ($required_files as $f) {
    $exists = is_file($base . '/' . $f);
    result("File: {$f}", $exists);
}

// ── 5. PHP Lint Check ───────────────────────────────────────
echo "{$nl}--- PHP Lint ---{$nl}";
$php_files = array_filter($required_files, fn($f) => str_ends_with($f, '.php'));
foreach ($php_files as $f) {
    $path = $base . '/' . $f;
    if (!is_file($path)) continue;
    $output = [];
    $code   = 0;
    exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $code);
    result("Lint: {$f}", $code === 0, $code !== 0 ? implode(' ', $output) : '');
}

// ── 6. HTTP 200 Check (if running in web context) ───────────
if (!$is_cli && isset($_SERVER['HTTP_HOST'])) {
    echo "{$nl}--- HTTP 200 Checks ---{$nl}";
    $base_url = 'http://' . $_SERVER['HTTP_HOST'] . '/mizan';

    $public_pages = [
        '/login.php',
        '/register.php',
        '/forgot-password.php',
    ];

    foreach ($public_pages as $page) {
        $url = $base_url . $page;
        $ctx = stream_context_create([
            'http' => [
                'timeout'       => 5,
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
        ]);
        $headers = @get_headers($url, true, $ctx);
        $status  = $headers ? (int) substr($headers[0], 9, 3) : 0;
        // 200 or 302 (redirect to index if already logged in) are both acceptable
        $ok = in_array($status, [200, 302]);
        result("HTTP {$page}", $ok, "status={$status}");
    }

    // API endpoints (expect 401 since no session)
    $api_endpoints = [
        '/api/projects.php?action=list',
        '/api/expenses.php?action=list&project_id=1',
        '/api/notifications.php?action=list',
    ];
    foreach ($api_endpoints as $ep) {
        $url = $base_url . $ep;
        $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $ctx);
        $json = $body ? json_decode($body, true) : null;
        // Should return valid JSON (even if 401)
        $ok = is_array($json) && isset($json['success']);
        result("API {$ep}", $ok, $ok ? 'valid JSON response' : 'invalid response');
    }
} else {
    echo "{$nl}(HTTP checks skipped — run in browser for full test){$nl}";
}

// ── Summary ─────────────────────────────────────────────────
echo "{$nl}=== Summary ==={$nl}";
echo "Passed: {$pass}  |  Failed: {$fail}  |  Total: " . ($pass + $fail) . $nl;

if ($fail > 0) {
    echo "{$nl}FAILED TESTS:{$nl}";
    foreach ($tests as $t) {
        if (!$t['ok']) echo "  - {$t['name']}: {$t['detail']}{$nl}";
    }
}

$exit_code = $fail > 0 ? 1 : 0;
echo "{$nl}" . ($fail === 0 ? 'ALL TESTS PASSED' : "SOME TESTS FAILED ({$fail})") . $nl;

if (!$is_cli) echo '</body></html>';
if ($is_cli) exit($exit_code);