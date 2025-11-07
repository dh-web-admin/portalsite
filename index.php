<?php
// Front controller safety: allow health endpoint to be served even if server rewrites to index
$uri = $_SERVER['REQUEST_URI'] ?? '/';
if (preg_match('~/(health(?:\.php)?)$~i', $uri)) {
	// Serve the health check directly
	require __DIR__ . '/health.php';
	exit;
}

// Bootstrap session and route based on auth state
require_once __DIR__ . '/session_init.php';

// If already logged in, go straight to dashboard; otherwise show login
if (isset($_SESSION['email']) && isset($_SESSION['name'])) {
    header('Location: pages/dashboard/');
    exit;
}

header('Location: auth/login.php');
exit;
?>
