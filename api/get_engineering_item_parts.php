<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

try {
    $itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
    
    if ($itemId === 0) {
        echo json_encode(['success' => false, 'message' => 'Missing item_id']);
        exit();
    }
    
    // Fetch parts with all makes and supplier info
    $stmt = $conn->prepare("
        SELECT eip.part_name, eip.nsn_number, eip.quantity, eip.notes, 
               eps.make, eps.model, eps.other_numbers, eps.make_lnk,
               eps.supplier, eps.supplier_name, eps.supplier_number, 
               eps.supplier_email, eps.supplier_address, eps.supplier_part_number, eps.supplier_price, eps.supplier_lnk
        FROM engineering_item_parts eip
        LEFT JOIN engineering_part_specifications eps ON eip.part_name = eps.part_name
        WHERE eip.item_id = ?
        ORDER BY eip.part_name, eps.make
    ");
    $stmt->bind_param('i', $itemId);
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
