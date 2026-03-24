<?php
// session_init.php

// ✅ Bypass auth/session for static uploads so images never redirect to login
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
if (preg_match('#^/(PortalSite/)?uploads/#i', $path)) {
    return;
}

// ---- Normal session boot ----
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.save_handler', 'files');

    $savePath = ini_get('session.save_path');
    if (!$savePath) $savePath = sys_get_temp_dir();
    if (!$savePath) $savePath = '/tmp';
    @session_save_path($savePath);

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    @session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    @session_start();

    // remember-me auto login
    if (!isset($_SESSION['email']) && isset($_COOKIE['remember_token'])) {
        require_once __DIR__ . '/config/config.php';
        $token = $_COOKIE['remember_token'];

        $stmt = $conn->prepare("SELECT id, email, name, role FROM users WHERE remember_token = ? AND remember_token_expires > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $_SESSION['email'] = $user['email'];
            $_SESSION['name']  = $user['name'];
            $_SESSION['role']  = $user['role'] ?? null;
            if (isset($user['id'])) $_SESSION['user_id'] = (int)$user['id'];

            $newToken = bin2hex(random_bytes(32));
            $expires  = date('Y-m-d H:i:s', time() + 86400);

            $updateStmt = $conn->prepare("UPDATE users SET remember_token = ?, remember_token_expires = ? WHERE email = ?");
            $updateStmt->bind_param("sss", $newToken, $expires, $user['email']);
            $updateStmt->execute();
            $updateStmt->close();

            setcookie('remember_token', $newToken, [
                'expires'  => time() + 86400,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

        $stmt->close();
    }
}