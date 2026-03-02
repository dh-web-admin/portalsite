<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
header('Content-Type: application/json');

// Simple auth check (match add_engineering_item_part behavior)
if (empty($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$part_name = isset($_POST['part_name']) ? trim($_POST['part_name']) : '';

if ($item_id <= 0 || $part_name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid item or part name.']);
    exit();
}

$conn->begin_transaction();

$stmt = $conn->prepare('DELETE FROM engineering_item_parts WHERE item_id = ? AND part_name = ?');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare delete statement.']);
    exit();
}

$stmt->bind_param('is', $item_id, $part_name);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete part.']);
    exit();
}

$stmt = $conn->prepare('DELETE FROM engineering_part_specifications WHERE part_name = ?');
if (!$stmt) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare delete specs statement.']);
    exit();
}

$stmt->bind_param('s', $part_name);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete part specifications.']);
    exit();
}

$conn->commit();

echo json_encode(['success' => true]);
