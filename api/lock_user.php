<?php
// Locks an account on an administrator's instruction. The person keeps their
// password; they simply cannot sign in, and their current session is dropped
// on its next request (see partials/access_gate.php).
//
// Unlike a failed-attempt lockout, this one cannot be cleared by the account
// owner running a password reset — only another admin can lift it.
define('IS_API', true);
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';
require_once __DIR__ . '/../partials/account_lock.php';

header('Content-Type: application/json; charset=utf-8');

require_admin_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid user_id']);
    exit();
}

account_lock_ensure_columns($conn);

$stmt = $conn->prepare('SELECT id, name, email, locked_at FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit();
}

// Locking yourself would log you straight out with no way back in.
if (strcasecmp((string)$user['email'], (string)($_SESSION['email'] ?? '')) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'You cannot lock your own account']);
    exit();
}

if (!empty($user['locked_at'])) {
    echo json_encode(['success' => true, 'already_locked' => true]);
    exit();
}

if (!account_lock_set_by_id($conn, $userId, ACCOUNT_LOCK_REASON_ADMIN)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to lock account']);
    exit();
}

error_log(sprintf(
    'account locked by admin: user_id=%d email=%s by=%s',
    $userId,
    (string)$user['email'],
    (string)($_SESSION['email'] ?? 'unknown')
));

echo json_encode(['success' => true]);
