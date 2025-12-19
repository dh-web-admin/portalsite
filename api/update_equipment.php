<?php
// Suppress all error output except for JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
while (ob_get_level()) ob_end_clean();

$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input.']);
    exit();
}

$equipmentId = isset($input['equipment_id']) ? (int)$input['equipment_id'] : 0;
if ($equipmentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid equipment ID.']);
    exit();
}

$fields = [
    'dhcst_equipment_number',
    'dhss_equipment_number',
    'type',
    'engine',
    'year',
    'engine_serial_number',
    'vin',
    'transmission',
    'location',
    'trans_serial_number',
    'model'
];

$set = [];
$params = [];
$types = '';
foreach ($fields as $field) {
    if (isset($input[$field])) {
        $set[] = "$field = ?";
        $params[] = $input[$field];
        $types .= 's';
    }
}
if (empty($set)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No fields to update.']);
    exit();
}
$params[] = $equipmentId;
$types .= 'i';

$sql = "UPDATE equipment SET " . implode(', ', $set) . " WHERE equipment_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
    exit();
}
$stmt->bind_param($types, ...$params);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
    exit();
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $stmt->error]);
    exit();
}
