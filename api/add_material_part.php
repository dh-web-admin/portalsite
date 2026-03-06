<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../partials/permissions.php';
if (!can_edit_page('engineering')) {
    echo json_encode(['success' => false, 'message' => 'No permission to edit']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$material_id = isset($data['material_id']) ? (int)$data['material_id'] : 0;
$name = isset($data['name']) ? trim($data['name']) : '';
$make = isset($data['make']) ? trim($data['make']) : '';
$part_number = isset($data['part_number']) ? trim($data['part_number']) : '';
$material_type = isset($data['material_type']) ? trim($data['material_type']) : '';
$thickness = isset($data['thickness']) ? trim($data['thickness']) : '';
$length = isset($data['length']) ? trim($data['length']) : '';
$width = isset($data['width']) ? trim($data['width']) : '';
$area = isset($data['area']) ? trim($data['area']) : '';
$quantity = isset($data['quantity']) ? trim($data['quantity']) : '';

if ($material_id <= 0 || empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Material ID and name are required']);
    exit;
}

try {
    // Create table if not exists
    $createTable = "CREATE TABLE IF NOT EXISTS Engineering_material_parts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        material_id INT NOT NULL,
        number VARCHAR(20) NOT NULL,
        name VARCHAR(255) NOT NULL,
        make VARCHAR(255),
        part_number VARCHAR(255),
        material_type VARCHAR(100),
        thickness VARCHAR(50),
        length VARCHAR(50),
        width VARCHAR(50),
        area VARCHAR(50),
        quantity VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (material_id)
    ) ENGINE=InnoDB";
    
    if (!$conn->query($createTable)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create table: ' . $conn->error]);
        exit;
    }
    
    // Get material number to generate part number
    $stmt = $conn->prepare("SELECT number, item_id FROM Engineering_materials WHERE id = ?");
    $stmt->bind_param('i', $material_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $material = $result->fetch_assoc();
    $stmt->close();
    
    if (!$material) {
        echo json_encode(['success' => false, 'message' => 'Material not found']);
        exit;
    }
    
    $material_number = $material['number'];
    $item_id = $material['item_id'];
    
    // Get the next letter suffix for this material
    $stmt = $conn->prepare("SELECT number FROM Engineering_material_parts WHERE material_id = ? ORDER BY number DESC LIMIT 1");
    $stmt->bind_param('i', $material_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lastPart = $result->fetch_assoc();
    $stmt->close();
    
    if ($lastPart) {
        // Extract the letter suffix and increment
        $lastNumber = $lastPart['number'];
        // Format should be like "1a", "1b", etc.
        preg_match('/(\d+)([a-z]+)$/', $lastNumber, $matches);
        if (count($matches) === 3) {
            $nextSuffix = chr(ord($matches[2]) + 1); // Increment letter
        } else {
            $nextSuffix = 'a'; // Default to 'a' if pattern doesn't match
        }
    } else {
        $nextSuffix = 'a'; // First part for this material
    }
    
    $part_number_generated = $material_number . $nextSuffix;
    
    // Insert the new part
    $stmt = $conn->prepare("INSERT INTO Engineering_material_parts 
        (material_id, number, name, make, part_number, material_type, thickness, length, width, area, quantity) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param('issssssssss', 
        $material_id,
        $part_number_generated,
        $name,
        $make,
        $part_number,
        $material_type,
        $thickness,
        $length,
        $width,
        $area,
        $quantity
    );
    
    if ($stmt->execute()) {
        $part_id = $conn->insert_id;
        
        echo json_encode([
            'success' => true,
            'message' => 'Part added successfully',
            'part_id' => $part_id,
            'number' => $part_number_generated
        ]);
        
        $stmt->close();
        
        // Also add this part to the Parts and Suppliers section (engineering_item_parts)
        // Add part name to engineering_item_parts
        $stmtParts = $conn->prepare("INSERT INTO engineering_item_parts (item_id, part_name, nsn_number, quantity, notes) VALUES (?, ?, '', NULL, '')");
        $stmtParts->bind_param('is', $item_id, $name);
        $stmtParts->execute();
        $stmtParts->close();
        
        // If make is provided, also add it to engineering_part_specifications
        if (!empty($make)) {
            $stmtSpec = $conn->prepare("INSERT INTO engineering_part_specifications (part_name, make, model, other_numbers, supplier, supplier_name, supplier_number, supplier_email, supplier_address, supplier_price) VALUES (?, ?, '', '', '', '', '', '', '', NULL)");
            $stmtSpec->bind_param('ss', $name, $make);
            $stmtSpec->execute();
            $stmtSpec->close();
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add part: ' . $stmt->error]);
        $stmt->close();
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
