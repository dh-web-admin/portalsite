<?php
// Diagnostic-friendly output
header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', '1');

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
$needsRepair = false;
$autoSql = "SELECT EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'id'";
$res = $conn->query($autoSql);
if ($res && $row = $res->fetch_assoc()) {
    $extra = strtolower($row['EXTRA'] ?? '');
    if (strpos($extra, 'auto_increment') === false) {
        $needsRepair = true;
    }
} else {
    // If we can't read schema info, attempt repair anyway
    $needsRepair = true;
}
if ($needsRepair) {
    echo "Applying schema repair for users.id...\n";
    // Try to ensure id is not null
    if (!$conn->query("ALTER TABLE users MODIFY COLUMN id INT NOT NULL")) {
        echo "Note: MODIFY id NOT NULL may have failed: " . $conn->error . "\n";
    }
    // Try to add AUTO_INCREMENT
    if (!$conn->query("ALTER TABLE users MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT")) {
        echo "Note: ADD AUTO_INCREMENT may have failed: " . $conn->error . "\n";
    }
    // Try to add PRIMARY KEY on id
    if (!$conn->query("ALTER TABLE users ADD PRIMARY KEY (id)")) {
        echo "Note: ADD PRIMARY KEY may have failed (already exists?): " . $conn->error . "\n";
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
