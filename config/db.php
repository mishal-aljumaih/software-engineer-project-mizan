<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: db.php
 * PURPOSE: PDO database connection and shared currency/formatting helpers.
 * ========================================================================
 */
// Fix PHP's clock to Saudi local time so created_at columns and date math match the user's expectations
date_default_timezone_set('Asia/Riyadh');
// ── Load .env (lightweight parser, no external libraries) ───
// We hand-roll a parser to avoid Composer/vlucas/dotenv. Credentials live outside source control.
(function () {
    $envFile = __DIR__ . '/.env';
    if (!is_readable($envFile)) return;
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        // Strip optional surrounding quotes
        if (strlen($val) >= 2 && $val[0] === '"' && $val[-1] === '"') {
            $val = substr($val, 1, -1);
        }
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $val;
            putenv("$key=$val");
        }
    }
})();

/** Read a value from the loaded environment with an optional default. */
function env(string $key, string $default = ''): string {
    return $_ENV[$key] ?? (getenv($key) ?: $default);
}

define('DB_HOST',    env('DB_HOST',    '127.0.0.1'));
define('DB_PORT',    env('DB_PORT',    '3307'));
define('DB_NAME',    env('DB_NAME',    'mizan_db'));
define('DB_USER',    env('DB_USER',    'root'));
define('DB_PASS',    env('DB_PASS',    ''));
define('DB_CHARSET', 'utf8mb4');

// Canonical app URL — used for reset links and redirects; never trust HTTP_HOST
define('APP_URL', env('APP_URL', 'http://localhost'));

// Returns the singleton PDO connection (lazy-init, reused across all callers in the request).
// PDO::ATTR_EMULATE_PREPARES=false forces real server-side prepared statements — defense against SQL injection.
function getDB() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST
                 . ";port="     . DB_PORT
                 . ";dbname="   . DB_NAME
                 . ";charset="  . DB_CHARSET;

            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("SET time_zone = '+03:00'");
        } catch (PDOException $e) {
            error_log('DB connection error: ' . $e->getMessage());
            http_response_code(500);
            die('خطأ في الاتصال بقاعدة البيانات. يرجى المحاولة لاحقاً.');
        }
    }

    return $pdo;
}

/**
 * Log a user action to activity_logs.
 * Never throws — logging must never crash the calling flow.
 */
function logActivity(PDO $pdo, int $user_id, string $action_type, string $details = ''): void {
    try {
        $raw_ip = $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0])
            ?: ($_SERVER['REMOTE_ADDR'] ?? 'Unknown');
        $ip = filter_var(trim($raw_ip), FILTER_VALIDATE_IP) ?: '0.0.0.0';
        $pdo->prepare('
            INSERT INTO activity_logs (user_id, action_type, details, ip_address)
            VALUES (?, ?, ?, ?)
        ')->execute([$user_id, $action_type, mb_substr($details, 0, 500), $ip]);
    } catch (Throwable $e) { /* intentionally silent */ }
}

/**
 * Return the active currency code for the logged-in user.
 * Reads from session first, falls back to DB lookup, defaults to SAR.
 */
function mz_currency_code(): string {
    if (!empty($_SESSION['currency'])) return strtoupper($_SESSION['currency']);
    if (!empty($_SESSION['user_id'])) {
        try {
            $stmt = getDB()->prepare("SELECT currency FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$_SESSION['user_id']]);
            $c = $stmt->fetchColumn();
            if ($c) { $_SESSION['currency'] = strtoupper($c); return $_SESSION['currency']; }
        } catch (\Throwable $e) { /* fall through */ }
    }
    return 'SAR';
}

/**
 * Resolve the localized currency symbol for the current request.
 * SAR → ﷼ (ar) / SAR (en); USD → $; falls back to code for the rest.
 */
function mz_currency_symbol(?string $lang = null): string {
    $code = mz_currency_code();
    $lang = $lang ?: (($_COOKIE['mizan_lang'] ?? '') === 'en' ? 'en' : 'ar');
    if ($code === 'SAR') return $lang === 'en' ? 'SAR' : '﷼';
    if ($code === 'USD') return '$';
    $map = ['EUR'=>'€','GBP'=>'£','AED'=>'د.إ','KWD'=>'د.ك'];
    return $map[$code] ?? $code;
}

/** Format a number with the active currency suffix. */
function mz_format_money($amount, int $decimals = 0, ?string $lang = null): string {
    return number_format((float)$amount, $decimals) . ' ' . mz_currency_symbol($lang);
}
