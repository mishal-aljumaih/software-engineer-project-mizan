<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: auth.php
 * PURPOSE: Session guard utilities for protecting authenticated routes.
 * OWNER: Mishal Al-jumaih - ScrumMaster & Development Member (Full Stack All Rounder)
 * ========================================================================
 */
// تشغيل الجلسة بإعدادات HttpOnly/Secure/SameSite عبر helper مركّزي
require_once __DIR__ . '/security.php';

mz_send_security_headers();
mz_session_start();

// Guard clause for any page that includes this file — unauthenticated visitors are bounced to /login
// and we preserve where they were trying to go in ?next= so login.php can redirect them back after sign-in
if (!isset($_SESSION['user_id'])) {
    $next = urlencode($_SERVER['REQUEST_URI'] ?? '');
    header('Location: /login.php' . ($next ? '?next=' . $next : ''));
    exit;
}

// إتاحة بيانات المستخدم لكل الصفحات
$current_user_id   = $_SESSION['user_id'];
$current_user_name = $_SESSION['user_name'] ?? '';

// CSRF token — generate once per session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
