<?php
require_once '../config/config.php';

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
    echo "Admin user created successfully!<br>";
    echo "Email: admin<br>";
    echo "Password: admin<br>";
    echo "Generated hash: " . $hashed_password;
} else {
    echo "Error creating admin user: " . $conn->error;
}

$stmt->close();
$conn->close();
?>