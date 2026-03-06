<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : null;

if (!$item_id) {
    echo json_encode(['success' => false, 'message' => 'Item ID is required']);
    exit;
}

// Check if table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'Engineering_materials'");
if ($tableCheck->num_rows === 0) {
    echo json_encode(['success' => true, 'materials' => []]);
    exit;
}

$stmt = $conn->prepare("SELECT id, number, name, created_at FROM Engineering_materials WHERE item_id = ? ORDER BY number ASC");
$stmt->bind_param('i', $item_id);
$stmt->execute();
$result = $stmt->get_result();

$materials = [];
while ($row = $result->fetch_assoc()) {
    $materials[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'materials' => $materials]);
