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
// Optional override: condition to set on the equipment after repair (separate from the original issue's operating_condition)
$condition_after_repair = isset($_POST['condition_after_repair']) ? trim((string)$_POST['condition_after_repair']) : '';

// Detect whether DB has the column to persist
$afterColRes = $conn->query("SHOW COLUMNS FROM equipment_history LIKE 'condition_after_repair'");
$afterColExists = ($afterColRes && $afterColRes->num_rows > 0);

// Update the issue record
$sqlUpdate = 'UPDATE equipment_history SET mechanic_diagnosis = ?, date_repaired = ?, repair_mechanic = ?, parts_fixed = ?, pictures = ?, operating_condition = ?';
if ($afterColExists) {
    $sqlUpdate .= ', condition_after_repair = ?';
}
$sqlUpdate .= ', equipment_hours_at_repair = NULLIF(?, "") WHERE id = ?';

$stmt = $conn->prepare($sqlUpdate);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Prepare failed.']);
    exit();
}

// build bind params array
$bindVars = [$mechanic_diagnosis, $date_repaired, $repair_mechanic, $parts_fixed, $pictures, $operating_condition];
if ($afterColExists) $bindVars[] = $condition_after_repair;
$bindVars = array_merge($bindVars, [$equipment_hours_at_repair, $issue_id]);

// Build types string and bind dynamically
$types = '';
foreach ($bindVars as $bv) {
    if (is_int($bv)) $types .= 'i'; else $types .= 's';
}
$params = array_merge([$types], $bindVars);
$refs = [];
foreach ($params as $key => $value) {
    $refs[$key] = &$params[$key];
}
call_user_func_array([$stmt, 'bind_param'], $refs);
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

// BEGIN FIX: compute equipmentWorst per RULE
// Effective condition per row: IF TRIM(IFNULL(condition_after_repair,'')) <> '' THEN condition_after_repair ELSE operating_condition
// Compute severity from effective condition with mapping: red/inoperable=3, yellow/minor=2, green/fully=1, else 0
// Exclude superseded originals: NOT EXISTS (SELECT 1 FROM equipment_history eh2 WHERE eh2.original_issue_id = eh.id)
if ($equipmentId > 0) {
    if ($afterColExists) {
        $sql = "SELECT MAX(
            CASE
                WHEN LOWER(eff) = 'red' OR LOWER(eff) LIKE '%inoperable%' THEN 3
                WHEN LOWER(eff) = 'yellow' OR LOWER(eff) LIKE '%minor%' THEN 2
                WHEN LOWER(eff) = 'green' OR LOWER(eff) LIKE '%fully%' THEN 1
                ELSE 0
            END
        ) AS equipmentWorst FROM (
            SELECT (CASE WHEN TRIM(IFNULL(condition_after_repair,'')) <> '' THEN condition_after_repair ELSE operating_condition END) AS eff, id
            FROM equipment_history eh
            WHERE eh.equipment_id = ? AND NOT EXISTS (SELECT 1 FROM equipment_history eh2 WHERE eh2.original_issue_id = eh.id)
        ) t";
    } else {
        $sql = "SELECT MAX(
            CASE
                WHEN LOWER(operating_condition) = 'red' OR LOWER(operating_condition) LIKE '%inoperable%' THEN 3
                WHEN LOWER(operating_condition) = 'yellow' OR LOWER(operating_condition) LIKE '%minor%' THEN 2
                WHEN LOWER(operating_condition) = 'green' OR LOWER(operating_condition) LIKE '%fully%' THEN 1
                ELSE 0
            END
        ) AS equipmentWorst FROM equipment_history eh WHERE eh.equipment_id = ? AND NOT EXISTS (SELECT 1 FROM equipment_history eh2 WHERE eh2.original_issue_id = eh.id)";
    }

    $equipmentWorst = 0;
    $stmtWorst = $conn->prepare($sql);
    if ($stmtWorst) {
        $stmtWorst->bind_param('i', $equipmentId);
        $stmtWorst->execute();
        $resWorst = $stmtWorst->get_result();
        if ($resWorst && ($rw = $resWorst->fetch_assoc())) {
            $equipmentWorst = (int)($rw['equipmentWorst'] ?? 0);
        }
        $stmtWorst->close();
    }

    $final = '';
    if ($equipmentWorst === 3) $final = 'red';
    elseif ($equipmentWorst === 2) $final = 'yellow';
    elseif ($equipmentWorst === 1) $final = 'green';

    $stmtUpd = $conn->prepare('UPDATE equipments SET operating_condition = ? WHERE equipment_id = ?');
    if ($stmtUpd) {
        $stmtUpd->bind_param('si', $final, $equipmentId);
        $stmtUpd->execute();
        $stmtUpd->close();
    }

    if (!empty($_GET['debug'])) {
        $debugInfo = ['equipment_id' => $equipmentId, 'equipmentWorst' => $equipmentWorst, 'final' => $final];
    }
}
// END FIX

$resp = ['success' => true];
if (!empty($debugInfo) && !empty($_GET['debug'])) {
    $resp['debug'] = $debugInfo;
}
echo json_encode($resp);
