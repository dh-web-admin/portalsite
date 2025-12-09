<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

$message = '';
$message_type = '';
$step = 'email'; // default step

// If we previously sent a code
if (isset($_SESSION['reset_email'])) {
    $reset_email = $_SESSION['reset_email'];
} else {
    $reset_email = '';
}

// Determine current progress
if (isset($_SESSION['reset_code_verified']) && $_SESSION['reset_code_verified'] === true) {
    $step = 'new_password';
} elseif (isset($_SESSION['reset_email_sent']) && $_SESSION['reset_email_sent'] === true) {
    $step = 'verify_code';
}

// ----------------------
// STEP 1: SEND CODE
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'send_code') {
    $reset_email = trim($_POST['email']);

    if (empty($reset_email)) {
        $message = "Please enter your email.";
        $message_type = "error";
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $reset_email);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows === 0) {
            $message = "No account found with this email.";
            $message_type = "error";
        } else {
            // Create code
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires_at = time() + (15 * 60);

            // Store in table
            $stmt = $conn->prepare("REPLACE INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $reset_email, $code, $expires_at);
            $stmt->execute();
            $stmt->close();

            // send email
            require_once __DIR__ . "/mailjet_helper.php";
            sendResetCode($reset_email, $code);

            $_SESSION['reset_email_sent'] = true;
            $_SESSION['reset_email'] = $reset_email;

            $step = 'verify_code';
            $message = "A reset code has been sent to your email.";
            $message_type = "success";
        }
    }
}

// ----------------------
// STEP 2: VERIFY CODE
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'verify_code') {
    $code = trim($_POST['code']);
    $reset_email = $_SESSION['reset_email'] ?? '';

    if (empty($reset_email) || empty($code)) {
        $message = "Please enter the code.";
        $message_type = "error";
        $step = "verify_code";
    } else {
        $stmt = $conn->prepare("SELECT code, expires_at FROM password_resets WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $reset_email);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows === 0) {
            $message = "No reset request found for that email.";
            $message_type = "error";
            $step = "verify_code";
        } else {
            $data = $result->fetch_assoc();

            if (time() > $data['expires_at']) {
                $message = "The reset code has expired.";
                $message_type = "error";
                $step = "email";
                session_destroy();
            } elseif ($code !== $data['code']) {
                $message = "Invalid code. Please try again.";
                $message_type = "error";
                $step = "verify_code";
            } else {
                // SUCCESS → show new password fields
                $_SESSION['reset_code_verified'] = true;
                $step = "new_password";
                $message = "Code verified! Please enter a new password.";
                $message_type = "success";
            }
        }
    }
}

// ----------------------
// STEP 3: SET NEW PASSWORD
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'set_password') {
    $new_pass = trim($_POST['new_password']);
    $confirm_pass = trim($_POST['confirm_password']);
    $reset_email = $_SESSION['reset_email'] ?? '';

    if (empty($new_pass) || empty($confirm_pass)) {
        $message = "Please fill both fields.";
        $message_type = "error";
        $step = "new_password";
    } elseif ($new_pass !== $confirm_pass) {
        $message = "Passwords do not match.";
        $message_type = "error";
        $step = "new_password";
    } else {
        // Update password
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashed, $reset_email);
        $stmt->execute();
        $stmt->close();

        // Cleanup
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->bind_param("s", $reset_email);
        $stmt->execute();
        $stmt->close();

        session_destroy();

        $message = "Your password has been reset successfully!";
        $message_type = "success";
        $step = "done";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link rel="stylesheet" href="password_reset.css">
</head>
<body>

<div class="password-reset-container">
    <h1>Password Reset</h1>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- STEP 1: ENTER EMAIL -->
    <?php if ($step === 'email'): ?>
        <form method="POST">
            <label>Email:</label>
            <input type="email" name="email" required>

            <button type="submit" name="action" value="send_code">Send Code</button>
        </form>
    <?php endif; ?>

    <!-- STEP 2: ENTER CODE -->
    <?php if ($step === 'verify_code'): ?>
        <form method="POST">
            <label>Enter 6-digit Code:</label>
            <input type="text" name="code" maxlength="6" required>

            <button type="submit" name="action" value="verify_code">Verify Code</button>
        </form>
    <?php endif; ?>

    <!-- STEP 3: NEW PASSWORD -->
    <?php if ($step === 'new_password'): ?>
        <form method="POST">
            <label>New Password:</label>
            <input type="password" name="new_password" required>

            <label>Confirm Password:</label>
            <input type="password" name="confirm_password" required>

            <button type="submit" name="action" value="set_password">Reset Password</button>
        </form>
    <?php endif; ?>

    <?php if ($step === 'done'): ?>
        <a href="login.php" class="btn-primary">Go to Login</a>
    <?php endif; ?>

</div>

</body>
</html>
