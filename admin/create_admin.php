<?php
require_once '../config/config.php';

// Optional: return plain text for easier viewing in browser/logs
header('Content-Type: text/plain');

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

if (!$conn->query($createSql)) {
        http_response_code(500);
        echo "Failed to ensure users table exists: " . $conn->error;
        exit;
}

// First, delete existing admin user if exists
$conn->query("DELETE FROM users WHERE email='admin'");

// Create password hash
$password = 'admin';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert new admin user
$name = 'admin';
$email = 'admin';
$role = 'admin';

$sql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $name, $email, $hashed_password, $role);

if ($stmt->execute()) {
    echo "Admin user created successfully!\n";
    echo "Email: admin\n";
    echo "Password: admin\n";
    echo "Generated hash: " . $hashed_password . "\n";
} else {
    echo "Error creating admin user: " . $conn->error . "\n";
}

$stmt->close();
$conn->close();
?>