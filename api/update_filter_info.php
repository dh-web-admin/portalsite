<?php
// update_filter_info.php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}


$filter_id = isset($_POST['filter_id']) ? intval($_POST['filter_id']) : 0;
$filter_name = isset($_POST['filter_name']) ? trim($_POST['filter_name']) : null;
$filter_date = isset($_POST['filter_date']) ? trim($_POST['filter_date']) : null;
$hours = isset($_POST['hours']) ? trim($_POST['hours']) : null;
$part_number = isset($_POST['part_number']) ? trim($_POST['part_number']) : null;
$make = isset($_POST['make']) ? trim($_POST['make']) : null;

if (!$filter_id) {
    echo json_encode(['success' => false, 'error' => 'Missing filter_id.']);
    exit();
}

// Update filter_name as well
$stmt = $conn->prepare("UPDATE filter_info SET filter_name=?, filter_date=?, hours=?, part_number=?, make=? WHERE filter_id=?");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
    exit();
}
$stmt->bind_param('sssssi', $filter_name, $filter_date, $hours, $part_number, $make, $filter_id);
$success = $stmt->execute();
if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}
$stmt->close();
