<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';
header('Content-Type: application/json; charset=utf-8');

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
$equipment_hours_at_repair = isset($_POST['equipment_hours_at_repair']) ? trim((string)$_POST['equipment_hours_at_repair']) : '';

// Preserve pictures if not provided in this request
if (array_key_exists('pictures', $_POST)) {
    $pictures = $_POST['pictures'] ?? '';
} else {
    $pictures = '';
    $stmtPreserve = $conn->prepare('SELECT pictures FROM equipment_history WHERE id = ? LIMIT 1');
    if ($stmtPreserve) {
        $stmtPreserve->bind_param('i', $issue_id);
        $stmtPreserve->execute();
        $resPreserve = $stmtPreserve->get_result();
        if ($resPreserve && ($rowPreserve = $resPreserve->fetch_assoc())) {
            $pictures = $rowPreserve['pictures'] ?? '';
        }
        $stmtPreserve->close();
    }
}
$operating_condition = $_POST['operating_condition'] ?? '';

// Update the issue record
$stmt = $conn->prepare('UPDATE equipment_history SET mechanic_diagnosis = ?, date_repaired = ?, repair_mechanic = ?, parts_fixed = ?, pictures = ?, operating_condition = ?, equipment_hours_at_repair = NULLIF(?, "") WHERE id = ?');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Prepare failed.']);
    exit();
}
$stmt->bind_param('sssssssi', $mechanic_diagnosis, $date_repaired, $repair_mechanic, $parts_fixed, $pictures, $operating_condition, $equipment_hours_at_repair, $issue_id);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update issue.']);
    exit();
}

// If an equipment_location was provided in the update request, update the equipments table
$equipmentId = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
if (isset($_POST['equipment_location']) && $equipmentId > 0) {
    $newLocation = trim($_POST['equipment_location']);
    if ($newLocation !== '') {
        $stmt3 = $conn->prepare('UPDATE equipments SET location = ? WHERE equipment_id = ?');
        if ($stmt3) {
            $stmt3->bind_param('si', $newLocation, $equipmentId);
            $stmt3->execute();
            $stmt3->close();
        }
    }
}

// After updating an issue, compute equipment operating condition using latest issue
// but prefer any worse unrepaired condition (red>yellow>green).
if ($equipmentId > 0) {
    $latestCondition = '';
    $stmtLatest = $conn->prepare('SELECT operating_condition FROM equipment_history WHERE equipment_id = ? ORDER BY date_reported DESC, id DESC LIMIT 1');
    if ($stmtLatest) {
        $stmtLatest->bind_param('i', $equipmentId);
        $stmtLatest->execute();
        $resLatest = $stmtLatest->get_result();
        if ($resLatest && ($rowLatest = $resLatest->fetch_assoc())) {
            $latestCondition = $rowLatest['operating_condition'] ?? '';
        }
        $stmtLatest->close();
    }

    $worst = 0;
    // Exclude original rows that have a newer edited copy (they are superseded)
    $stmtWorst = $conn->prepare("SELECT MAX(CASE WHEN operating_condition='red' THEN 3 WHEN operating_condition='yellow' THEN 2 WHEN operating_condition='green' THEN 1 ELSE 0 END) AS worst FROM equipment_history eh WHERE eh.equipment_id = ? AND eh.date_repaired IS NULL AND NOT EXISTS (SELECT 1 FROM equipment_history eh2 WHERE eh2.original_issue_id = eh.id)");
    if ($stmtWorst) {
        $stmtWorst->bind_param('i', $equipmentId);
        $stmtWorst->execute();
        $resWorst = $stmtWorst->get_result();
        if ($resWorst && ($r = $resWorst->fetch_assoc())) {
            $worst = (int)($r['worst'] ?? 0);
        }
        $stmtWorst->close();
    }

    $map = ['red' => 3, 'yellow' => 2, 'green' => 1];
    $latestSeverity = isset($map[$latestCondition]) ? $map[$latestCondition] : 0;
    if ($worst > $latestSeverity) {
        $final = $worst === 3 ? 'red' : ($worst === 2 ? 'yellow' : ($worst === 1 ? 'green' : ''));
    } else {
        $final = $latestCondition;
    }

    if ($final !== null) {
        $stmtUpd = $conn->prepare('UPDATE equipments SET operating_condition = ? WHERE equipment_id = ?');
        if ($stmtUpd) {
            $stmtUpd->bind_param('si', $final, $equipmentId);
            $stmtUpd->execute();
            $stmtUpd->close();
        }
    }
}

echo json_encode(['success' => true]);
