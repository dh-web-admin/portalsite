<?php
require_once __DIR__ . '/../../session_init.php';

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    header('Location: ../../auth/login.php');
    exit();
}

// Include database configuration
require_once '../../config/config.php';

// Get user information
$email = $_SESSION['email'];
$stmt = $conn->prepare('SELECT role FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$actualRole = $user ? $user['role'] : 'laborer';

// Check if developer is previewing as another role
if ($actualRole === 'developer' && isset($_GET['preview_role'])) {
    $role = $_GET['preview_role'];
} else {
    $role = $actualRole;
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#667eea">
    <title>Account Settings</title>
    <link rel="stylesheet" href="../../assets/css/base.css">
    <link rel="stylesheet" href="../../assets/css/admin-layout.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="admin-page">
    <div class="admin-container">
    <?php include __DIR__ . '/../../partials/portalheader.php'; ?>
        <div class="admin-layout">
            <?php include __DIR__ . '/../../partials/sidebar.php'; ?>
            <main class="content-area">
                <div class="main-content">
                    <h1>Account Settings</h1>
                    
                    <?php if (isset($_SESSION['password_success'])): ?>
                        <div class="success-message"><?php echo htmlspecialchars($_SESSION['password_success']); unset($_SESSION['password_success']); ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['password_error'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($_SESSION['password_error']); unset($_SESSION['password_error']); ?></div>
                    <?php endif; ?>

                    <div class="settings-section">
                        <h2>Change Password</h2>
                        <form action="../../api/change_own_password.php" method="POST" class="password-form">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="current_password" name="current_password" required>
                                    <button type="button" class="password-toggle" onclick="togglePassword('current_password', this)">Show</button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="new_password" name="new_password" required minlength="8">
                                    <button type="button" class="password-toggle" onclick="togglePassword('new_password', this)">Show</button>
                                </div>
                                <div class="password-requirements">
                                    <ul>
                                        <li>At least 8 characters</li>
                                        <li>At least one uppercase letter</li>
                                        <li>At least one number</li>
                                        <li>At least one special character (!@#$%^&*)</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="confirm_password" name="confirm_password" required>
                                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', this)">Show</button>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </form>
                    </div>

                    <!-- Placeholder for future settings sections -->
                    <div class="settings-section placeholder-section">
                        <h2>Additional Settings</h2>
                        <p>More options coming soon...</p>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="../../assets/js/mobile-menu.js"></script>
    <script>
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                button.textContent = 'Hide';
            } else {
                input.type = 'password';
                button.textContent = 'Show';
            }
        }
    </script>
</body>
</html>
