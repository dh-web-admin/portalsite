<?php
http_response_code(200);
header('Content-Type: text/plain');
echo "ok\n";
echo 'php: ' . PHP_VERSION . "\n";
echo 'sapi: ' . php_sapi_name() . "\n";
echo 'mysqli: ' . (function_exists('mysqli_connect') ? 'yes' : 'no') . "\n";

// Test basic file access
$paths = [
  __DIR__ . '/auth/login.php',
  __DIR__ . '/config/config.php',
  __DIR__ . '/admin/create_admin.php'
];
foreach ($paths as $p) {
  echo basename($p) . ': ' . (file_exists($p) ? 'found' : 'missing') . "\n";
}

// Try DB connect using our config; on failure config.php will print a generic message and exit
echo "db_connect: ";
ob_start();
$ok = true;
try {
    require __DIR__ . '/config/config.php';
} catch (Throwable $e) {
    $ok = false;
}
$buff = ob_get_clean();
if (isset($conn) && $conn instanceof mysqli && empty($conn->connect_error)) {
    echo "ok\n";
    // Check users table exists
    $res = $conn->query("SHOW TABLES LIKE 'users'");
    echo 'users_table: ' . (($res && $res->num_rows > 0) ? 'present' : 'missing') . "\n";
} else {
    echo "fail\n";
    if (!empty($buff)) {
        echo "note: config output suppressed in prod\n";
    }
}
?>