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

// Try DB connect using env vars directly (avoid config.php die())
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';
$name = getenv('DB_NAME') ?: 'railway';
$port = (int)(getenv('DB_PORT') ?: 3306);

echo 'db_host: ' . $host . "\n";
echo 'db_port: ' . $port . "\n";
echo 'db_name: ' . $name . "\n";

$mysqli = @new mysqli($host, $user, $pass, $name, $port);
if ($mysqli && !$mysqli->connect_error) {
    echo "db_connect: ok\n";
    $res = $mysqli->query("SHOW TABLES LIKE 'users'");
    echo 'users_table: ' . (($res && $res->num_rows > 0) ? 'present' : 'missing') . "\n";
} else {
    echo "db_connect: fail\n";
    if ($mysqli && $mysqli->connect_error) {
        echo 'db_error: ' . $mysqli->connect_error . "\n";
    }
}
?>