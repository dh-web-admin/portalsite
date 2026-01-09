<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json; charset=utf-8');

require_edit_api('admin_panel');

// Get POST data
$id = $_POST['id'] ?? '';
$new_password = $_POST['new_password'] ?? '';

if (empty($id) || empty($new_password)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

// Password validation: at least 8 chars, 1 number, 1 uppercase, 1 special char
if (strlen($new_password) < 8) {
    echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']);
    exit();
}

if (!preg_match('/[0-9]/', $new_password)) {
    echo json_encode(['success' => false, 'error' => 'Password must contain at least one number']);
    exit();
}

if (!preg_match('/[A-Z]/', $new_password)) {
    echo json_encode(['success' => false, 'error' => 'Password must contain at least one uppercase letter']);
    exit();
}

if (!preg_match('/[!@#$%^&*()_+\-=[\]{};:\'"\\|,.<>\/\?]/', $new_password)) {
    echo json_encode(['success' => false, 'error' => 'Password must contain at least one special character']);
    exit();
}

// Hash the new password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Update the password
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->bind_param("si", $hashed_password, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database update failed']);
}

$stmt->close();
$conn->close();
?>
