<?php
require_once __DIR__ . '/../session_init.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';
header('Content-Type: application/json');

require_edit_api('equipments');

$equipment_id = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
$steer_tire_make = isset($_POST['steer_tire_make']) ? trim($_POST['steer_tire_make']) : null;
$steer_tire_model = isset($_POST['steer_tire_model']) ? trim($_POST['steer_tire_model']) : null;
$steer_tire_size = isset($_POST['steer_tire_size']) ? trim($_POST['steer_tire_size']) : null;
$drive_tire_make = isset($_POST['drive_tire_make']) ? trim($_POST['drive_tire_make']) : null;
$drive_tire_model = isset($_POST['drive_tire_model']) ? trim($_POST['drive_tire_model']) : null;
$drive_tire_size = isset($_POST['drive_tire_size']) ? trim($_POST['drive_tire_size']) : null;

if (!$equipment_id) {
    echo json_encode(['success' => false, 'error' => 'Missing equipment_id']);
    exit;
}

$stmt = $conn->prepare('INSERT INTO tire_info (equipment_id, steer_tire_make, steer_tire_model, steer_tire_size, drive_tire_make, drive_tire_model, drive_tire_size) VALUES (?, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param('issssss', $equipment_id, $steer_tire_make, $steer_tire_model, $steer_tire_size, $drive_tire_make, $drive_tire_model, $drive_tire_size);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}

