<?php
// Diagnostic-friendly output
header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', '1');
// Avoid mysqli throwing exceptions that kill the script; we'll handle errors manually
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

echo "create_admin start\n";

// Ensure mysqli is available early to avoid fatal white-screen
if (!function_exists('mysqli_connect') || !class_exists('mysqli')) {
    http_response_code(500);
    echo "FATAL: mysqli extension not loaded.\n";
    echo "Hint: set PHP_EXTENSIONS='mysqli pdo_mysql' on the web service and redeploy.\n";
    exit;
}

// Include config and validate connection
require_once __DIR__ . '/../config/config.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo "FATAL: DB connection (\$conn) is not initialized.\n";
    exit;
}

echo "DB connected. Host: " . ($conn->host_info ?? 'n/a') . "\n";

// Ensure the users table exists (idempotent)
$createSql = <<<SQL
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)
SQL;

echo "Ensuring users table...\n";
if (!$conn->query($createSql)) {
    http_response_code(500);
    echo "Failed to ensure users table exists: " . $conn->error . "\n";
    exit;
}

// Repair schema if table exists but id is not AUTO_INCREMENT/PRIMARY KEY
echo "Verifying users.id is AUTO_INCREMENT PRIMARY KEY...\n";

// Check if id has AUTO_INCREMENT
$autoSql = "SELECT LOWER(EXTRA) AS extra FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'id'";
$autoRes = $conn->query($autoSql);
$hasAuto = false;
if ($autoRes && ($row = $autoRes->fetch_assoc())) {
    $hasAuto = (strpos($row['extra'] ?? '', 'auto_increment') !== false);
}

// Check if a PRIMARY KEY exists and which columns it covers
$pkSql = "SELECT GROUP_CONCAT(k.COLUMN_NAME ORDER BY k.ORDINAL_POSITION) AS pk_cols\n          FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS t\n          JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE k\n            ON t.CONSTRAINT_NAME = k.CONSTRAINT_NAME\n           AND t.TABLE_SCHEMA = k.TABLE_SCHEMA\n           AND t.TABLE_NAME = k.TABLE_NAME\n         WHERE t.TABLE_SCHEMA = DATABASE()\n           AND t.TABLE_NAME = 'users'\n           AND t.CONSTRAINT_TYPE = 'PRIMARY KEY'";
$pkRes = $conn->query($pkSql);
$pkCols = '';
if ($pkRes && ($row = $pkRes->fetch_assoc())) {
    $pkCols = (string)($row['pk_cols'] ?? '');
}

// If PK exists but not on id, drop it first (fresh schema safety)
if ($pkCols !== '' && strtolower(trim($pkCols)) !== 'id') {
    echo "Primary key exists on ($pkCols); switching to id...\n";
    if (!$conn->query("ALTER TABLE users DROP PRIMARY KEY")) {
        echo "Note: DROP PRIMARY KEY failed: " . $conn->error . "\n";
    }
    $pkCols = '';
}

// Ensure id is NOT NULL
if (!$conn->query("ALTER TABLE users MODIFY COLUMN id INT NOT NULL")) {
    echo "Note: MODIFY id NOT NULL may have failed: " . $conn->error . "\n";
}

// Ensure PK on id if missing
if ($pkCols === '') {
    if (!$conn->query("ALTER TABLE users ADD PRIMARY KEY (id)")) {
        echo "Note: ADD PRIMARY KEY (id) failed or already exists: " . $conn->error . "\n";
    }
}

// Ensure AUTO_INCREMENT on id
if (!$hasAuto) {
    if (!$conn->query("ALTER TABLE users MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT")) {
        echo "Note: ADD AUTO_INCREMENT failed: " . $conn->error . "\n";
    }
}

echo "Clearing existing admin (if any)...\n";
if (!$conn->query("DELETE FROM users WHERE email='admin'")) {
    http_response_code(500);
    echo "Failed to delete existing admin: " . $conn->error . "\n";
    exit;
}

// Create password hash
$password = 'admin';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
if ($hashed_password === false) {
    http_response_code(500);
    echo "Failed to generate password hash.\n";
    exit;
}

// Insert new admin user
$name = 'admin';
$email = 'admin';
$role = 'admin';

echo "Inserting admin...\n";
$sql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo "Failed to prepare statement: " . $conn->error . "\n";
    exit;
}
$stmt->bind_param("ssss", $name, $email, $hashed_password, $role);

if ($stmt->execute()) {
    echo "Admin user created successfully!\n";
    echo "Email: admin\n";
    echo "Password: admin\n";
    echo "Generated hash: " . $hashed_password . "\n";
} else {
    http_response_code(500);
    echo "Error creating admin user: " . $stmt->error . "\n";
}

$stmt->close();
$conn->close();

echo "Done.\n";
?>
