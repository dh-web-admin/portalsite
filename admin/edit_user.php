<?php
require_once __DIR__ . '/../session_init.php';
require_once '../config/config.php';

// Auth + admin check
if (!isset($_SESSION['email'])) {
    header('Location: index.php');
    exit();
}
$adminEmail = $_SESSION['email'];
$stmt = $conn->prepare("SELECT role FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $adminEmail);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) { header('Location: index.php'); exit(); }
$row = $res->fetch_assoc();
$actualRole = $row['role'];
// Allow admin or developer previewing as admin
if ($actualRole === 'developer' && isset($_GET['preview_role']) && $_GET['preview_role'] === 'admin') {
    // Developer previewing as admin - allow access
} elseif ($actualRole !== 'admin') { 
    header('Location: index.php'); 
    exit(); 
}
$stmt->close();

// Get target user id from GET
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header('Location: user_list.php');
    exit();
}

// Fetch user
$stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) { header('Location: user_list.php'); exit(); }
$user = $res->fetch_assoc();
$stmt->close();

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';

    $allowed_roles = ['admin','projectmanager','estimator','accounting','superintendent','foreman','mechanic','operator','laborer','developer'];
    if (!in_array($role, $allowed_roles, true)) {
        $error = 'Invalid role';
    }

    if (empty($error)) {
        if ($password !== '') {
            // validate password
            if (strlen($password) < 8 || !preg_match('/[0-9]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/[!@#$%^&*()_+\-=[\]{};:\'"\\|,.<>\/\?]/', $password)) {
                $error = 'Password must be at least 8 chars, include number, uppercase and special char';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET name = ?, role = ?, password = ? WHERE id = ?");
                $update->bind_param('sssi', $name, $role, $hashed, $id);
                $ok = $update->execute();
                $update->close();
                if ($ok) $message = 'User updated successfully'; else $error = 'Error updating user: ' . $conn->error;
            }
        } else {
            $update = $conn->prepare("UPDATE users SET name = ?, role = ? WHERE id = ?");
            $update->bind_param('ssi', $name, $role, $id);
            $ok = $update->execute();
            $update->close();
            if ($ok) $message = 'User updated successfully'; else $error = 'Error updating user: ' . $conn->error;
        }
    }
    // Reload user data
    $stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();
}

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit User</title>
<link rel="stylesheet" href="../assets/css/base.css">
<link rel="stylesheet" href="../assets/css/admin-layout.css">
<link rel="stylesheet" href="../assets/css/edit-user.css">
</head>
<body class="admin-page">
<div class="admin-container">
    <?php include __DIR__ . '/../partials/portalheader.php'; ?>
    <div class="admin-layout">
        <?php include __DIR__ . '/../partials/sidebar.php'; ?>
        <main class="content-area">
            <div class="edit-container">
                <a href="user_list.php" class="back-btn">Back to User List</a>
                <h1>Edit User</h1>

                <?php if ($error): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($message): ?>
                    <div class="success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" required>
                            <?php foreach (['admin','projectmanager','estimator','accounting','superintendent','foreman','mechanic','operator','laborer','developer'] as $r):
                                $sel = ($user['role'] === $r) ? 'selected' : '';
                                echo "<option value=\"$r\" $sel>$r</option>";
                            endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>New Password (leave blank to keep current)</label>
                        <input type="password" name="password">
                    </div>
                    <button type="submit" class="add-user-btn">Save Changes</button>
                </form>
            </div>
        </main>
    </div>
</div>
<script>
(function(){
    var usersToggle = document.getElementById('usersToggle');
    var usersGroup = document.getElementById('usersGroup');
    if (usersToggle && usersGroup) usersToggle.addEventListener('click', function(){ usersGroup.classList.toggle('open'); });
})();
</script>
</body>
</html>
