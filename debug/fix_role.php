<?php
require_once __DIR__ . '/../config/config.php';

echo "<h1>Database Structure Check</h1>";

// Check table structure
$result = $conn->query("DESCRIBE users");
echo "<h2>Users Table Structure:</h2><pre>";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";

// Check current role value for user 16
echo "<h2>Current Role for User 16:</h2>";
$stmt = $conn->prepare("SELECT id, name, email, role, LENGTH(role) as role_len FROM users WHERE id = 16");
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
echo "<pre>";
print_r($user);
echo "</pre>";

// Try to manually update
echo "<h2>Manual Update Test:</h2>";
$stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
$testRole = 'developer';
$testId = 16;
$stmt->bind_param('si', $testRole, $testId);
if ($stmt->execute()) {
    echo "Update executed. Affected rows: " . $stmt->affected_rows . "<br>";
} else {
    echo "Update failed: " . $conn->error . "<br>";
}

// Check again after update
echo "<h2>After Manual Update:</h2>";
$stmt = $conn->prepare("SELECT id, name, email, role, LENGTH(role) as role_len FROM users WHERE id = 16");
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
echo "<pre>";
print_r($user);
echo "</pre>";

$conn->close();
?>
<br><a href="check_roles.php">Back to Role Debug</a>
