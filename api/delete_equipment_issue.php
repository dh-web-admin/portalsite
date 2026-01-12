<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';
header('Content-Type: application/json');

// perform a targeted permission check so we can return helpful diagnostics for non-admin editors
if (empty($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$role = null;
if (function_exists('get_current_role')) $role = get_current_role();
$canEdit = false;
if (function_exists('can_edit_page')) $canEdit = can_edit_page('equipments');

if (!$canEdit) {
    http_response_code(403);
    $ovr = null;
    if (!empty($_SESSION['email']) && function_exists('get_user_page_override')) {
        $ovr = get_user_page_override($_SESSION['email'], 'equipments');
    }
    echo json_encode(['success' => false, 'message' => 'Forbidden', 'debug' => ['role' => $role, 'email' => $_SESSION['email'] ?? null, 'override' => $ovr]]);
    exit();
}

// Validate and collect POST data
$issue_id = isset($_POST['issue_id']) ? (int)$_POST['issue_id'] : 0;
if ($issue_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid issue ID.']);
    exit();
}

// Determine equipment_id for this issue, then delete the issue record
$equipmentId = null;
$g = $conn->prepare('SELECT equipment_id FROM equipment_history WHERE id = ? LIMIT 1');
if ($g) {
    $g->bind_param('i', $issue_id);
    $g->execute();
    $resg = $g->get_result();
    if ($resg && ($rg = $resg->fetch_assoc())) {
        $equipmentId = isset($rg['equipment_id']) ? (int)$rg['equipment_id'] : null;
    }
    $g->close();
}

$stmt = $conn->prepare('DELETE FROM equipment_history WHERE id = ?');
$stmt->bind_param('i', $issue_id);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete issue.']);
    exit();
}

// After deletion, recompute equipment.operating_condition if we found the equipment id
if (!empty($equipmentId)) {
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

