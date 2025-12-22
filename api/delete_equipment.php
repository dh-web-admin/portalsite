<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$equipmentId = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
if ($equipmentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid equipment ID.']);
    exit();
}

$stmt = $conn->prepare('DELETE FROM equipments WHERE equipment_id = ?');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit();
}
$stmt->bind_param('i', $equipmentId);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
    exit();
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $stmt->error]);
    exit();
}
