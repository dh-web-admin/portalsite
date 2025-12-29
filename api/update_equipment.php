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
    'dhss_equipment_number',
    'type',
    'operating_condition',
    'location',
    'current_hours',
    'oil_status',
    'vin',
    'vehicle_year',
    'make',
    'model',
    'engine',
    'engine_serial_number',
    'transmission',
    'trans_serial_number',
    'transfer_case_serial',
    'front_differential_serial',
    'middle_differential_serial',
    'rear_differential_serial',
    'dhcst_equipment_number'
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

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $stmt->error]);
    exit();
}
$stmt->close();

// --- Multi-upload support for air_filters, warranty, tires ---
$upload_dir = realpath(__DIR__ . '/../uploads/equipment');
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

function handle_multi_upload($files, $equipment_id, $field, $conn, $upload_dir) {
    if (!$files || !isset($files['tmp_name'])) return;
    $count = is_array($files['tmp_name']) ? count($files['tmp_name']) : 0;
    for ($i = 0; $i < $count; $i++) {
        if (isset($files['tmp_name'][$i]) && is_uploaded_file($files['tmp_name'][$i])) {
            $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $safe_name = uniqid($field . '_') . '.' . $ext;
            $target = $upload_dir . DIRECTORY_SEPARATOR . $safe_name;
            if (move_uploaded_file($files['tmp_name'][$i], $target)) {
                $url = 'uploads/equipment/' . $safe_name;
                $stmt = $conn->prepare('INSERT INTO equipment_uploads (equipment_id, field, file_url) VALUES (?, ?, ?)');
                $stmt->bind_param('iss', $equipment_id, $field, $url);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

// Accept multiple files for each field (if present)
if (isset($_FILES['air_filters']) && is_array($_FILES['air_filters']['name'])) {
    handle_multi_upload($_FILES['air_filters'], $equipmentId, 'air_filters', $conn, $upload_dir);
}
if (isset($_FILES['warranty']) && is_array($_FILES['warranty']['name'])) {
    handle_multi_upload($_FILES['warranty'], $equipmentId, 'warranty', $conn, $upload_dir);
}
if (isset($_FILES['tires']) && is_array($_FILES['tires']['name'])) {
    handle_multi_upload($_FILES['tires'], $equipmentId, 'tires', $conn, $upload_dir);
}

echo json_encode(['success' => true]);
exit();
