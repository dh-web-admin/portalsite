<?php
// Suppress all error output except for JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
while (ob_get_level()) ob_end_clean();


// Accept POST data from form
$equipmentId = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
if ($equipmentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid equipment ID.']);
    exit();
}

$fields = [
    'equipment_number',
    'type',
    'operating_condition',
    'location',
    'current_hours',
    'oil_status'
];
$set = [];
$params = [];
$types = '';
foreach ($fields as $field) {
    if (isset($_POST[$field])) {
        $set[] = "$field = ?";
        $params[] = $_POST[$field];
        $types .= 's';
    }
}
if (empty($set)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No fields to update.']);
    exit();
}
$params[] = $equipmentId;
$types .= 'i';

$sql = "UPDATE equipments SET " . implode(', ', $set) . " WHERE equipment_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit();
}
$stmt->bind_param($types, ...$params);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
    exit();
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $stmt->error]);
    exit();
}
