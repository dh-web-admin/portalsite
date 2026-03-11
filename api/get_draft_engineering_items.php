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
if (!$tableCheck || $tableCheck->num_rows === 0) {
    echo json_encode(['success' => true, 'item_ids' => []]);
    exit();
}

$stmt = $conn->prepare('SELECT engineering_item_id FROM engineering_draft_items WHERE draft_id = ?');
$stmt->bind_param('i', $draft_id);
$stmt->execute();
$result = $stmt->get_result();
$item_ids = [];
while ($row = $result->fetch_assoc()) {
    $item_ids[] = $row['engineering_item_id'];
}
$stmt->close();

echo json_encode(['success' => true, 'item_ids' => $item_ids]);
