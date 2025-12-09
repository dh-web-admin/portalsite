<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

$message = '';
$message_type = '';
$reset_email = '';
$code_verified = isset($_SESSION['reset_authenticated']) && isset($_SESSION['reset_email']);

// Step 1: Code Verification (if not yet authenticated)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$code_verified) {
    $reset_email = trim($_POST['email'] ?? '');
    $code = trim($_POST['code'] ?? '');

    if (empty($reset_email) || empty($code)) {
        $message = 'Please enter both email and code.';
        $message_type = 'error';
    } else {
        // Retrieve code from password_resets table
        $stmt = $conn->prepare("SELECT code, expires_at FROM password_resets WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $reset_email);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows === 0) {
            $message = 'No reset request found for this email.';
            $message_type = 'error';
        } else {
            $reset_data = $result->fetch_assoc();
            $stored_code = $reset_data['code'];
            $expires_at = $reset_data['expires_at'];

            // Check expiry
            if (time() > $expires_at) {
                $message = 'This reset code has expired. Please request a new one.';
                $message_type = 'error';
                // Delete expired code
                $del_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                $del_stmt->bind_param('s', $reset_email);
                $del_stmt->execute();
                $del_stmt->close();
            } else if ($code !== $stored_code) {
                $message = 'Invalid reset code. Please try again.';
                $message_type = 'error';
            } else {
                // Code is valid! Set session
                $_SESSION['reset_email'] = $reset_email;
                $_SESSION['reset_authenticated'] = true;
                $code_verified = true;
                $message = 'Code verified! Now set your new password.';
                $message_type = 'success';
            }
        }
    }
}

// Step 2: Password Reset (if authenticated)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $code_verified) {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $reset_email = $_SESSION['reset_email'];

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
    <title>Reset Password</title>
    <link rel="stylesheet" href="password_reset.css">
</head>
<body style="background-color: #f5f5f5;">
    <div class="password-reset-container">
        <?php if (!$code_verified): ?>
            <!-- Step 1: Code Verification -->
            <h1>Verify Reset Code</h1>
            <p class="subtitle">Enter the 6-digit code we sent to your email.</p>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="password-reset-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        required 
                        autocomplete="email"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? $reset_email); ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="code">Reset Code (6 digits)</label>
                    <input 
                        type="text" 
                        id="code" 
                        name="code" 
                        required 
                        inputmode="numeric"
                        maxlength="6"
                        placeholder="000000"
                        value="<?php echo htmlspecialchars($_POST['code'] ?? ''); ?>"
                    >
                    <small style="color: #666; margin-top: 0.25rem;">Check your email for the code.</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Verify Code</button>
                </div>
            </form>

            <div class="form-link">
                <a href="request_reset.php">← Request a new code</a> | 
                <a href="login.php">Back to Login</a>
            </div>

        <?php else: ?>
            <!-- Step 2: Password Reset (after code verified) -->
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
        <?php endif; ?>
    </div>
</body>
</html>
