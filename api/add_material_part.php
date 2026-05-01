<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
ob_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
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
$number = isset($data['number']) ? trim($data['number']) : '';
$name = isset($data['name']) ? trim($data['name']) : '';
$make = isset($data['make']) ? trim($data['make']) : '';
$part_number = isset($data['part_number']) ? trim($data['part_number']) : '';
$material_type = isset($data['material_type']) ? trim($data['material_type']) : '';
$thickness = isset($data['thickness']) ? trim($data['thickness']) : '';
$length = isset($data['length']) ? trim($data['length']) : '';
$width = isset($data['width']) ? trim($data['width']) : '';
$area = isset($data['area']) ? trim($data['area']) : '';
$quantity = isset($data['quantity']) ? trim($data['quantity']) : '';

if ($material_id <= 0 || empty($name) || $number === '') {
    echo json_encode(['success' => false, 'message' => 'Material ID, part ID, and name are required']);
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
    
    // Get material data for sync
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
    
    $item_id = $material['item_id'];

    $stmt = $conn->prepare("SELECT id FROM Engineering_material_parts WHERE material_id = ? AND number = ? LIMIT 1");
    $stmt->bind_param('is', $material_id, $number);
    $stmt->execute();
    $duplicateResult = $stmt->get_result();
    $duplicateRow = $duplicateResult ? $duplicateResult->fetch_assoc() : null;
    $stmt->close();

    if ($duplicateRow) {
        echo json_encode(['success' => false, 'message' => 'Part ID already exists. Please use a unique part ID.']);
        exit;
    }
    
    // Insert the new part
    $stmt = $conn->prepare("INSERT INTO Engineering_material_parts 
        (material_id, number, name, make, part_number, material_type, thickness, length, width, area, quantity) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param('issssssssss', 
        $material_id,
        $number,
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

        $stmt->close();

        // Also add this part to the Parts and Suppliers section (engineering_item_parts)
        // Add part name to engineering_item_parts
        $stmtParts = $conn->prepare("INSERT INTO engineering_item_parts (item_id, part_name, nsn_number, quantity, notes) VALUES (?, ?, '', NULL, '')");
        $stmtParts->bind_param('is', $item_id, $name);
        $stmtParts->execute();
        $engineering_part_id = $conn->insert_id;
        $stmtParts->close();
        
        // If make is provided, sync it to engineering_part_specifications without failing on duplicates.
        if (!empty($make)) {
            $stmtSpec = $conn->prepare("INSERT INTO engineering_part_specifications (part_name, make, model, other_numbers, supplier, supplier_name, supplier_number, supplier_email, supplier_address, supplier_price) VALUES (?, ?, '', '', '', '', '', '', '', NULL) ON DUPLICATE KEY UPDATE make = VALUES(make)");
            $stmtSpec->bind_param('ss', $name, $make);
            $stmtSpec->execute();
            $stmtSpec->close();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Part added successfully',
            'part_id' => $part_id,
            'engineering_part_id' => $engineering_part_id,
            'number' => $number
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add part: ' . $stmt->error]);
        $stmt->close();
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
