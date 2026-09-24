<?php
// Lifts a failed-login lockout on behalf of a user. Deliberately does NOT
// touch their password — they keep signing in with the one they already have.
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

if (empty($user['locked_at'])) {
    echo json_encode(['success' => true, 'already_unlocked' => true]);
    exit();
}

if (!account_lock_clear_by_id($conn, $userId)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to unlock account']);
    exit();
}

error_log(sprintf(
    'account unlocked: user_id=%d email=%s by=%s',
    $userId,
    (string)$user['email'],
    (string)($_SESSION['email'] ?? 'unknown')
));

echo json_encode(['success' => true]);
