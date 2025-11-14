<?php
/**
 * Database Migration: Add 'data_entry' role
 *
 * Run this script ONCE after deploying to production to add the 'data_entry' role to the database.
 * After running successfully, you should delete this file for security.
 */

// Only allow admin users to run this
session_start();
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['email'])) {
    die('Unauthorized: Please login as admin first.');
}

$stmt = $conn->prepare("SELECT role FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $_SESSION['email']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || $user['role'] !== 'admin') {
    die('Unauthorized: Admin access required.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Migration - Add Data Entry Role</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 6px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 6px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 6px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #5568d3; }
    </style>
</head>
<body>
    <h1>🔧 Database Migration: Add Data Entry Role</h1>

    <?php
    // Check current ENUM definition
    $checkSql = "SHOW COLUMNS FROM users LIKE 'role'";
    $result = $conn->query($checkSql);
    $column = $result->fetch_assoc();

    $alreadyHas = (strpos($column['Type'], 'data_entry') !== false);

    if ($alreadyHas) {
        echo '<div class="info"><strong>✓ Already Applied:</strong> The data_entry role already exists in the database. No action needed.</div>';
        echo '<pre>' . htmlspecialchars($column['Type']) . '</pre>';
    } else {
        echo '<div class="info"><strong>Status:</strong> data_entry role not found. Applying migration...</div>';

        // Build new ENUM list by appending data_entry at the end
        // Note: keep existing roles and add data_entry
        $migrationSql = "ALTER TABLE users MODIFY COLUMN role ENUM('admin','projectmanager','estimator','accounting','superintendent','foreman','mechanic','operator','laborer','developer','data_entry') NOT NULL";

        if ($conn->query($migrationSql)) {
            echo '<div class="success"><strong>✓ Success:</strong> data_entry role has been added to the database!</div>';

            // Verify
            $result = $conn->query($checkSql);
            $column = $result->fetch_assoc();
            echo '<h3>Updated Role ENUM:</h3>';
            echo '<pre>' . htmlspecialchars($column['Type']) . '</pre>';

            echo '<div class="info"><strong>⚠️ Security Note:</strong> This migration has been applied successfully. You should now delete this file from your production server for security.</div>';
        } else {
            echo '<div class="error"><strong>✗ Error:</strong> Failed to apply migration: ' . htmlspecialchars($conn->error) . '</div>';
        }
    }

    $conn->close();
    ?>

    <a href="../admin/user_list.php" class="btn">← Back to User List</a>

    <h3>What This Migration Does:</h3>
    <ul>
        <li>Adds 'data_entry' as a valid role option in the users table</li>
        <li>Allows you to assign users the data_entry role from the admin panel</li>
    </ul>

    <h3>Post-Migration Steps:</h3>
    <ol>
        <li>✓ Verify the migration was successful (check above)</li>
        <li>Run the User List and assign the <strong>Data Entry</strong> role to a user</li>
        <li><strong>Delete this migration file</strong> from the server: <code>/migrations/add_data_entry_role.php</code></li>
    </ol>
</body>
</html>
