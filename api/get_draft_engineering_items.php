<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$draft_id = isset($_GET['draft_id']) ? (int)$_GET['draft_id'] : 0;
if (!$draft_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid draft ID']);
    exit();
}

// Validate ownership
$email = $_SESSION['email'];
$stmt = $conn->prepare('SELECT id FROM draft_equipment WHERE id = ? AND created_by = ? LIMIT 1');
$stmt->bind_param('is', $draft_id, $email);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || !$result->fetch_assoc()) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Draft not found or access denied']);
    exit();
}
$stmt->close();

// Table may not exist yet
$tableCheck = $conn->query("SHOW TABLES LIKE 'engineering_draft_items'");
$item_ids = [];
$selection_rows = [];

if ($tableCheck && $tableCheck->num_rows > 0) {
    $stmt = $conn->prepare('SELECT engineering_item_id FROM engineering_draft_items WHERE draft_id = ?');
    $stmt->bind_param('i', $draft_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $item_ids[] = (int)$row['engineering_item_id'];
    }
    $stmt->close();
}

$selectionTableCheck = $conn->query("SHOW TABLES LIKE 'draft_equipment_selections'");
if ($selectionTableCheck && $selectionTableCheck->num_rows > 0) {
    $stmt = $conn->prepare('SELECT item_id, material_id, part_id, version FROM draft_equipment_selections WHERE draft_equipment_id = ? ORDER BY id ASC');
    $stmt->bind_param('i', $draft_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $selection_rows[] = [
            'item_id' => isset($row['item_id']) ? (int)$row['item_id'] : 0,
            'material_id' => isset($row['material_id']) && $row['material_id'] !== null ? (int)$row['material_id'] : null,
            'part_id' => isset($row['part_id']) && $row['part_id'] !== null ? (int)$row['part_id'] : null,
            'version' => isset($row['version']) && $row['version'] !== null ? (string)$row['version'] : null,
        ];
    }
    $stmt->close();
}

if (empty($item_ids) && !empty($selection_rows)) {
    $seenItems = [];
    foreach ($selection_rows as $row) {
        $itemId = isset($row['item_id']) ? (int)$row['item_id'] : 0;
        if ($itemId > 0 && !isset($seenItems[$itemId])) {
            $seenItems[$itemId] = true;
            $item_ids[] = $itemId;
        }
    }
}

echo json_encode(['success' => true, 'item_ids' => $item_ids, 'selection_rows' => $selection_rows]);
