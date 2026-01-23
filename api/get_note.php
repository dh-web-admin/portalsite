<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
// Mark as API to avoid emitting UI CSS/JS from permissions helper
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json; charset=utf-8');

// expects GET: equipment_id, field
$equipment_id = isset($_GET['equipment_id']) ? (int)$_GET['equipment_id'] : 0;
$field = isset($_GET['field']) ? trim($_GET['field']) : '';

if ($equipment_id <= 0 || $field === '') {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

// basic permission: viewers can read notes if they can access equipments
$email = isset($_SESSION['email']) ? $_SESSION['email'] : null;
if (!$email) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$roleStmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$roleStmt->bind_param('s', $email);
$roleStmt->execute();
$roleRes = $roleStmt->get_result();
$user = $roleRes ? $roleRes->fetch_assoc() : null;
$role = $user ? $user['role'] : 'laborer';
$roleStmt->close();

if (!can_access($role, 'equipments')) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$stmt = $conn->prepare('SELECT note, created_by, created_at, updated_at FROM notes WHERE equipment_id = ? AND field = ? LIMIT 1');
$stmt->bind_param('is', $equipment_id, $field);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if ($row) {
    echo json_encode(['success' => true, 'note' => $row['note'], 'created_by' => $row['created_by'], 'created_at' => $row['created_at'], 'updated_at' => $row['updated_at']]);
} else {
    echo json_encode(['success' => true, 'note' => '']);
}
