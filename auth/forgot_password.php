<?php
session_start();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../config/config.php';
    require_once '../config/email_config.php'; // Include email function
    
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } else {
        // Check if email exists in database
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Generate a unique token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database (you'll need to create a password_resets table)
            $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expiry) VALUES (?, ?, ?) 
                                    ON DUPLICATE KEY UPDATE token = ?, expiry = ?");
            $stmt->bind_param("sssss", $email, $token, $expiry, $token, $expiry);
            $stmt->execute();
            
            // Send email with reset link
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
            
            $email_body = "
                <h2>Password Reset Request</h2>
                <p>You requested to reset your password. Click the link below to reset it:</p>
                <p><a href='$reset_link'>Reset Password</a></p>
                <p>This link will expire in 1 hour.</p>
                <p>If you didn't request this, please ignore this email.</p>
            ";
            
            // Send the email
            if (sendEmail($email, "Password Reset Request", $email_body)) {
                $message = 'If that email address is in our system, you will receive a password reset link shortly.';
            } else {
                $message = 'If that email address is in our system, you will receive a password reset link shortly.';
            }
        } else {
            // Don't reveal if email exists or not (security best practice)
            $message = 'If that email address is in our system, you will receive a password reset link shortly.';
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/forgot-password.css">
</head>
<body>
    <div class="forgot-password-container">
        <img src="../assets/images/logo.svg" alt="Darkhorse Logo" class="logo" />
        <h1>Forgot Password</h1>
        <p>Enter your email address and we'll send you a link to reset your password.</p>
        
        <?php if ($error): ?>
            <p class="error-message"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <p class="success-message"><?= $message ?></p>
        <?php endif; ?>
        
        <form action="forgot_password.php" method="post" class="forgot-form">
            <label for="email">Email Address:</label>
            <input type="email" id="email" name="email" required />
            <button type="submit">Send Reset Link</button>
        </form>
        
        <div class="back-to-login">
            <a href="index.php">← Back to Login</a>
        </div>
    </div>
</body>
</html>
