<?php
header('Content-Type: text/plain');
session_start();

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
