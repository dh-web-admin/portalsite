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

$part_id = 0;
$stmt = $conn->prepare('SELECT id FROM engineering_item_parts WHERE item_id = ? AND part_name = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('is', $item_id, $part_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $part_id = $row ? (int)$row['id'] : 0;
    $stmt->close();
}

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

$tableCheck = $conn->query("SHOW TABLES LIKE 'engineering_drawings'");
if ($tableCheck && $tableCheck->num_rows > 0 && $part_id > 0) {
    $stmt = $conn->prepare('DELETE FROM engineering_drawings WHERE item_id = ? AND part_id = ?');
    if ($stmt) {
        $stmt->bind_param('ii', $item_id, $part_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Also delete from Bill of Materials section (Engineering_material_parts)
$stmt = $conn->prepare('DELETE emp FROM Engineering_material_parts emp 
                        JOIN Engineering_materials em ON emp.material_id = em.id 
                        WHERE em.item_id = ? AND emp.name = ?');
if ($stmt) {
    $stmt->bind_param('is', $item_id, $part_name);
    $stmt->execute();
    $stmt->close();
}

$conn->commit();

echo json_encode(['success' => true]);
