<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$itemId = isset($_GET['item_id']) ? (int) $_GET['item_id'] : 0;
$partId = isset($_GET['part_id']) ? (int) $_GET['part_id'] : 0;

if ($itemId <= 0 || $partId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid item or part']);
    exit();
}

try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'engineering_drawings'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        echo json_encode(['success' => true, 'drawings' => []]);
        exit();
    }

    $partColumnCheck = $conn->query("SHOW COLUMNS FROM engineering_drawings LIKE 'part_id'");
    if (!$partColumnCheck || $partColumnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE engineering_drawings ADD COLUMN part_id INT(11) NULL DEFAULT NULL AFTER item_id");
        $conn->query("CREATE INDEX idx_part_id ON engineering_drawings (part_id)");
        $conn->query("CREATE INDEX idx_item_part_version ON engineering_drawings (item_id, part_id, version)");
    }

    $stmt = $conn->prepare('SELECT id, item_id, part_id, file_url, filename, version, uploaded_at, uploaded_by FROM engineering_drawings WHERE item_id = ? AND part_id = ? ORDER BY CAST(SUBSTRING(version, 2) AS UNSIGNED) DESC, uploaded_at DESC, id DESC');
    $stmt->bind_param('ii', $itemId, $partId);
    $stmt->execute();
    $result = $stmt->get_result();

    $drawings = [];
    while ($row = $result->fetch_assoc()) {
        $drawings[] = $row;
    }

    $stmt->close();

    echo json_encode(['success' => true, 'drawings' => $drawings]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}