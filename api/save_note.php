<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
// Mark as API to avoid emitting UI CSS/JS from permissions helper
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json; charset=utf-8');

// expects POST: equipment_id, field, note
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$input = $_POST;
$equipment_id = isset($input['equipment_id']) ? (int)$input['equipment_id'] : 0;
$field = isset($input['field']) ? trim($input['field']) : '';
$note = isset($input['note']) ? trim($input['note']) : '';

if ($equipment_id <= 0 || $field === '') {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$email = $_SESSION['email'];
// Ensure caller is allowed to edit equipments
require_edit_api('equipments');

// fetch current user id for auditing
$roleStmt = $conn->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
$roleStmt->bind_param('s', $email);
$roleStmt->execute();
$roleRes = $roleStmt->get_result();
$user = $roleRes ? $roleRes->fetch_assoc() : null;
$userId = $user ? (int)$user['id'] : null;
$roleStmt->close();

// Upsert using INSERT ... ON DUPLICATE KEY UPDATE (requires UNIQUE(equipment_id, field))
$stmt = $conn->prepare('INSERT INTO notes (equipment_id, field, note, created_by) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE note = VALUES(note), updated_at = CURRENT_TIMESTAMP, created_by = VALUES(created_by)');
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Prepare failed']);
    exit;
}
$stmt->bind_param('issi', $equipment_id, $field, $note, $userId);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => 'Execute failed']);
    $stmt->close();
    exit;
}
$stmt->close();
echo json_encode(['success' => true]);
