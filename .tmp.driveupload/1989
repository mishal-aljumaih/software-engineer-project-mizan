<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: security.php
 * PURPOSE: CSRF token issuance/validation and XSS sanitization helpers.
 * OWNER: Mishal Al-jumaih - ScrumMaster & Development Member (Full Stack All Rounder)
 * ========================================================================
 */
if (!function_exists('mz_send_security_headers')) {
    /**
     * Adds defensive HTTP headers. Mirrors the Apache .htaccess block
     * so the protection survives even if mod_headers is unavailable
     * (PHP-CLI dev server, hosts that strip Apache directives, etc.).
     */
    function mz_send_security_headers(): void {
        if (headers_sent()) return;

        header_remove('X-Powered-By');
        @ini_set('expose_php', '0');

        // X-Frame-Options:DENY  — block clickjacking by refusing to render in any iframe
        header('X-Frame-Options: DENY');
        // X-Content-Type-Options:nosniff — stop browsers from MIME-sniffing (defends against XSS via mismatched types)
        header('X-Content-Type-Options: nosniff');
        // Referrer-Policy — leak only origin (not the full URL) when navigating to other sites
        header('Referrer-Policy: strict-origin-when-cross-origin');
        // Permissions-Policy — explicitly disable powerful APIs we don't use
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443);

        // HSTS — only meaningful over HTTPS; forces clients to use TLS for the next year
        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // 'unsafe-inline' is required for SweetAlert2 and page-level <style> blocks.
        // 'unsafe-eval' is required by Chart.js 4.x (uses Function() internally).
        // Accepted risk: both are mitigated by strict server-side output escaping and
        // parameterized queries. Long-term fix: migrate to nonce-based CSP + Chart.js ESM build.
        header(
            "Content-Security-Policy: " .
            "default-src 'self' 'unsafe-inline' 'unsafe-eval' " .
            "https://cdn.jsdelivr.net https://fonts.googleapis.com " .
            "https://fonts.gstatic.com https://static.cloudflareinsights.com; " .
            "img-src 'self' data: https:; " .
            "connect-src 'self' https:; " .
            "frame-ancestors 'none';"
        );
    }
}

if (!function_exists('mz_session_start')) {
    /**
     * Starts a session with HttpOnly + Secure (when HTTPS) + SameSite=Lax
     * cookie params. Idempotent — does nothing if a session is already
     * running (won't break legacy includes that called session_start()).
     */
    function mz_session_start(): void {
        if (session_status() !== PHP_SESSION_NONE) return;

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443);

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name('MIZANSESSID');
        session_start();
    }
}

if (!function_exists('mz_validate_email')) {
    /**
     * Strict email validation:
     *   1. PHP filter_var passes (RFC 5321 subset).
     *   2. Domain has at least one dot (rejects ex@ex).
     *   3. DNS lookup finds an MX or A record (rejects fake domains).
     *
     * Returns true / false. The DNS check is skipped on Windows + non-https
     * dev environments only when the optional $dns flag is false.
     */
    function mz_validate_email(string $email, bool $dns = true): bool {
        $email = trim($email);
        if ($email === '') return false;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) return false;
        $domain = $parts[1];

        if (strpos($domain, '.') === false) return false;
        if (!preg_match('/\.[a-z]{2,}$/i', $domain)) return false;

        if ($dns && function_exists('checkdnsrr')) {
            // checkdnsrr can be slow on Windows; cap at 3s via timeout if available.
            if (!@checkdnsrr($domain, 'MX') && !@checkdnsrr($domain, 'A')) {
                return false;
            }
        }
        return true;
    }
}
