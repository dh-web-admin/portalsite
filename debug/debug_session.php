<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../partials/url.php';
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

echo "<h1>Session Debug</h1>";
echo "<pre>";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "ACTIVE" : "NOT ACTIVE") . "\n";
echo "Session ID: " . session_id() . "\n\n";

echo "Session Variables:\n";
print_r($_SESSION);

echo "\n\nCookie Data:\n";
print_r($_COOKIE);

echo "\n\nSession Config:\n";
echo "save_handler: " . ini_get('session.save_handler') . "\n";
echo "save_path: " . ini_get('session.save_path') . "\n";
echo "cookie_lifetime: " . ini_get('session.cookie_lifetime') . "\n";
echo "cookie_path: " . ini_get('session.cookie_path') . "\n";

echo "\n\nCheck if logged in:\n";
echo "email set: " . (isset($_SESSION['email']) ? 'YES - ' . $_SESSION['email'] : 'NO') . "\n";
echo "name set: " . (isset($_SESSION['name']) ? 'YES - ' . $_SESSION['name'] : 'NO') . "\n";
echo "</pre>";

echo '<br><a href="' . htmlspecialchars(base_url('/auth/login.php')) . '">Go to Login</a>';
echo ' | <a href="' . htmlspecialchars(base_url('/pages/dashboard.php')) . '">Go to Dashboard</a>';
echo ' | <a href="' . htmlspecialchars(base_url('/pages/equipment.php')) . '">Go to Equipment</a>';
?>
