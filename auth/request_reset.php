<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/mailjet_helper.php';

$message = '';
$message_type = '';
$show_code_field = false;   // Controls visibility of the code input
$entered_email = '';
$entered_code = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Which button was clicked?
    $action = $_POST['action'] ?? '';
    $entered_email = trim($_POST['email'] ?? '');
    $entered_code = trim($_POST['reset_code'] ?? '');

    // -------------------------------------------
    // ACTION 1: SEND OR RESEND RESET CODE
    // -------------------------------------------
    if ($action === 'send_code') {

        if (empty($entered_email)) {
            $message = 'Please enter your email address.';
            $message_type = 'error';
        } else {
            // Check user existence
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $check_stmt->bind_param('s', $entered_email);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            $check_stmt->close();

            if ($result->num_rows === 0) {
                $message = 'No account found for that email.';
                $message_type = 'error';
            } else {
                // Generate code
                $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires_at = time() + (15 * 60);

                // Save or replace
                $stmt = $conn->prepare("REPLACE INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)");
                $stmt->bind_param('ssi', $entered_email, $code, $expires_at);
                $stmt->execute();
                $stmt->close();

                // Send email
                $result = sendResetCode($entered_email, $code);

                if (!$result['success']) {
                    $message = "Failed to send reset code: " . $result['error'];
                    $message_type = 'error';
                } else {
                    $message = "A reset code has been sent to $entered_email.";
                    $message_type = 'success';
                    $show_code_field = true;
                }
            }
        }
    }

    // -------------------------------------------
    // ACTION 2: VERIFY CODE
    // -------------------------------------------
    if ($action === 'verify_code') {

        if (empty($entered_email) || empty($entered_code)) {
            $message = "Please enter your email and reset code.";
            $message_type = "error";
            $show_code_field = true;
        } else {
            $stmt = $conn->prepare("SELECT code, expires_at FROM password_resets WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $entered_email);
            $stmt->execute();
            $res = $stmt->get_result();
            $stmt->close();

            if ($res->num_rows === 0) {
                $message = "No reset request found. Please request a new code.";
                $message_type = "error";
            } else {
                $row = $res->fetch_assoc();

                if ($row['expires_at'] < time()) {
                    $message = "Your reset code has expired. Please resend a new code.";
                    $message_type = "error";
                    $show_code_field = true;
                } elseif ($row['code'] != $entered_code) {
                    $message = "Invalid reset code. Please try again.";
                    $message_type = "error";
                    $show_code_field = true;
                } else {
                    // Code verified → redirect to reset password form
                    header("Location: reset_password.php?email=" . urlencode($entered_email));
                    exit;
                }
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

            <!-- EMAIL FIELD -->
            <div class="form-group">
                <label for="email">Email Address</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    required 
                    value="<?php echo htmlspecialchars($entered_email); ?>"
                >
            </div>

            <!-- RESET CODE FIELD (shown after sending code) -->
            <?php if ($show_code_field): ?>
            <div class="form-group">
                <label for="reset_code">Enter Reset Code</label>
                <input 
                    type="text" 
                    id="reset_code" 
                    name="reset_code" 
                    maxlength="6"
                    value="<?php echo htmlspecialchars($entered_code); ?>"
                >
            </div>
            <?php endif; ?>

            <div class="form-actions">
                <!-- The send/resend button -->
                <button 
                    type="submit" 
                    name="action" 
                    value="send_code" 
                    class="btn-primary"
                >
                    <?php echo $show_code_field ? 'Resend Code' : 'Send Reset Code'; ?>
                </button>

                <!-- Verify button appears only when code field is shown -->
                <?php if ($show_code_field): ?>
                    <button 
                        type="submit" 
                        name="action" 
                        value="verify_code" 
                        class="btn-secondary"
                        style="margin-left: 10px;"
                    >
                        Verify Code
                    </button>
                <?php endif; ?>
            </div>
        </form>

        <div class="form-link">
            Remember your password? <a href="login.php">Back to Login</a>
        </div>
    </div>
</body>
</html>
