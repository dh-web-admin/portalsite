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
        // Always return generic message (prevent account enumeration)
        // But still check if email exists for backend logic
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check_stmt->bind_param('s', $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $user_exists = $check_result->num_rows > 0;
        $check_stmt->close();

        // Generate 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires_at = time() + (15 * 60); // 15 minutes

        if ($user_exists) {
            // Store code in password_resets table (REPLACE INTO to overwrite old codes)
            $insert_stmt = $conn->prepare("REPLACE INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)");
            $insert_stmt->bind_param('ssi', $email, $code, $expires_at);
            $insert_stmt->execute();
            $insert_stmt->close();

            // Send email via Mailjet
            sendResetCode($email, $code);
        }

        // Always show same message (whether email exists or not)
        $message = 'If an account exists with that email, you will receive a password reset code shortly.';
        $message_type = 'success';
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
