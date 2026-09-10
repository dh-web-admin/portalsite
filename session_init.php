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
        'lifetime' => 21600,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // Ensure server-side session GC matches cookie lifetime (6 hours)
    @ini_set('session.gc_maxlifetime', '21600');
    @session_start();

    // expire session after 30 minutes of inactivity
    try {
        $inactiveLimit = 1800; // seconds (30 minutes)
        if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity'] > $inactiveLimit)) {
            // clear session and remove session cookie
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params['path'], $params['domain'], $params['secure'], $params['httponly']
                );
            }
            @session_unset();
            @session_destroy();
            // start a fresh session for this request
            @session_start();
        }
    } catch (Throwable $e) {}

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

    // update last activity timestamp for inactivity checks
    try { $_SESSION['last_activity'] = time(); } catch (Throwable $e) {}
}

// ---- Remember where a signed-out visitor was actually headed ----
// Every page requires this file before running its own auth check, so
// capturing here covers the whole site at once — no page has to opt in, and
// each page's existing redirect to the login screen keeps working untouched.
// Consumed once, after sign-in, by take_intended_url() in partials/url.php.
if (session_status() === PHP_SESSION_ACTIVE && !isset($_SESSION['email'])) {
    $hcIsGet = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';

    // Only real top-level navigations. Sec-Fetch-Dest is absent on older
    // browsers, so treat "missing" as a navigation and let the path/extension
    // rules below do the filtering there.
    $hcDest  = $_SERVER['HTTP_SEC_FETCH_DEST'] ?? '';
    $hcIsDoc = ($hcDest === '' || $hcDest === 'document')
        && (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest');

    // Never capture the auth screens themselves (would trap the user in a
    // loop), the JSON API, or static assets.
    $hcSkip = $path === ''
        || preg_match('#^/(PortalSite/)?(auth|api|assets|uploads)/#i', $path)
        || preg_match('#\.(css|js|map|json|png|jpe?g|gif|svg|ico|webp|woff2?|ttf|eot)$#i', $path);

    if ($hcIsGet && $hcIsDoc && !$hcSkip) {
        $hcTarget = $path;
        $hcQuery  = $_SERVER['QUERY_STRING'] ?? '';
        if ($hcQuery !== '') {
            $hcTarget .= '?' . $hcQuery;
        }
        // Store only a same-site absolute path. Anything starting "//" would
        // be read by the browser as another origin, so it never gets saved —
        // that keeps this from becoming an open redirect.
        if ($hcTarget[0] === '/' && strncmp($hcTarget, '//', 2) !== 0) {
            $_SESSION['intended_url'] = $hcTarget;
        }
    }

    unset($hcIsGet, $hcDest, $hcIsDoc, $hcSkip, $hcTarget, $hcQuery);
}