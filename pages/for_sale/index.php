<?php
// Redirect visitors to the Shop application on the same host.
// This will send users to https://<current-host>/shop (or http depending on scheme).
// Works for both local XAMPP (localhost/shop) and production (app.darkhorsespreader.com/shop).
// Use the request scheme if available, default to https.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$target = $scheme . '://' . $host . '/shop';
header('Location: ' . $target);
exit();
