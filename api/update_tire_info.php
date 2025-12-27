<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$tire_id = isset($_POST['tire_id']) ? (int)$_POST['tire_id'] : 0;
$steer_tire_make = isset($_POST['steer_tire_make']) ? trim($_POST['steer_tire_make']) : null;
$steer_tire_model = isset($_POST['steer_tire_model']) ? trim($_POST['steer_tire_model']) : null;
$steer_tire_size = isset($_POST['steer_tire_size']) ? trim($_POST['steer_tire_size']) : null;
$drive_tire_make = isset($_POST['drive_tire_make']) ? trim($_POST['drive_tire_make']) : null;
$drive_tire_model = isset($_POST['drive_tire_model']) ? trim($_POST['drive_tire_model']) : null;
$drive_tire_size = isset($_POST['drive_tire_size']) ? trim($_POST['drive_tire_size']) : null;

if (!$tire_id) {
    echo json_encode(['success' => false, 'error' => 'Missing tire_id']);
    exit;
}

$stmt = $conn->prepare('UPDATE tire_info SET steer_tire_make = ?, steer_tire_model = ?, steer_tire_size = ?, drive_tire_make = ?, drive_tire_model = ?, drive_tire_size = ? WHERE tire_id = ?');
$stmt->bind_param('ssssssi', $steer_tire_make, $steer_tire_model, $steer_tire_size, $drive_tire_make, $drive_tire_model, $drive_tire_size, $tire_id);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}