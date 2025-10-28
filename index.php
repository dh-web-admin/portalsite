<?php
// Front controller safety: allow health endpoint to be served even if server rewrites to index
$uri = $_SERVER['REQUEST_URI'] ?? '/';
if (preg_match('~/(health(?:\.php)?)$~i', $uri)) {
	// Serve the health check directly
	require __DIR__ . '/health.php';
	exit;
}

// Default: redirect to login page using a relative path
// Works on both local (http://localhost/PortalSite/) and production (domain root)
header('Location: auth/login.php');
exit;
?>
