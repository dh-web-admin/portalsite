<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

// Auth check
if (!isset($_SESSION['email'])) {
    die('Not logged in');
}

// Admin check
$adminEmail = $_SESSION['email'];
$stmt = $conn->prepare("SELECT role FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $adminEmail);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    die('User not found');
}
$row = $res->fetch_assoc();
if ($row['role'] !== 'admin') {
    die('Not admin');
}
$stmt->close();

// Get all users with their roles
$sql = "SELECT id, name, email, role FROM users ORDER BY id DESC";
$result = $conn->query($sql);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Roles</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #667eea; color: white; }
        tr:nth-child(even) { background: #f2f2f2; }
        .empty { background: #ffcccc !important; font-weight: bold; }
    </style>
</head>
<body>
    <h1>User Roles Debug</h1>
    <p>Current time: <?php echo date('Y-m-d H:i:s'); ?></p>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Role Length</th>
                <th>Role Raw</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result && $result->num_rows > 0) {
                while ($user = $result->fetch_assoc()) {
                    $roleEmpty = empty($user['role']) || trim($user['role']) === '';
                    $rowClass = $roleEmpty ? 'class="empty"' : '';
                    echo "<tr $rowClass>";
                    echo "<td>" . htmlspecialchars($user['id']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['name']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['role']) . "</td>";
                    echo "<td>" . strlen($user['role']) . "</td>";
                    echo "<td>" . bin2hex($user['role']) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6'>No users found</td></tr>";
            }
            ?>
        </tbody>
    </table>
    <br>
    <a href="../admin/user_list.php">Back to User List</a>
</body>
</html>
