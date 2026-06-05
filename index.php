<?php
// Front controller safety: allow health endpoint to be served even if server rewrites to index
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// ✅ Serve uploads directly from mounted volume when requested.
// This bypasses session/auth so static files return 200 instead of redirecting to /auth/login.php.
if (preg_match('#^/(?:PortalSite/)?uploads/(.+)$#i', $uri, $m)) {
    $rel = rawurldecode($m[1]);
    $rel = str_replace('\\', '/', $rel);

    // Prevent traversal
    if (strpos($rel, '..') !== false) {
        http_response_code(400);
        echo 'Bad request';
        exit;
    }

    $fsPrimary = '/portalsite/uploads/' . $rel;
    $fsLegacy = __DIR__ . '/uploads/' . $rel;
    $fs = (is_file($fsPrimary) && is_readable($fsPrimary)) ? $fsPrimary : $fsLegacy;

    if (!is_file($fs) || !is_readable($fs)) {
        // Debug log in a writable location
        @file_put_contents('/tmp/upload_debug.log', date('c') . " MISS: $uri -> primary:$fsPrimary legacy:$fsLegacy\n", FILE_APPEND | LOCK_EX);
        http_response_code(404);
        echo 'Not found';
        exit;
    }

    $ext = strtolower(pathinfo($fs, PATHINFO_EXTENSION));
    $types = [
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
        'pdf'  => 'application/pdf',
    ];

    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($fs));
    header('Cache-Control: public, max-age=31536000, immutable');
    readfile($fs);
    exit;
}

if (preg_match('~/(health(?:\.php)?)$~i', $uri)) {
    require __DIR__ . '/health.php';
    exit;
}

// ✅ Continue normal app flow
require_once __DIR__ . '/session_init.php';

// IMPORTANT: Don’t redirect everything unless this file is *only* for root.
// If this is your true front controller, you should dispatch to your router instead.
if (isset($_SESSION['email']) && isset($_SESSION['name'])) {
    $isProduction = (strpos($_SERVER['HTTP_HOST'] ?? '', 'darkhorsespreader.com') !== false || getenv('RAILWAY_ENVIRONMENT'));
    $dashboardPath = $isProduction ? '/pages/dashboard/' : '/PortalSite/pages/dashboard/';
    header('Location: ' . $dashboardPath);
    exit;
} else {
    $isProduction = (strpos($_SERVER['HTTP_HOST'] ?? '', 'darkhorsespreader.com') !== false || getenv('RAILWAY_ENVIRONMENT'));
    $loginPath = $isProduction ? '/auth/login.php' : '/PortalSite/auth/login.php';
    header('Location: ' . $loginPath);
    exit;
}
