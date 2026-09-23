<?php
// Step-up ("sudo mode") re-authentication for sensitive pages: the admin
// control panel and account settings. A valid login session isn't enough on
// its own to reach these — the password must have been re-confirmed within
// REAUTH_WINDOW_SECONDS, or the visitor is bounced to auth/reauth.php first.
require_once __DIR__ . '/url.php';

if (!defined('REAUTH_WINDOW_SECONDS')) {
    define('REAUTH_WINDOW_SECONDS', 900); // 15 minutes
}

if (!function_exists('require_reauth')) {
    function require_reauth(): void {
        $verifiedAt = $_SESSION['reauth_at'] ?? null;
        if (is_int($verifiedAt) && (time() - $verifiedAt) <= REAUTH_WINDOW_SECONDS) {
            return;
        }

        $next = $_SERVER['REQUEST_URI'] ?? '/';
        $next = is_safe_local_redirect($next) ?? '/';

        header('Location: ' . base_url('/auth/reauth.php') . '?next=' . urlencode($next));
        exit();
    }
}
