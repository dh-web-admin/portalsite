<?php
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
if ($role !== 'admin') { header('Location: ../pages/dashboard/'); exit(); }

http_response_code(200);
header('Content-Type: text/plain');
echo "ok\n";
echo 'php: ' . PHP_VERSION . "\n";
echo 'sapi: ' . php_sapi_name() . "\n";
echo 'mysqli: ' . (function_exists('mysqli_connect') ? 'yes' : 'no') . "\n";
echo 'php_extensions_env: ' . (getenv('PHP_EXTENSIONS') ?: '(not set)') . "\n";
echo 'php_ini: ' . (php_ini_loaded_file() ?: '(none)') . "\n";
// Show a compact list of loaded extensions
$ext = get_loaded_extensions(); sort($ext);
echo 'extensions_loaded: ' . implode(',', array_slice($ext, 0, min(20, count($ext)))) . (count($ext) > 20 ? ',...' : '') . "\n";

// Test basic file access (fix relative paths)
$paths = [
    __DIR__ . '/../auth/login.php',
    __DIR__ . '/../config/config.php',
    __DIR__ . '/../admin/create_admin.php'
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

// If mysqli extension isn't loaded, skip attempting connection to avoid fatal error
$hasMysqli = function_exists('mysqli_connect') && class_exists('mysqli');
if (!$hasMysqli) {
    echo "db_connect: skipped (mysqli extension not loaded)\n";
    echo "hint: set PHP_EXTENSIONS='mysqli pdo_mysql' on the web service and redeploy\n";
    exit; // prevent fatal
}

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
