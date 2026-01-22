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
// Optional override: when creating an issue (or edited copy), caller may provide
// a `condition_after_repair` value indicating the equipment-level state after repair.
$condition_after_repair = isset($_POST['condition_after_repair']) ? trim((string)$_POST['condition_after_repair']) : '';

// Detect whether DB has the column to persist
$afterColRes = $conn->query("SHOW COLUMNS FROM equipment_history LIKE 'condition_after_repair'");
$afterColExists = ($afterColRes && $afterColRes->num_rows > 0);

// Insert into equipment_history with all fields
$is_edited_copy = isset($_POST['is_edited_copy']) ? (int)$_POST['is_edited_copy'] : 0;
$original_issue_id = isset($_POST['original_issue_id']) && $is_edited_copy ? (int)$_POST['original_issue_id'] : null;
    if ($afterColExists) {
        $stmt = $conn->prepare('INSERT INTO equipment_history (equipment_id, date_reported, reported_issues, reported_by, equipment_location, operating_condition, condition_after_repair, mechanic_diagnosis, equipment_hours_at_repair, date_repaired, repair_mechanic, parts_fixed, pictures, is_edited_copy, original_issue_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ""), ?, ?, ?, ?, ?, ?)');
    } else {
        $stmt = $conn->prepare('INSERT INTO equipment_history (equipment_id, date_reported, reported_issues, reported_by, equipment_location, operating_condition, mechanic_diagnosis, equipment_hours_at_repair, date_repaired, repair_mechanic, parts_fixed, pictures, is_edited_copy, original_issue_id) VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, ""), ?, ?, ?, ?, ?, ?)');
    }
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Prepare failed.']);
    exit();
}

$bindVars = [$equipment_id, $date_reported, $reported_issues, $reported_by, $equipment_location, $operating_condition];
if ($afterColExists) $bindVars[] = $condition_after_repair;
$bindVars = array_merge($bindVars, [$mechanic_diagnosis, $equipment_hours_at_repair, $date_repaired, $repair_mechanic, $parts_fixed, $pictures, $is_edited_copy, $original_issue_id]);

// Build types string dynamically
$types = '';
foreach ($bindVars as $bv) {
    if (is_int($bv)) $types .= 'i'; else $types .= 's';
}

// bind using call_user_func_array with references
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

// BEGIN FIX: compute equipmentWorst per RULE
// Effective condition per row: IF TRIM(IFNULL(condition_after_repair,'')) <> '' THEN condition_after_repair ELSE operating_condition
// Compute severity from effective condition with mapping: red/inoperable=3, yellow/minor=2, green/fully=1, else 0
// Exclude superseded originals: NOT EXISTS (SELECT 1 FROM equipment_history eh2 WHERE eh2.original_issue_id = eh.id)
if ($equipment_id > 0) {
    if ($afterColExists) {
        // Use subquery to compute effective condition per row, then map to severity and take MAX
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
        // condition_after_repair not present: effective condition == operating_condition
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
        $stmtWorst->bind_param('i', $equipment_id);
        $stmtWorst->execute();
        $resWorst = $stmtWorst->get_result();
        if ($resWorst && ($rw = $resWorst->fetch_assoc())) {
            $equipmentWorst = (int)($rw['equipmentWorst'] ?? 0);
        }
        $stmtWorst->close();
    }

    // Map numeric worst to canonical string (red/yellow/green) or empty when 0
    $final = '';
    if ($equipmentWorst === 3) $final = 'red';
    elseif ($equipmentWorst === 2) $final = 'yellow';
    elseif ($equipmentWorst === 1) $final = 'green';

    // Update equipments.operating_condition based solely on computed equipmentWorst
    $stmtUpd = $conn->prepare('UPDATE equipments SET operating_condition = ? WHERE equipment_id = ?');
    if ($stmtUpd) {
        $stmtUpd->bind_param('si', $final, $equipment_id);
        $stmtUpd->execute();
        $stmtUpd->close();
    }

    // Debug output when requested (non-sensitive)
    if (!empty($_GET['debug'])) {
        $debugInfo = ['equipment_id' => $equipment_id, 'equipmentWorst' => $equipmentWorst, 'final' => $final];
    }
}
// END FIX

$resp = ['success' => true];
if (!empty($debugInfo) && !empty($_GET['debug'])) {
    $resp['debug'] = $debugInfo;
}
echo json_encode($resp);
