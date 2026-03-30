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
    $err = 'New passwords do not match';
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $err]);
        exit();
    }
    $_SESSION['password_error'] = $err;
    header('Location: ../pages/account_settings/');
    exit();
}

// Password validation: at least 8 chars, 1 number, 1 uppercase, 1 special char
if (strlen($new_password) < 8) {
    $err = 'Password must be at least 8 characters';
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => $err]); exit(); }
    $_SESSION['password_error'] = $err;
    header('Location: ../pages/account_settings/');
    exit();
}

if (!preg_match('/[0-9]/', $new_password)) {
    $err = 'Password must contain at least one number';
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => $err]); exit(); }
    $_SESSION['password_error'] = $err;
    header('Location: ../pages/account_settings/');
    exit();
}

if (!preg_match('/[A-Z]/', $new_password)) {
    $err = 'Password must contain at least one uppercase letter';
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => $err]); exit(); }
    $_SESSION['password_error'] = $err;
    header('Location: ../pages/account_settings/');
    exit();
}

if (!preg_match('/[!@#$%^&*()_+\-=[\]{};:\'"\\|,.<>\/\?]/', $new_password)) {
    $err = 'Password must contain at least one special character';
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => $err]); exit(); }
    $_SESSION['password_error'] = $err;
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
    $err = 'User not found';
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => $err]); exit(); }
    $_SESSION['password_error'] = $err;
    header('Location: ../pages/account_settings/');
    exit();
}

$user = $res->fetch_assoc();
$stmt->close();

if (!password_verify($current_password, $user['password'])) {
    $err = 'Current password is incorrect';
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => $err]); exit(); }
    $_SESSION['password_error'] = $err;
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

    // Set a session message and redirect back to settings; the page will show a status and then logout.
    // If this was an AJAX request, return JSON success; otherwise set session and redirect
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
        $stmt->close();
        $conn->close();
        exit();
    }

    $_SESSION['password_success'] = 'Password updated successfully. You will be logged out for security.';
    $_SESSION['password_logout'] = true;
    $stmt->close();
    $conn->close();
    header('Location: ../pages/account_settings/');
    exit();
} else {
    $_SESSION['password_error'] = 'Failed to update password';
}

$stmt->close();
$conn->close();

header('Location: ../pages/account_settings/');
exit();
?>
