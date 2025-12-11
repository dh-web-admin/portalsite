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

$step = 'email'; // default
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_code') {

    $reset_email = trim($_POST['email'] ?? '');

    if (empty($reset_email)) {
        $message = "Please enter your email.";
        $message_type = "error";
        $step = 'email';
    } else {

        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $reset_email);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows === 0) {
            $message = "No account found with that email.";
            $message_type = "error";
            $step = 'email';
        } else {

            // Create reset code
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires_at = time() + (15 * 60); // 15 mins

            // Save reset code
            $stmt = $conn->prepare("REPLACE INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $reset_email, $code, $expires_at);
            $stmt->execute();
            $stmt->close();

            // Send email
            sendResetCode($reset_email, $code);

            // Save session state
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_code') {

    $reset_email = $_SESSION['reset_email'] ?? '';
    $submitted_code = trim($_POST['code'] ?? '');

    if (empty($reset_email)) {
        $message = "Your reset session has expired. Please request a new code.";
        $message_type = "error";
        $step = 'email';
    } elseif (empty($submitted_code)) {
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
            session_destroy();
            $step = 'email';
        } else {
            $data = $result->fetch_assoc();

            if (time() > $data['expires_at']) {
                $message = "Your reset code has expired.";
                $message_type = "error";
                session_destroy();
                $step = 'email';
            } elseif ($submitted_code !== $data['code']) {
                $message = "Invalid code. Please try again.";
                $message_type = "error";
                $step = 'verify_code';
            } else {
                // Code verified — proceed
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_password') {

    $reset_email = $_SESSION['reset_email'] ?? '';
    $new_pass = trim($_POST['new_password'] ?? '');
    $confirm_pass = trim($_POST['confirm_password'] ?? '');

    if (empty($reset_email)) {
        $message = "Your reset session has expired. Please request a new code.";
        $message_type = "error";
        $step = 'email';
    } elseif (empty($new_pass) || empty($confirm_pass)) {
        $message = "Please fill in both password fields.";
        $message_type = "error";
        $step = 'new_password';
    } elseif ($new_pass !== $confirm_pass) {
        $message = "Passwords do not match.";
        $message_type = "error";
        $step = 'new_password';
    } else {

        // Hash new password
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);

        // ALWAYS update by email
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashed, $reset_email);
        $stmt->execute();

        $execOk = $stmt->errno === 0;
        $affected = $stmt->affected_rows;
        $sqlErr = $stmt->error;
        $stmt->close();

        error_log("PASSWORD RESET | email=$reset_email | execOk=$execOk | affected=$affected | error=$sqlErr");

        if (!$execOk) {
            $message = "Error updating password: " . htmlspecialchars($sqlErr);
            $message_type = "error";
            $step = 'new_password';
        } else {
            /*
             * IMPORTANT PART:
             * Invalidate all remember-me tokens for this account
             */
            $stmt = $conn->prepare("UPDATE users SET remember_token = NULL, remember_token_expires = NULL WHERE email = ?");
            $stmt->bind_param("s", $reset_email);
            $stmt->execute();
            $stmt->close();

            // Clear remember_token cookie in browser (if set)
            if (isset($_COOKIE['remember_token'])) {
                $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

                setcookie('remember_token', '', [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }

            // Cleanup reset record
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->bind_param("s", $reset_email);
            $stmt->execute();
            $stmt->close();

            // Destroy session (logout everywhere)
            session_destroy();

            $message = "Your password has been reset successfully! Please log in with your new password.";
            $message_type = "success";
            $step = 'done';
        }
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


    <!-- DONE -->
    <?php if ($step === 'done'): ?>
        <a href="login.php" class="btn-primary">Go to Login</a>
    <?php endif; ?>

</div>
</body>
</html>
