<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

try {
    $equipmentId = isset($_GET['equipment_id']) ? (int)$_GET['equipment_id'] : 0;
    
    if ($equipmentId === 0) {
        echo json_encode(['success' => false, 'message' => 'Missing equipment_id']);
        exit();
    }
    
    // Fetch parts with all makes and supplier info
    $stmt = $conn->prepare("
        SELECT ep.part_name, ep.nsn_number, ep.quantity, ep.notes, 
               ps.make, ps.model, ps.other_numbers, 
               ps.supplier, ps.supplier_name, ps.supplier_number, 
               ps.supplier_email, ps.supplier_address, ps.supplier_price
        FROM equipment_parts ep
        LEFT JOIN part_specifications ps ON ep.part_name = ps.part_name
        WHERE ep.equipment_id = ?
        ORDER BY ep.part_name, ps.make
    ");
    $stmt->bind_param('i', $equipmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $parts = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $parts[] = $row;
        }
    }
    $stmt->close();
    
    echo json_encode(['success' => true, 'parts' => $parts]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
