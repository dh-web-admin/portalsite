<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

// Validate and collect POST data
$issue_id = isset($_POST['issue_id']) ? (int)$_POST['issue_id'] : 0;
if ($issue_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid issue ID.']);
    exit();
}

$mechanic_diagnosis = $_POST['mechanic_diagnosis'] ?? '';
$date_repaired_raw = $_POST['date_repaired'] ?? '';
$date_repaired = !empty(trim($date_repaired_raw)) ? $date_repaired_raw : null;
$repair_mechanic = $_POST['repair_mechanic'] ?? '';
$parts_fixed = $_POST['parts_fixed'] ?? '';
$pictures = $_POST['pictures'] ?? '';
$operating_condition = $_POST['operating_condition'] ?? '';

// Update the issue record
$stmt = $conn->prepare('UPDATE equipment_history SET mechanic_diagnosis = ?, date_repaired = ?, repair_mechanic = ?, parts_fixed = ?, pictures = ?, operating_condition = ? WHERE id = ?');
$stmt->bind_param('ssssssi', $mechanic_diagnosis, $date_repaired, $repair_mechanic, $parts_fixed, $pictures, $operating_condition, $issue_id);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update issue.']);
    exit();
}

// Optionally update main equipment table's operating_condition
if (!empty($operating_condition)) {
    $equipmentId = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
    if ($equipmentId > 0) {
        $stmt2 = $conn->prepare('UPDATE equipments SET operating_condition = ? WHERE equipment_id = ?');
        $stmt2->bind_param('si', $operating_condition, $equipmentId);
        $stmt2->execute();
        $stmt2->close();
    }
}

echo json_encode(['success' => true]);

