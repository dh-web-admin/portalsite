<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

// Require edit permission for project checklist
require_edit_api('project_checklist');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || !isset($data['order']) || !is_array($data['order'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$items = $data['order'];
if (count($items) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Empty order']);
    exit;
}

// Validate and extract ids in order
$ids = [];
$seen = [];
foreach ($items as $it) {
    if (!is_array($it) || !isset($it['project_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Malformed order item']);
        exit;
    }
    $pid = intval($it['project_id']);
    if ($pid <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid project id']);
        exit;
    }
    if (isset($seen[$pid])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Duplicate project id in payload']);
        exit;
    }
    $seen[$pid] = true;
    $ids[] = $pid;
}

if (count($ids) > 1000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Too many items']);
    exit;
}

// Verify all IDs exist in Projects
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));
$checkSql = "SELECT Project_ID FROM Projects WHERE Project_ID IN ($placeholders)";
$stmt = $conn->prepare($checkSql);
if (!$stmt) {
    error_log('save_project_order prepare failed: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}
$bind_names = [];
$bind_names[] = $types;
for ($i = 0; $i < count($ids); $i++) {
    $bind_names[] = &$ids[$i];
}
call_user_func_array([$stmt, 'bind_param'], $bind_names);
if (!$stmt->execute()) {
    error_log('save_project_order execute failed: ' . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}
$res = $stmt->get_result();
$found = [];
while ($r = $res->fetch_assoc()) $found[] = intval($r['Project_ID']);
$stmt->close();

$found_sorted = $found;
$ids_sorted = $ids;
sort($found_sorted);
sort($ids_sorted);
if ($found_sorted !== $ids_sorted) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'One or more project IDs do not exist']);
    exit;
}

// Load current global order
$orderRes = $conn->query('SELECT Project_ID FROM Projects ORDER BY Display_Order ASC, Project_ID DESC');
if (!$orderRes) {
    error_log('save_project_order select failed: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}
$global = [];
while ($r = $orderRes->fetch_assoc()) $global[] = intval($r['Project_ID']);

// Build original index map
$origIndex = [];
foreach ($global as $i => $pid) $origIndex[$pid] = $i;

// Remove visible ids from global
$visibleSet = array_flip($ids);
$remaining = array_values(array_filter($global, function($v) use ($visibleSet) { return !isset($visibleSet[$v]); }));

// Determine insertion index: find smallest original index among visible ids
$smallest = null;
foreach ($ids as $pid) {
    if (isset($origIndex[$pid])) {
        $idx = $origIndex[$pid];
        if ($smallest === null || $idx < $smallest) $smallest = $idx;
    }
}

if ($smallest === null) {
    // none found in original (unlikely) -> append
    $insertAt = count($remaining);
} else {
    // insertion index is count of remaining items whose original index < smallest
    $insertAt = 0;
    foreach ($remaining as $v) {
        if (isset($origIndex[$v]) && $origIndex[$v] < $smallest) $insertAt++; else break;
    }
}

// Insert the ids at insertAt preserving order
array_splice($remaining, $insertAt, 0, $ids);

// Now write back sequential Display_Order values in a transaction
try {
    $conn->begin_transaction();
    $update = $conn->prepare('UPDATE Projects SET Display_Order = ? WHERE Project_ID = ?');
    if (!$update) throw new Exception('Prepare failed: ' . $conn->error);
    $pos = 0;
    foreach ($remaining as $pid) {
        $update->bind_param('ii', $pos, $pid);
        if (!$update->execute()) throw new Exception('Update failed: ' . $update->error);
        $pos++;
    }
    $update->close();
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Project order saved.']);
    exit;
} catch (Exception $e) {
    error_log('save_project_order exception: ' . $e->getMessage());
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save project order.']);
    exit;
}

?>
