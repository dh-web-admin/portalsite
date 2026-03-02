<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['item_id']) || !is_numeric($_GET['item_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
    exit();
}

$itemId = intval($_GET['item_id']);

try {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'engineering_drawings'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        echo json_encode(['success' => true, 'drawings' => []]);
        exit();
    }

    $stmt = $conn->prepare("SELECT id, item_id, file_url, filename, version, uploaded_at, uploaded_by FROM engineering_drawings WHERE item_id = ? ORDER BY CAST(SUBSTRING(version, 2) AS UNSIGNED) DESC, uploaded_at DESC, id DESC");
    $stmt->bind_param('i', $itemId);
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
