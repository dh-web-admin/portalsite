<?php
session_start();
require_once '../config/config.php';
header('Content-Type: application/json; charset=utf-8');

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);
    exit();
}

if (!isset($_SESSION['email'])) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Not authenticated']);
    exit();
}

$adminEmail = $_SESSION['email'];
$stmt = $conn->prepare("SELECT role FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $adminEmail);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Unauthorized']);
    exit();
}
$row = $res->fetch_assoc();
if ($row['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Admins only']);
    exit();
}
$stmt->close();

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$name = trim($_POST['name'] ?? '');
$role = $_POST['role'] ?? '';
$password = $_POST['password'] ?? '';

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Invalid user id']);
    exit();
}

$allowed_roles = ['admin','projectmanager','estimator','accounting','superintendent','foreman','mechanic','operator','laborer'];
if (!in_array($role, $allowed_roles, true)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Invalid role']);
    exit();
}

// Prevent changing own role to non-admin? We'll allow editing but prevent deleting self elsewhere.

// Build update depending on provided fields. We allow role updates alone.
$params = [];
$types = '';
$sets = [];

// Only update name if provided (non-empty)
if ($name !== '') {
    $sets[] = 'name = ?';
    $types .= 's';
    $params[] = $name;
}

// Role is required for this endpoint (validated above)
$sets[] = 'role = ?';
$types .= 's';
$params[] = $role;

if ($password !== '') {
    if (strlen($password) < 8 || !preg_match('/[0-9]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/[!@#$%^&*()_+\-=[\]{};:\'"\\|,.<>\/\?]/', $password)) {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'Password must be at least 8 chars, include number, uppercase and special char']);
        exit();
    }
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $sets[] = 'password = ?';
    $types .= 's';
    $params[] = $hashed;
}

$types .= 'i';
$params[] = $id;

$sql = "UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?";
$update = $conn->prepare($sql);
// bind params dynamically
$update->bind_param($types, ...$params);

if ($update->execute()) {
    echo json_encode(['success'=>true,'message'=>'User updated']);
} else {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Update failed: '.$conn->error]);
}
$update->close();
$conn->close();
?>
