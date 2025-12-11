<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

// Short-lived SSO token lifetime in seconds
$DEFAULT_TTL = 120; // 2 minutes

$redirect = $_GET['redirect'] ?? '/shop';

// If there's a configured SHOP_SSO_URL, use that as the destination base
$shop_sso_url = getenv('SHOP_SSO_URL') ?: null;

// If user is not logged in, just redirect to the shop target (no SSO)
if (empty($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header('Location: ' . $redirect);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Ensure we have a token secret for hashing
$token_secret = getenv('SSO_TOKEN_SECRET') ?: '';
if (empty($token_secret)) {
    error_log('Warning: SSO_TOKEN_SECRET not configured; tokens will be hashed without server secret.');
}

try {
    $token = bin2hex(random_bytes(32));
} catch (Exception $e) {
    // Fallback - should not happen on modern PHP
    $token = bin2hex(openssl_random_pseudo_bytes(32));
}

$token_hash = hash_hmac('sha256', $token, $token_secret);
$expires = time() + $DEFAULT_TTL;

// Store token hash
$stmt = $conn->prepare('INSERT INTO sso_tokens (user_id, token_hash, expires_at, used) VALUES (?, ?, ?, 0)');
if ($stmt) {
    $stmt->bind_param('isi', $user_id, $token_hash, $expires);
    $ok = $stmt->execute();
    if (!$ok) {
        error_log('Failed to insert sso_tokens: ' . $stmt->error);
    }
    $stmt->close();
} else {
    error_log('Failed to prepare sso_tokens insert: ' . $conn->error);
}

// Build final redirect URL
if ($shop_sso_url) {
    // Append token and original redirect
    $sep = (strpos($shop_sso_url, '?') === false) ? '?' : '&';
    $final = $shop_sso_url . $sep . 'sso_token=' . urlencode($token) . '&redirect=' . urlencode($redirect);
} else {
    // No SSO target configured; send user to redirect directly
    $final = $redirect;
}

header('Location: ' . $final);
exit();

?>
