<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

// Get POST data
$equipment_id = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
$filter_name = isset($_POST['filter_name']) ? trim($_POST['filter_name']) : '';
$filter_date = isset($_POST['filter_date']) ? $_POST['filter_date'] : null;
$hours = isset($_POST['hours']) ? $_POST['hours'] : null;
$part_number = isset($_POST['part_number']) ? trim($_POST['part_number']) : null;
$make = isset($_POST['make']) ? trim($_POST['make']) : null;

if (!$equipment_id || !$filter_name) {
    echo json_encode(['success' => false, 'error' => 'Missing equipment_id or filter_name']);
    exit;
}

// Insert into filter_info (let filter_id auto-increment)
$stmt = $conn->prepare('INSERT INTO filter_info (equipment_id, filter_name, filter_date, hours, part_number, make) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->bind_param('isssss', $equipment_id, $filter_name, $filter_date, $hours, $part_number, $make);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}
?>
