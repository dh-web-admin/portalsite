<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$allowed = ['', 'Ongoing', 'Completed', 'Cancelled'];
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

if (!in_array($status, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

$email = $_SESSION['email'];
$stmt = $conn->prepare('UPDATE users SET checklist_status_filter = ? WHERE email = ?');
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
    exit;
}
$stmt->bind_param('ss', $status, $email);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => (bool)$ok]);
