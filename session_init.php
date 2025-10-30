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
    // Extended lifetime to 24 hours by default (prevents logout on refresh)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    @session_set_cookie_params([
        'lifetime' => 2592000, // 30 days to persist across browser restarts
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // Start the session
    @session_start();
    
    // Check for remember me cookie and auto-login
    if (!isset($_SESSION['email']) && isset($_COOKIE['remember_token'])) {
        require_once __DIR__ . '/config/config.php';
        $token = $_COOKIE['remember_token'];
        
        // Verify token from database
        $stmt = $conn->prepare("SELECT email, name FROM users WHERE remember_token = ? AND remember_token_expires > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $_SESSION['email'] = $user['email'];
            $_SESSION['name'] = $user['name'];
            
            // Regenerate token for security
            $newToken = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + (30 * 86400)); // 30 days
            
            $updateStmt = $conn->prepare("UPDATE users SET remember_token = ?, remember_token_expires = ? WHERE email = ?");
            $updateStmt->bind_param("sss", $newToken, $expires, $user['email']);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Update cookie with new token
            setcookie('remember_token', $newToken, [
                'expires' => time() + (30 * 86400),
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        $stmt->close();
    }
}

?>
