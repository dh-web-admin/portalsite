<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';
header('Content-Type: application/json');

// Auth & permission
if (empty($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$role = function_exists('get_current_role') ? get_current_role() : null;
$canEdit = function_exists('can_edit_page') ? can_edit_page('equipments') : false;

if (!$canEdit) {
    http_response_code(403);
    $ovr = null;
    if (!empty($_SESSION['email']) && function_exists('get_user_page_override')) {
        $ovr = get_user_page_override($_SESSION['email'], 'equipments');
    }
    echo json_encode([
        'success' => false,
        'message' => 'Forbidden',
        'debug' => ['role' => $role, 'email' => $_SESSION['email'] ?? null, 'override' => $ovr]
    ]);
    exit();
}

$equipment_id = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
$part_name = isset($_POST['part_name']) ? trim($_POST['part_name']) : '';

if ($equipment_id <= 0 || $part_name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid equipment or part name.']);
    exit();
}

$stmt = $conn->prepare('DELETE FROM equipment_parts WHERE equipment_id = ? AND part_name = ?');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare delete statement.']);
    exit();
}

$stmt->bind_param('is', $equipment_id, $part_name);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete part.']);
    exit();
}

echo json_encode(['success' => true]);
