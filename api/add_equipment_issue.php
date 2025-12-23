<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

// Validate and collect POST data
$equipment_id = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
$date_reported = $_POST['date_reported'] ?? '';
$reported_issues = $_POST['reported_issues'] ?? '';
$reported_by = $_POST['reported_by'] ?? '';
$equipment_location = $_POST['equipment_location'] ?? '';
$operating_condition = $_POST['operating_condition'] ?? '';

if ($equipment_id <= 0 || !$date_reported || !$reported_issues || !$reported_by || !$equipment_location || !$operating_condition) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit();
}

// Insert into equipment_history
$is_edited_copy = isset($_POST['is_edited_copy']) ? (int)$_POST['is_edited_copy'] : 0;
$stmt = $conn->prepare('INSERT INTO equipment_history (equipment_id, date_reported, reported_issues, reported_by, equipment_location, operating_condition, is_edited_copy) VALUES (?, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param('isssssi', $equipment_id, $date_reported, $reported_issues, $reported_by, $equipment_location, $operating_condition, $is_edited_copy);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save issue.']);
    exit();
}

// Optionally update main equipment table's operating_condition
$stmt2 = $conn->prepare('UPDATE equipments SET operating_condition = ? WHERE equipment_id = ?');
$stmt2->bind_param('si', $operating_condition, $equipment_id);
$stmt2->execute();
$stmt2->close();

echo json_encode(['success' => true]);
