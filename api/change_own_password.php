<?php
session_start();
require_once '../config/config.php';

// Auth check
if (!isset($_SESSION['email'])) {
    $_SESSION['password_error'] = 'Not authenticated';
    header('Location: ../pages/account_settings/');
    exit();
}

// Get POST data
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    $_SESSION['password_error'] = 'All fields are required';
    header('Location: ../pages/account_settings/');
    exit();
}

// Check if new passwords match
if ($new_password !== $confirm_password) {
    $_SESSION['password_error'] = 'New passwords do not match';
    header('Location: ../pages/account_settings/');
    exit();
}

// Password validation: at least 8 chars, 1 number, 1 uppercase, 1 special char
if (strlen($new_password) < 8) {
    $_SESSION['password_error'] = 'Password must be at least 8 characters';
    header('Location: ../pages/account_settings/');
    exit();
}

if (!preg_match('/[0-9]/', $new_password)) {
    $_SESSION['password_error'] = 'Password must contain at least one number';
    header('Location: ../pages/account_settings/');
    exit();
}

if (!preg_match('/[A-Z]/', $new_password)) {
    $_SESSION['password_error'] = 'Password must contain at least one uppercase letter';
    header('Location: ../pages/account_settings/');
    exit();
}

if (!preg_match('/[!@#$%^&*()_+\-=[\]{};:\'"\\|,.<>\/\?]/', $new_password)) {
    $_SESSION['password_error'] = 'Password must contain at least one special character';
    header('Location: ../pages/account_settings/');
    exit();
}

$email = $_SESSION['email'];

// Verify current password
$stmt = $conn->prepare("SELECT password FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    $_SESSION['password_error'] = 'User not found';
    header('Location: ../pages/account_settings/');
    exit();
}

$user = $res->fetch_assoc();
$stmt->close();

if (!password_verify($current_password, $user['password'])) {
    $_SESSION['password_error'] = 'Current password is incorrect';
    header('Location: ../pages/account_settings/');
    exit();
}

// Hash the new password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Update the password
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->bind_param("ss", $hashed_password, $email);

if ($stmt->execute()) {
    // Invalidate any remember-me token so auto-login won't occur
    $clear = $conn->prepare("UPDATE users SET remember_token = NULL, remember_token_expires = NULL WHERE email = ?");
    if ($clear) { $clear->bind_param('s', $email); $clear->execute(); $clear->close(); }

    // Expire remember_token cookie
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

    // Render a lightweight confirmation page and auto-redirect to the logout endpoint after a short delay
    // (Logout page will fully clear the session and show the final message)
    $conn->close();
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Password Updated</title>'
        . '<meta http-equiv="refresh" content="3;url=../auth/logout.php">'
        . '<style>body{font-family: system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0}'
        . '.card{background:#fff;padding:28px 26px;border-radius:12px;box-shadow:0 10px 30px rgba(2,6,23,.18);max-width:520px;width:92%;text-align:center}'
        . '.card h1{margin:0 0 10px 0;font-size:22px;color:#0f172a}'
        . '.card p{margin:6px 0 0 0;color:#475569;font-size:14px}'
        . '.actions{margin-top:16px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap}'
        . '.btn{padding:10px 14px;border-radius:8px;border:1px solid rgba(15,23,42,.06);text-decoration:none;font-weight:700;font-size:14px}'
        . '.btn.primary{background:#667eea;color:#fff;border-color:rgba(102,126,234,.2)}'
        . '.btn.ghost{background:#f1f5f9;color:#0f172a}'
        . '</style>'
        . '<script>setTimeout(function(){window.location.href="../auth/logout.php";},3000);</script>'
        . '</head><body><div class="card">'
        . '<h1>Password updated</h1>'
        . '<p>You will be logged out for security and can sign back in with your new password.</p>'
        . '<p style="margin-top:10px;color:#64748b">Redirecting to logout…</p>'
        . '<div class="actions">'
        . '<a class="btn primary" href="../auth/logout.php">Logout now</a>'
        . '<a class="btn ghost" href="../pages/account_settings/">Stay on settings</a>'
        . '</div>'
        . '</div></body></html>';
    exit();
} else {
    $_SESSION['password_error'] = 'Failed to update password';
}

$stmt->close();
$conn->close();

header('Location: ../pages/account_settings/');
exit();
?>
