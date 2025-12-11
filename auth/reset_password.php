<?php
// Always start session BEFORE output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/mailjet_helper.php';

$message = '';
$message_type = '';

/*
-------------------------------------------------------
 STEP HANDLING
-------------------------------------------------------
*/

$step = 'email'; // default first step
$reset_email = $_SESSION['reset_email'] ?? '';

if (!empty($_SESSION['reset_email']) && !empty($_SESSION['reset_email_sent'])) {
    $step = 'verify_code';
}

if (!empty($_SESSION['reset_code_verified'])) {
    $step = 'new_password';
}

/*
-------------------------------------------------------
 STEP 1 — SEND CODE
-------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'send_code') {

    $reset_email = trim($_POST['email']);

    if (empty($reset_email)) {
        $message = "Please enter your email.";
        $message_type = "error";
        $step = 'email';
    } else {
        // Validate email exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->bind_param("s", $reset_email);
        $check->execute();
        $result = $check->get_result();
        $check->close();

        if ($result->num_rows === 0) {
            $message = "No account found with that email.";
            $message_type = "error";
            $step = 'email';
        } else {
            // Create code
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires_at = time() + (15 * 60);

            // Store code
            $stmt = $conn->prepare("REPLACE INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $reset_email, $code, $expires_at);
            $stmt->execute();
            $stmt->close();

            // Send email
            sendResetCode($reset_email, $code);

            // Save state
            $_SESSION['reset_email'] = $reset_email;
            $_SESSION['reset_email_sent'] = true;

            $message = "A reset code has been sent to your email.";
            $message_type = "success";
            $step = 'verify_code';
        }
    }
}

/*
-------------------------------------------------------
 STEP 2 — VERIFY CODE
-------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'verify_code') {

    // Email MUST come from session
    $reset_email = $_SESSION['reset_email'] ?? '';
    $submitted_code = trim($_POST['code']);

    if (empty($reset_email) || empty($submitted_code)) {
        $message = "Please enter the code.";
        $message_type = "error";
        $step = 'verify_code';
    } else {
        $stmt = $conn->prepare("SELECT code, expires_at FROM password_resets WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $reset_email);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows === 0) {
            $message = "No reset request found. Please request a new code.";
            $message_type = "error";
            $step = 'email';
            session_destroy();
        } else {
            $data = $result->fetch_assoc();

            if (time() > $data['expires_at']) {
                $message = "Your reset code has expired.";
                $message_type = "error";
                $step = 'email';
                session_destroy();
            } elseif ($submitted_code !== $data['code']) {
                $message = "Invalid code. Please try again.";
                $message_type = "error";
                $step = 'verify_code';
            } else {
                // SUCCESS: move to new password
                $_SESSION['reset_code_verified'] = true;
                $message = "Code verified! Please enter your new password.";
                $message_type = "success";
                $step = 'new_password';
            }
        }
    }
}

/*
-------------------------------------------------------
 STEP 3 — SET NEW PASSWORD
-------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'set_password') {

    $reset_email = $_SESSION['reset_email'] ?? '';
    $new_pass = trim($_POST['new_password']);
    $confirm_pass = trim($_POST['confirm_password']);

    if (empty($new_pass) || empty($confirm_pass)) {
        $message = "Please fill in both password fields.";
        $message_type = "error";
        $step = 'new_password';
    } elseif ($new_pass !== $confirm_pass) {
        $message = "Passwords do not match.";
        $message_type = "error";
        $step = 'new_password';
    } else {
        // Update password
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashed, $reset_email);
        $execOk = false;
        if ($stmt) {
            $execOk = $stmt->execute();
            $affected = $stmt->affected_rows;
            $sqlErr = $stmt->error;
            $stmt->close();
        } else {
            $affected = 0;
            $sqlErr = $conn->error ?? 'prepare_failed';
        }

        // Log the outcome for diagnostics
        $log = date('c') . " | update_password | email:" . $reset_email . " | execOk:" . ($execOk ? '1' : '0') . " | affected:" . $affected . " | error:" . $sqlErr . "\n";
        @file_put_contents(__DIR__ . '/../debug/password_reset_update.log', $log, FILE_APPEND | LOCK_EX);
        error_log($log);

        // Cleanup
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->bind_param("s", $reset_email);
        $stmt->execute();
        $stmt->close();

        session_destroy();

        $message = "Your password has been reset successfully!";
        $message_type = "success";
        $step = 'done';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Password Reset</title>
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


    <!-- STEP 1: EMAIL -->
    <?php if ($step === 'email'): ?>
        <form method="POST">
            <label>Email:</label>
            <input type="email" name="email" required>
            <button type="submit" name="action" value="send_code">Send Code</button>
        </form>
    <?php endif; ?>


    <!-- STEP 2: VERIFY CODE -->
    <?php if ($step === 'verify_code'): ?>
        <form method="POST">
            <label>Enter 6-digit code:</label>
            <input type="text" name="code" maxlength="6" required>

            <!-- Not needed now because session holds email -->
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


    <!-- STEP 4: DONE -->
    <?php if ($step === 'done'): ?>
        <a href="login.php" class="btn-primary">Go to Login</a>
    <?php endif; ?>

</div>
</body>
</html>
