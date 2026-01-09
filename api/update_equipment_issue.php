<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';
header('Content-Type: application/json');

require_edit_api('equipments');

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

// Handle pictures: if the client did not send a `pictures` field at all, preserve the existing pictures value
if (array_key_exists('pictures', $_POST)) {
    $pictures = $_POST['pictures'] ?? '';
} else {
    $pictures = '';
    // fetch current pictures for this issue to preserve them
    $stmtPreserve = $conn->prepare('SELECT pictures FROM equipment_history WHERE id = ? LIMIT 1');
    $stmtPreserve->bind_param('i', $issue_id);
    $stmtPreserve->execute();
    $resPreserve = $stmtPreserve->get_result();
    if ($resPreserve && $rowPreserve = $resPreserve->fetch_assoc()) {
        $pictures = $rowPreserve['pictures'] ?? '';
    }
    $stmtPreserve->close();
}
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

// Optionally update main equipment table's operating_condition when a separate "condition_after_repair" is provided
$condition_after = $_POST['condition_after_repair'] ?? '';
$equipmentId = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
if (!empty($condition_after) && $equipmentId > 0) {
    $stmt2 = $conn->prepare('UPDATE equipments SET operating_condition = ? WHERE equipment_id = ?');
    $stmt2->bind_param('si', $condition_after, $equipmentId);
    $stmt2->execute();
    $stmt2->close();
}

// If an equipment_location was provided in the update request, update the equipments table
if (isset($_POST['equipment_location'])) {
    $newLocation = trim($_POST['equipment_location']);
    if ($newLocation !== '' && $equipmentId > 0) {
        $stmt3 = $conn->prepare('UPDATE equipments SET location = ? WHERE equipment_id = ?');
        $stmt3->bind_param('si', $newLocation, $equipmentId);
        $stmt3->execute();
        $stmt3->close();
    }
}

echo json_encode(['success' => true]);

