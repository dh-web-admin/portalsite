<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$material_id = isset($_GET['material_id']) ? (int)$_GET['material_id'] : 0;

if ($material_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Material ID is required']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM Engineering_material_parts WHERE material_id = ? ORDER BY number ASC");
    $stmt->bind_param('i', $material_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $parts = [];
    while ($row = $result->fetch_assoc()) {
        $parts[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'parts' => $parts
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
