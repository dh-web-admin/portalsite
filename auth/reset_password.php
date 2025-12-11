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
$reset_user_id = $_SESSION['reset_user_id'] ?? null;

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
        // Grab user ID from DB
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
            $row = $result->fetch_assoc();
            $user_id = intval($row['id']);

            // Store ID in session
            $_SESSION['reset_user_id'] = $user_id;

            // Generate code
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires_at = time() + (15 * 60);

            // Save code
            $stmt = $conn->prepare("REPLACE INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $reset_email, $code, $expires_at);
            $stmt->execute();
            $stmt->close();

            // Send mail
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
 STEP 2 — VERIFY CODE (NOW SAFELY FETCHES USER ID AGAIN)
-------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'verify_code') {

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
            session_destroy();
            $step = 'email';
        } else {
            $data = $result->fetch_assoc();

            // Expired?
            if (time() > $data['expires_at']) {
                $message = "Your reset code has expired.";
                $message_type = "error";
                session_destroy();
                $step = 'email';
            }
            // Wrong code?
            elseif ($submitted_code !== $data['code']) {
                $message = "Invalid code. Please try again.";
                $message_type = "error";
                $step = 'verify_code';
            }
            // Code OK
            else {

                // 🔥 FIX: Re-fetch user ID to ensure accurate update
                $get = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $get->bind_param("s", $reset_email);
                $get->execute();
                $id_result = $get->get_result();
                $get->close();

                if ($id_result->num_rows > 0) {
                    $_SESSION['reset_user_id'] = intval($id_result->fetch_assoc()['id']);
                }

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
    $reset_user_id = $_SESSION['reset_user_id'] ?? null;
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

        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);

        // Must update by ID (safest)
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $reset_user_id);
        $stmt->execute();

        $execOk = $stmt->errno === 0;
        $affected = $stmt->affected_rows;
        $sqlErr = $stmt->error;
        $stmt->close();

        // Diagnostic
        error_log("UPDATE PASSWORD | id=$reset_user_id | execOk=$execOk | affected=$affected | error=$sqlErr");

        if (!$execOk || $affected === 0) {
            $message = "Password update failed. (affected=$affected | error=$sqlErr)";
            $message_type = "error";
            $step = 'new_password';
        } else {

            // CLEANUP
                // Verify the stored hash matches the new password to be sure update applied
                $db_hash = null;
                $q = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
                if ($q) {
                    $q->bind_param('i', $reset_user_id);
                    $q->execute();
                    $res = $q->get_result();
                    if ($res && $res->num_rows > 0) {
                        $db_hash = $res->fetch_assoc()['password'] ?? null;
                    }
                    $q->close();
                }

                $verify_new_matches = false;
                if ($db_hash !== null) {
                    $verify_new_matches = password_verify($new_pass, $db_hash);
                }

                if (!$verify_new_matches) {
                    // Strange: update reported success but stored hash doesn't match new password
                    error_log("Password reset: UPDATE succeeded but verification failed for id=" . $reset_user_id);
                    $message = "Password updated but verification failed. Please contact support.";
                    $message_type = 'error';
                    $step = 'new_password';
                } else {
                    // Clear remember token to prevent old persistent login from bypassing new password
                    $clear = $conn->prepare("UPDATE users SET remember_token = NULL, remember_token_expires = NULL WHERE id = ?");
                    if ($clear) {
                        $clear->bind_param('i', $reset_user_id);
                        $clear->execute();
                        $clear->close();
                    }

                    // Remove the reset request
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


    <!-- STEP 4: DONE -->
    <?php if ($step === 'done'): ?>
        <a href="login.php" class="btn-primary">Go to Login</a>
    <?php endif; ?>

</div>
</body>
</html>
