<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

$message = '';
$message_type = '';
$reset_email = '';
$code_sent = false;
$code_verified = false;
$action = $_POST['action'] ?? '';

// Retrieve from session if already sent
if (isset($_SESSION['reset_email_sent'])) {
    $reset_email = $_SESSION['reset_email_sent'];
    $code_sent = true;
}

// Action: Send/Resend code
if ($action === 'send_code') {
    $reset_email = trim($_POST['email'] ?? '');
    
    if (empty($reset_email)) {
        $message = 'Please enter your email address.';
        $message_type = 'error';
    } else {
        // Check if email exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check_stmt->bind_param('s', $reset_email);
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

            // Store code in password_resets table
            $insert_stmt = $conn->prepare("REPLACE INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)");
            $insert_stmt->bind_param('ssi', $reset_email, $code, $expires_at);
            $insert_stmt->execute();
            $insert_stmt->close();

            // Send email via Mailjet
            require_once __DIR__ . '/mailjet_helper.php';
            $result = sendResetCode($reset_email, $code);
            
            if (is_array($result) && isset($result['success']) && !$result['success']) {
                $message = 'Email send failed: ' . $result['error'];
                $message_type = 'error';
            } else {
                $_SESSION['reset_email_sent'] = $reset_email;
                $code_sent = true;
                $message = 'Reset code sent to ' . htmlspecialchars($reset_email) . '. Check your email.';
                $message_type = 'success';
            }
        }
    }
}

// Action: Verify code
if ($action === 'verify_code') {
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
            $code_sent = true;
        } else {
            $reset_data = $result->fetch_assoc();
            $stored_code = $reset_data['code'];
            $expires_at = $reset_data['expires_at'];

            // Check expiry
            if (time() > $expires_at) {
                $message = 'This reset code has expired. Please request a new one.';
                $message_type = 'error';
                $code_sent = true;
                // Delete expired code
                $del_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                $del_stmt->bind_param('s', $reset_email);
                $del_stmt->execute();
                $del_stmt->close();
            } else if ($code !== $stored_code) {
                $message = 'Invalid reset code. Please try again.';
                $message_type = 'error';
                $code_sent = true;
            } else {
                // Code is valid! Set session and redirect to password reset
                $_SESSION['reset_email'] = $reset_email;
                $_SESSION['reset_authenticated'] = true;
                unset($_SESSION['reset_email_sent']);
                header('Location: reset_password_new.php');
                exit();
            }
        }
    }
}

// Action: Reset password (final step)
if ($action === 'reset_password') {
    // Check if authenticated
    if (!isset($_SESSION['reset_authenticated']) || !isset($_SESSION['reset_email'])) {
        header('Location: reset_password.php');
        exit();
    }

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
    <style>
        .hidden { display: none; }
    </style>
</head>
<body style="background-color: #f5f5f5;">
    <div class="password-reset-container">
        <h1>Reset Password</h1>
        <p class="subtitle">Enter your email to get started.</p>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="password-reset-form">
            <!-- Step 1: Email input (always visible) -->
            <div class="form-group">
                <label for="email">Email Address</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    required 
                    autocomplete="email"
                    value="<?php echo htmlspecialchars($reset_email); ?>"
                >
            </div>

            <!-- Step 1 Button: Send/Resend Code -->
            <div class="form-actions" id="step1-buttons">
                <button type="submit" name="action" value="send_code" class="btn-primary">
                    <?php echo $code_sent ? 'Resend Code' : 'Send Reset Code'; ?>
                </button>
            </div>

            <!-- Step 2: Code input (hidden until code sent) -->
            <div id="code-section" class="<?php echo !$code_sent ? 'hidden' : ''; ?>">
                <div class="form-group">
                    <label for="code">Reset Code (6 digits)</label>
                    <input 
                        type="text" 
                        id="code" 
                        name="code" 
                        inputmode="numeric"
                        maxlength="6"
                        placeholder="000000"
                        value="<?php echo htmlspecialchars($_POST['code'] ?? ''); ?>"
                    >
                    <small style="color: #666; margin-top: 0.25rem;">Check your email for the code.</small>
                </div>

                <div class="form-actions">
                    <button type="submit" name="action" value="verify_code" class="btn-primary">
                        Verify Code
                    </button>
                </div>
            </div>
        </form>

        <div class="form-link">
            <a href="login.php">Back to Login</a>
        </div>
    </div>

    <script>
        // Show code section on page load if code was already sent
        document.addEventListener('DOMContentLoaded', function() {
            const codeSent = <?php echo $code_sent ? 'true' : 'false'; ?>;
            const codeSection = document.getElementById('code-section');
            if (codeSent) {
                codeSection.classList.remove('hidden');
            }
        });

        // Show code section when form is submitted with send_code action
        document.querySelector('.password-reset-form').addEventListener('submit', function(e) {
            const action = e.submitter?.value;
            if (action === 'send_code') {
                // Prevent default to show code section immediately
                // Actually, we'll let the form submit naturally and the PHP will handle it
            }
        });
    </script>
</body>
</html>
