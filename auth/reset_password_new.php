<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

// Block direct access unless authenticated via OTP
if (!isset($_SESSION['reset_authenticated']) || !isset($_SESSION['reset_email'])) {
    header('Location: reset_password.php');
    exit();
}

$message = '';
$message_type = '';
$reset_email = $_SESSION['reset_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($password) || empty($password_confirm)) {
        $message = 'Please fill in all fields.';
        $message_type = 'error';
    } else if ($password !== $password_confirm) {
        $message = 'Passwords do not match.';
        $message_type = 'error';
    } else if (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters long.';
        $message_type = 'error';
    } else if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $message = 'Password must contain uppercase, lowercase, and numbers.';
        $message_type = 'error';
    } else {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Update users table
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $update_stmt->bind_param('ss', $hashed_password, $reset_email);
        $update_stmt->execute();
        $update_stmt->close();

        // Delete from password_resets
        $del_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $del_stmt->bind_param('s', $reset_email);
        $del_stmt->execute();
        $del_stmt->close();

        // Clear session flags
        unset($_SESSION['reset_authenticated']);
        unset($_SESSION['reset_email']);

        // Redirect to login with success message
        $_SESSION['reset_success'] = 'Password reset successfully. Please log in with your new password.';
        header('Location: login.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password</title>
    <link rel="stylesheet" href="password_reset.css">
</head>
<body style="background-color: #f5f5f5;">
    <div class="password-reset-container">
        <h1>Set New Password</h1>
        <p class="subtitle">Create a strong password for your account.</p>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="password-reset-form">
            <div class="form-group">
                <label for="password">New Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    autocomplete="new-password"
                    minlength="8"
                >
                <small style="color: #666; margin-top: 0.25rem; line-height: 1.4;">
                    At least 8 characters, with uppercase, lowercase, and numbers.
                </small>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm Password</label>
                <input 
                    type="password" 
                    id="password_confirm" 
                    name="password_confirm" 
                    required 
                    autocomplete="new-password"
                    minlength="8"
                >
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">Reset Password</button>
            </div>
        </form>

        <div class="form-link">
            <a href="login.php">Back to Login</a>
        </div>
    </div>
</body>
</html>
