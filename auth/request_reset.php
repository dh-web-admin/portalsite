<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/mailjet_helper.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $message = 'Please enter your email address.';
        $message_type = 'error';
    } else {
        // Explicit diagnostic flow: tell exactly what happened.
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check_stmt->bind_param('s', $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $user_exists = $check_result->num_rows > 0;
        $check_stmt->close();

        if (!$user_exists) {
            $message = 'No account found for that email.';
            $message_type = 'error';
        } else {
            // Generate 6-digit code
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires_at = time() + (15 * 60); // 15 minutes

            $send_error = null;
            $db_error = null;

            // Store code in password_resets table (REPLACE INTO to overwrite old codes)
            $insert_stmt = $conn->prepare("REPLACE INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)");
            if ($insert_stmt) {
                $insert_stmt->bind_param('ssi', $email, $code, $expires_at);
                if (!$insert_stmt->execute()) {
                    $db_error = $insert_stmt->error;
                }
                $insert_stmt->close();
            } else {
                $db_error = $conn->error;
            }

            if (!$db_error) {
                $result = sendResetCode($email, $code);
                if (is_array($result) && isset($result['success']) && !$result['success']) {
                    $send_error = $result['error'] ?? 'Unknown Mailjet error';
                }
            }

            if ($db_error) {
                $message = 'Could not save reset code: ' . $db_error;
                $message_type = 'error';
            } elseif ($send_error) {
                $message = 'Password reset email failed: ' . $send_error;
                $message_type = 'error';
            } else {
                $message = 'Reset code sent to ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . ' successfully.';
                $message_type = 'success';
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
    <title>Request Password Reset</title>
    <link rel="stylesheet" href="password_reset.css">
</head>
<body style="background-color: #f5f5f5;">
    <div class="password-reset-container">
        <h1>Reset Password</h1>
        <p class="subtitle">Enter your email address and we'll send you a reset code.</p>

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

            <div class="form-actions">
                <button type="submit" class="btn-primary">Send Reset Code</button>
            </div>
        </form>

        <div class="form-link">
            Remember your password? <a href="login.php">Back to Login</a>
        </div>
    </div>
</body>
</html>
