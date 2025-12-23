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

// Optional fields for edited copies (fields below the bar)
$mechanic_diagnosis = $_POST['mechanic_diagnosis'] ?? '';
$date_repaired_raw = $_POST['date_repaired'] ?? '';
$date_repaired = !empty(trim($date_repaired_raw)) ? $date_repaired_raw : null;
$repair_mechanic = $_POST['repair_mechanic'] ?? '';
$parts_fixed = $_POST['parts_fixed'] ?? '';
$pictures = $_POST['pictures'] ?? '';

// Insert into equipment_history with all fields
$is_edited_copy = isset($_POST['is_edited_copy']) ? (int)$_POST['is_edited_copy'] : 0;
$original_issue_id = isset($_POST['original_issue_id']) && $is_edited_copy ? (int)$_POST['original_issue_id'] : null;
$stmt = $conn->prepare('INSERT INTO equipment_history (equipment_id, date_reported, reported_issues, reported_by, equipment_location, operating_condition, mechanic_diagnosis, date_repaired, repair_mechanic, parts_fixed, pictures, is_edited_copy, original_issue_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param('issssssssssii', $equipment_id, $date_reported, $reported_issues, $reported_by, $equipment_location, $operating_condition, $mechanic_diagnosis, $date_repaired, $repair_mechanic, $parts_fixed, $pictures, $is_edited_copy, $original_issue_id);
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
