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
?>