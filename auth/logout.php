<?php
session_start();

// Unset all session variables
$_SESSION = [];

// If session uses cookies, invalidate the cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], isset($params['httponly']) ? $params['httponly'] : false
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit();
