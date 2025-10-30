<?php
header('Content-Type: text/plain');
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

// Admin-only guard
$email = $_SESSION['email'] ?? null;
if (!$email) { header('Location: ../auth/login.php'); exit(); }
$stmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$role = $user['role'] ?? 'laborer';
$stmt->close();
if ($role !== 'admin') { header('Location: ../pages/dashboard.php'); exit(); }

// Bump a counter to verify persistence
if (!isset($_SESSION['__test_counter'])) {
    $_SESSION['__test_counter'] = 1;
} else {
    $_SESSION['__test_counter']++;
}

echo "session_id: " . session_id() . "\n";
echo "counter: " . $_SESSION['__test_counter'] . "\n";
echo "session vars: " . json_encode($_SESSION) . "\n";

// Show cookie we set
echo "cookies: " . json_encode($_COOKIE) . "\n";

// Also show headers that might affect cookies
echo "cookie_params: " . json_encode(session_get_cookie_params()) . "\n";

// Show session backend info
echo "save_handler: " . (ini_get('session.save_handler') ?: '(default)') . "\n";
echo "save_path: " . (ini_get('session.save_path') ?: '(none)') . "\n";

?>
