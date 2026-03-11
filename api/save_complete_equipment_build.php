<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (empty($data['equipment_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Equipment name is required']);
    exit;
}

$equipment_name = trim($data['equipment_name']);
$equipment_number = trim($data['equipment_number'] ?? '');
$equipment_type = trim($data['equipment_type'] ?? '');
$email = $_SESSION['email'];
$draft_id = isset($data['draft_id']) ? intval($data['draft_id']) : null;

// If draft_id is provided, update existing draft. Otherwise create new.
if ($draft_id) {
    // Update existing draft
    $stmt = $conn->prepare('UPDATE draft_equipment SET equipment_name = ?, equipment_number = ?, equipment_type = ?, updated_at = NOW() WHERE id = ? AND created_by = ?');
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    $stmt->bind_param('sssii', $equipment_name, $equipment_number, $equipment_type, $draft_id, $email);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update draft']);
        $stmt->close();
        exit;
    }
    $stmt->close();
    $equipment_id = $draft_id;
} else {
    // Create new draft
    $stmt = $conn->prepare('INSERT INTO draft_equipment (equipment_name, equipment_number, equipment_type, created_by) VALUES (?, ?, ?, ?)');
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    $stmt->bind_param('ssss', $equipment_name, $equipment_number, $equipment_type, $email);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create draft']);
        $stmt->close();
        exit;
    }
    $equipment_id = $stmt->insert_id;
    $stmt->close();
}

// Save selected parts if provided
if (!empty($data['parts']) && is_array($data['parts'])) {
    // Clear existing parts for this draft
    $stmt = $conn->prepare('DELETE FROM draft_equipment_parts WHERE draft_equipment_id = ?');
    $stmt->bind_param('i', $equipment_id);
    $stmt->execute();
    $stmt->close();

    // Insert new parts
    $stmt = $conn->prepare('INSERT INTO draft_equipment_parts (draft_equipment_id, item_id, part_name, nsn_number, make, supplier_name, quantity) VALUES (?, ?, ?, ?, ?, ?, ?)');
    
    foreach ($data['parts'] as $part) {
        $item_id = isset($part['item_id']) ? intval($part['item_id']) : null;
        $part_name = $part['part_name'] ?? '';
        $nsn_number = $part['nsn_number'] ?? '';
        $make = $part['make'] ?? '';
        $supplier_name = $part['supplier_name'] ?? '';
        $quantity = isset($part['quantity']) ? intval($part['quantity']) : 1;
        
        $stmt->bind_param('iissssi', $equipment_id, $item_id, $part_name, $nsn_number, $make, $supplier_name, $quantity);
        $stmt->execute();
    }
    $stmt->close();
}

// Save selected drawings if provided
if (!empty($data['drawings']) && is_array($data['drawings'])) {
    // Clear existing drawings for this draft
    $stmt = $conn->prepare('DELETE FROM draft_equipment_drawings WHERE draft_equipment_id = ?');
    $stmt->bind_param('i', $equipment_id);
    $stmt->execute();
    $stmt->close();

    // Insert new drawings
    $stmt = $conn->prepare('INSERT INTO draft_equipment_drawings (draft_equipment_id, drawing_id, item_id) VALUES (?, ?, ?)');
    
    foreach ($data['drawings'] as $drawing) {
        $drawing_id = intval($drawing['drawing_id'] ?? 0);
        $item_id = isset($drawing['item_id']) ? intval($drawing['item_id']) : null;
        $stmt->bind_param('iii', $equipment_id, $drawing_id, $item_id);
        $stmt->execute();
    }
    $stmt->close();
}

// Save selected materials if provided
if (!empty($data['materials']) && is_array($data['materials'])) {
    // Clear existing materials for this draft
    $stmt = $conn->prepare('DELETE FROM draft_equipment_materials WHERE draft_equipment_id = ?');
    $stmt->bind_param('i', $equipment_id);
    $stmt->execute();
    $stmt->close();

    // Insert new materials
    $stmt = $conn->prepare('INSERT INTO draft_equipment_materials (draft_equipment_id, material_id, item_id) VALUES (?, ?, ?)');
    
    foreach ($data['materials'] as $material) {
        $material_id = intval($material['material_id'] ?? 0);
        $item_id = isset($material['item_id']) ? intval($material['item_id']) : null;
        $stmt->bind_param('iii', $equipment_id, $material_id, $item_id);
        $stmt->execute();
    }
    $stmt->close();
}

echo json_encode(['success' => true, 'equipment_id' => $equipment_id]);
?>
