<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../partials/url.php';

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
echo ' | <a href="' . htmlspecialchars(base_url('/pages/equipments.php')) . '">Go to Equipments</a>';
?>
