<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$material_id = isset($_GET['material_id']) ? (int)$_GET['material_id'] : 0;

if ($material_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Material ID is required']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT emp.*, eip_one.id AS engineering_part_id
                            FROM Engineering_material_parts emp
                            JOIN Engineering_materials em ON emp.material_id = em.id
                            LEFT JOIN (
                                SELECT item_id, part_name, MIN(id) AS id
                                FROM engineering_item_parts
                                GROUP BY item_id, part_name
                            ) eip_one
                            ON eip_one.item_id = em.item_id
                            AND eip_one.part_name COLLATE utf8mb4_unicode_ci = emp.name COLLATE utf8mb4_unicode_ci
                            WHERE emp.material_id = ?
                            ORDER BY emp.number ASC");
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
