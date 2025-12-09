<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $code = trim($_POST['code'] ?? '');

    if (empty($email) || empty($code)) {
        $message = 'Please enter both email and code.';
        $message_type = 'error';
    } else {
        // Retrieve code from password_resets table
        $stmt = $conn->prepare("SELECT code, expires_at FROM password_resets WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows === 0) {
            $message = 'No reset request found for this email. Please request a new one.';
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
                $del_stmt->bind_param('s', $email);
                $del_stmt->execute();
                $del_stmt->close();
            } else if ($code !== $stored_code) {
                $message = 'Invalid reset code. Please try again.';
                $message_type = 'error';
            } else {
                // Code is valid! Set session and redirect to reset password page
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_authenticated'] = true;
                header('Location: reset_password.php');
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Reset Code</title>
    <link rel="stylesheet" href="password_reset.css">
</head>
<body style="background-color: #f5f5f5;">
    <div class="password-reset-container">
        <h1>Verify Code</h1>
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
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
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
    </div>
</body>
</html>
