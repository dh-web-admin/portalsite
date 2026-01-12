<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';
header('Content-Type: application/json; charset=utf-8');

require_edit_api('equipments');

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

// Optional fields
$mechanic_diagnosis = $_POST['mechanic_diagnosis'] ?? '';
$date_repaired_raw = $_POST['date_repaired'] ?? '';
$date_repaired = !empty(trim($date_repaired_raw)) ? $date_repaired_raw : null;
$repair_mechanic = $_POST['repair_mechanic'] ?? '';
$parts_fixed = $_POST['parts_fixed'] ?? '';
$pictures = $_POST['pictures'] ?? '';
$equipment_hours_at_repair = null; // New issues should not inherit hours from other records

// Insert into equipment_history with all fields
$is_edited_copy = isset($_POST['is_edited_copy']) ? (int)$_POST['is_edited_copy'] : 0;
$original_issue_id = isset($_POST['original_issue_id']) && $is_edited_copy ? (int)$_POST['original_issue_id'] : null;
$stmt = $conn->prepare('INSERT INTO equipment_history (equipment_id, date_reported, reported_issues, reported_by, equipment_location, operating_condition, mechanic_diagnosis, equipment_hours_at_repair, date_repaired, repair_mechanic, parts_fixed, pictures, is_edited_copy, original_issue_id) VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, ""), ?, ?, ?, ?, ?, ?)');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Prepare failed.']);
    exit();
}

// Defensive check: ensure types string length matches number of variables to bind
$types = 'isssssssssssii';
$bindVars = [
    $equipment_id, $date_reported, $reported_issues, $reported_by, $equipment_location, $operating_condition,
    $mechanic_diagnosis, $equipment_hours_at_repair, $date_repaired, $repair_mechanic, $parts_fixed, $pictures, $is_edited_copy, $original_issue_id
];
if (strlen($types) !== count($bindVars)) {
    http_response_code(500);
    $debug = ['success' => false, 'message' => 'Bind param count mismatch', 'types_len' => strlen($types), 'vars_count' => count($bindVars)];
    // include simple var summaries
    $summaries = [];
    foreach ($bindVars as $i => $v) {
        $summaries[] = ['index' => $i, 'type' => gettype($v), 'value_preview' => is_scalar($v) ? mb_substr((string)$v,0,120) : gettype($v)];
    }
    $debug['vars'] = $summaries;
    echo json_encode($debug);
    exit();
}
$stmt->bind_param($types, $equipment_id, $date_reported, $reported_issues, $reported_by, $equipment_location, $operating_condition, $mechanic_diagnosis, $equipment_hours_at_repair, $date_repaired, $repair_mechanic, $parts_fixed, $pictures, $is_edited_copy, $original_issue_id);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save issue.']);
    exit();
}

// Optionally update main equipment table's location when equipment_location provided
if (!empty($equipment_location) && $equipment_id > 0) {
    $stmt3 = $conn->prepare('UPDATE equipments SET location = ? WHERE equipment_id = ?');
    if ($stmt3) {
        $stmt3->bind_param('si', $equipment_location, $equipment_id);
        $stmt3->execute();
        $stmt3->close();
    }
}

// Ensure equipments.operating_condition reflects the most recent issue,
// but if any unrepaired issue has a more severe condition (red>yellow>green), prefer that.
if ($equipment_id > 0) {
    // fetch latest reported condition
    $latestCondition = '';
    $stmtLatest = $conn->prepare('SELECT operating_condition FROM equipment_history WHERE equipment_id = ? ORDER BY date_reported DESC, id DESC LIMIT 1');
    if ($stmtLatest) {
        $stmtLatest->bind_param('i', $equipment_id);
        $stmtLatest->execute();
        $resLatest = $stmtLatest->get_result();
        if ($resLatest && ($rowLatest = $resLatest->fetch_assoc())) {
            $latestCondition = $rowLatest['operating_condition'] ?? '';
        }
        $stmtLatest->close();
    }

    // compute worst unrepaired severity (3=red,2=yellow,1=green,0=none)
    $worst = 0;
    // Exclude original rows that have a newer edited copy (they are superseded)
    $stmtWorst = $conn->prepare("SELECT MAX(CASE WHEN operating_condition='red' THEN 3 WHEN operating_condition='yellow' THEN 2 WHEN operating_condition='green' THEN 1 ELSE 0 END) AS worst FROM equipment_history eh WHERE eh.equipment_id = ? AND eh.date_repaired IS NULL AND NOT EXISTS (SELECT 1 FROM equipment_history eh2 WHERE eh2.original_issue_id = eh.id)");
    if ($stmtWorst) {
        $stmtWorst->bind_param('i', $equipment_id);
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
        // choose worst severity
        $final = $worst === 3 ? 'red' : ($worst === 2 ? 'yellow' : ($worst === 1 ? 'green' : ''));
    } else {
        $final = $latestCondition;
    }

    if ($final !== null) {
        $stmtUpd = $conn->prepare('UPDATE equipments SET operating_condition = ? WHERE equipment_id = ?');
        if ($stmtUpd) {
            $stmtUpd->bind_param('si', $final, $equipment_id);
            $stmtUpd->execute();
            $stmtUpd->close();
        }
    }
}

echo json_encode(['success' => true]);
