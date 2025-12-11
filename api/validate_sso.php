<?php
/**
 * API endpoint for Shop to validate an SSO token.
 *
 * Expects a POST request with parameter `token` and a header `X-SSO-SECRET`
 * that matches the portal's `SHOP_SSO_SECRET` environment variable.
 *
 * Response: JSON { success: true, user: { id, email, name } } or
 * { success: false, message: '...' }
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$expected_secret = getenv('SHOP_SSO_SECRET') ?: '';
$provided_secret = $_SERVER['HTTP_X_SSO_SECRET'] ?? ($_POST['x_sso_secret'] ?? '');

if (empty($expected_secret) || !hash_equals($expected_secret, $provided_secret)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid SSO secret']);
    exit();
}

$raw_token = $_POST['token'] ?? null;
if (empty($raw_token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing token']);
    exit();
}

$token_secret = getenv('SSO_TOKEN_SECRET') ?: '';
if (empty($token_secret)) {
    error_log('Warning: SSO_TOKEN_SECRET not configured; validating tokens without secret.');
}

$token_hash = hash_hmac('sha256', $raw_token, $token_secret);

// Find a matching unused token that hasn't expired
$now = time();
$stmt = $conn->prepare('SELECT id, user_id FROM sso_tokens WHERE token_hash = ? AND used = 0 AND expires_at >= ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit();
}

$stmt->bind_param('si', $token_hash, $now);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Token not found or expired']);
    exit();
}

$token_id = (int) $row['id'];
$user_id = (int) $row['user_id'];

// Mark token used
$u = $conn->prepare('UPDATE sso_tokens SET used = 1 WHERE id = ?');
if ($u) {
    $u->bind_param('i', $token_id);
    $u->execute();
    $u->close();
}

// Fetch user details
$q = $conn->prepare('SELECT id, email, name FROM users WHERE id = ? LIMIT 1');
if (!$q) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit();
}

$q->bind_param('i', $user_id);
$q->execute();
$r = $q->get_result();
$user = $r->fetch_assoc();
$q->close();

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

// Success - return the user info
echo json_encode(['success' => true, 'user' => $user]);
exit();

?>
