<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

$message = '';
$message_type = '';
$reset_email = '';
$code_sent = false;
$code_verified = false;
$action = $_POST['action'] ?? '';

// If already verified, send user to the password entry page
if (isset($_SESSION['reset_authenticated']) && $_SESSION['reset_authenticated']) {
    // make redirect absolute to avoid relative path issues and ensure session is saved
    session_write_close();
    header('Location: /auth/reset_password_new.php');
    exit();
}

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
                // Persist that we sent the code so the form shows the code input
                $_SESSION['reset_email_sent'] = $reset_email;
                // Ensure session data is written before response continues
                session_write_close();

                $code_sent = true;
                $message = 'Reset code sent to ' . htmlspecialchars($reset_email) . '. Check your email and enter the code below.';
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
        // Debug: log verify attempts to help diagnose intermittent failures
        $logEntry = [];
        $logEntry[] = date('c');
        $logEntry[] = session_id();
        $logEntry[] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $logEntry[] = 'verify_attempt';
        $logEntry[] = $reset_email;
        $logEntry[] = $code;
        $preLog = implode(" | ", $logEntry) . "\n";
        @file_put_contents(__DIR__ . '/../debug/password_reset_verify.log', $preLog, FILE_APPEND | LOCK_EX);

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

            if (time() > $expires_at) {
                $message = 'This reset code has expired. Please request a new one.';
                $message_type = 'error';

                $del_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                $del_stmt->bind_param('s', $reset_email);
                $del_stmt->execute();
                $del_stmt->close();

                $code_sent = true;
                @file_put_contents(__DIR__ . '/../debug/password_reset_verify.log', date('c') . " | session:" . session_id() . " | expired for " . $reset_email . "\n", FILE_APPEND | LOCK_EX);
            } else if ($code !== $stored_code) {
                $message = 'Invalid reset code. Please try again.';
                $message_type = 'error';
                $code_sent = true;
                @file_put_contents(__DIR__ . '/../debug/password_reset_verify.log', date('c') . " | session:" . session_id() . " | invalid code submitted for " . $reset_email . " (submitted:" . $code . ", stored:" . $stored_code . ")\n", FILE_APPEND | LOCK_EX);
            } else {
                // Mark the session as authenticated for the reset flow
                $_SESSION['reset_email'] = $reset_email;
                $_SESSION['reset_authenticated'] = true;

                // Remove the temporary "email_sent" flag so the old form doesn't show
                unset($_SESSION['reset_email_sent']);

                // Flush session to storage to ensure the next request sees these flags
                session_write_close();

                // Redirect to the dedicated password entry page (absolute path)
                header('Location: /auth/reset_password_new.php');
                @file_put_contents(__DIR__ . '/../debug/password_reset_verify.log', date('c') . " | session:" . session_id() . " | verified OK for " . $reset_email . "\n", FILE_APPEND | LOCK_EX);
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
            <div class="form-group">
                <label for="email">Email Address</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    required 
                    value="<?php echo htmlspecialchars($reset_email); ?>"
                >
            </div>

            <div class="form-actions">
                <button type="submit" name="action" value="send_code" class="btn-primary">
                    <?php echo $code_sent ? 'Resend Code' : 'Send Reset Code'; ?>
                </button>
            </div>

            <div id="code-section" class="<?php echo !$code_sent ? 'hidden' : ''; ?>">
                <div class="form-group">
                    <label for="code">Reset Code (6 digits)</label>
                    <input 
                        type="text" 
                        id="code" 
                        name="code" 
                        maxlength="6"
                    >
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
        document.addEventListener('DOMContentLoaded', function() {
            const codeSent = <?php echo $code_sent ? 'true' : 'false'; ?>;
            const codeSection = document.getElementById('code-section');

            if (codeSent) {
                codeSection.classList.remove('hidden');
            }
        });
    </script>
</body>
</html>
