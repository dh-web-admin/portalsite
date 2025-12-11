<?php
// Redirect visitors to the Shop application on the same host.
// This will send users to https://<current-host>/shop (or http depending on scheme).
// Works for both local XAMPP (localhost/shop) and production (app.darkhorsespreader.com/shop).
// Use the request scheme if available, default to https.
// If running in production (Railway or your domain), redirect to the hosted shop app.
$isProduction = (strpos($_SERVER['HTTP_HOST'] ?? '', 'darkhorsespreader.com') !== false) || getenv('RAILWAY_ENVIRONMENT');
if ($isProduction) {
	// In production, prefer the hosted shop app but route via the Portal SSO entrypoint.
	$shopHost = 'https://shop-production-ce5b.up.railway.app';
	$target = $shopHost;
} else {
	$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
	$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$target = $scheme . '://' . $host . '/shop';
}

// Redirect to the Portal's SSO creator which will either generate a short-lived SSO token
// and forward to the shop (if configured), or simply forward directly when SSO isn't available.
$ssoEntry = '/auth/create_sso.php?redirect=' . urlencode($target);
header('Location: ' . $ssoEntry);
exit();
