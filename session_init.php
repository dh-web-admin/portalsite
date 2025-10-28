<?php
// Centralized session bootstrap for Railway/FrankenPHP
// Ensures sessions persist by using a writable save path and sane cookie settings

if (session_status() === PHP_SESSION_NONE) {
    // Use file-based sessions and a writable path
    @ini_set('session.save_handler', 'files');
    $savePath = ini_get('session.save_path');
    if (!$savePath) {
        $savePath = sys_get_temp_dir();
    }
    if (!$savePath) { // final fallback
        $savePath = '/tmp';
    }
    @session_save_path($savePath);

    // Configure cookie params (secure over HTTPS)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    @session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // Start the session
    @session_start();
}

?>
