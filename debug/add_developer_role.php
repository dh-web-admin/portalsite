<?php
require_once __DIR__ . '/../config/config.php';

echo "<h1>Add 'developer' to Role ENUM</h1>";

// Add 'developer' to the ENUM
$sql = "ALTER TABLE users MODIFY COLUMN role ENUM('admin','projectmanager','estimator','accounting','superintendent','foreman','mechanic','operator','laborer','developer') NOT NULL";

if ($conn->query($sql)) {
    echo "<p style='color:green; font-weight:bold;'>✓ Successfully added 'developer' to role ENUM!</p>";
    
    // Now set user 16 to developer
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $role = 'developer';
    $id = 16;
    $stmt->bind_param('si', $role, $id);
    
    if ($stmt->execute()) {
        echo "<p style='color:green;'>✓ Updated user 16 to developer role. Affected rows: " . $stmt->affected_rows . "</p>";
    } else {
        echo "<p style='color:red;'>✗ Failed to update user: " . $conn->error . "</p>";
    }
    
    // Verify
    echo "<h2>Verification:</h2>";
    $stmt = $conn->prepare("SELECT id, name, email, role, LENGTH(role) as role_len FROM users WHERE id = 16");
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    echo "<pre>";
    print_r($user);
    echo "</pre>";
    
    // Show updated ENUM
    echo "<h2>Updated Role ENUM:</h2>";
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
    $column = $result->fetch_assoc();
    echo "<pre>";
    print_r($column);
    echo "</pre>";
    
} else {
    echo "<p style='color:red; font-weight:bold;'>✗ Failed to alter table: " . $conn->error . "</p>";
}

$conn->close();
?>
<br>
<a href="check_roles.php">Check All Roles</a> | 
<a href="../admin/user_list.php">Go to User List</a>
