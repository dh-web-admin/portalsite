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
$part_id = isset($data['id']) ? (int)$data['id'] : 0;
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

if ($part_id <= 0 || empty($name) || $number === '') {
    echo json_encode(['success' => false, 'message' => 'Part record, part ID, and name are required']);
    exit;
}

try {
    // First, get the old part info before updating (to sync with Parts and Suppliers)
    $stmtOld = $conn->prepare("SELECT emp.name, emp.make, em.item_id 
                               FROM Engineering_material_parts emp 
                               JOIN Engineering_materials em ON emp.material_id = em.id 
                               WHERE emp.id = ?");
    $stmtOld->bind_param('i', $part_id);
    $stmtOld->execute();
    $resultOld = $stmtOld->get_result();
    $oldPart = $resultOld->fetch_assoc();
    $stmtOld->close();
    
    $old_name = $oldPart ? $oldPart['name'] : null;
    $old_make = $oldPart ? $oldPart['make'] : null;
    $item_id = $oldPart ? $oldPart['item_id'] : null;

    $stmtCheckNumber = $conn->prepare("SELECT id FROM Engineering_material_parts WHERE number = ? AND id <> ? LIMIT 1");
    $stmtCheckNumber->bind_param('si', $number, $part_id);
    $stmtCheckNumber->execute();
    $resultCheckNumber = $stmtCheckNumber->get_result();
    $numberExists = $resultCheckNumber ? $resultCheckNumber->fetch_assoc() : null;
    $stmtCheckNumber->close();

    if ($numberExists) {
        echo json_encode(['success' => false, 'message' => 'Part ID already exists. Please use a unique part ID.']);
        exit;
    }
    
    // Update the material part
    $stmt = $conn->prepare("UPDATE Engineering_material_parts 
        SET number = ?, name = ?, make = ?, part_number = ?, material_type = ?, 
            thickness = ?, length = ?, width = ?, area = ?, quantity = ?
        WHERE id = ?");
    
    $stmt->bind_param('ssssssssssi', 
        $number,
        $name,
        $make,
        $part_number,
        $material_type,
        $thickness,
        $length,
        $width,
        $area,
        $quantity,
        $part_id
    );
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Sync with Parts and Suppliers section
        if ($old_name && $item_id) {
            // Check if part exists in engineering_item_parts
            $stmtCheck = $conn->prepare("SELECT COUNT(*) as count FROM engineering_item_parts WHERE item_id = ? AND part_name = ?");
            $stmtCheck->bind_param('is', $item_id, $old_name);
            $stmtCheck->execute();
            $resultCheck = $stmtCheck->get_result();
            $rowCheck = $resultCheck->fetch_assoc();
            $exists = $rowCheck['count'] > 0;
            $stmtCheck->close();
            
            if ($exists) {
                // Update existing part name
                $stmtSync = $conn->prepare("UPDATE engineering_item_parts SET part_name = ? WHERE item_id = ? AND part_name = ?");
                $stmtSync->bind_param('sis', $name, $item_id, $old_name);
                $stmtSync->execute();
                $stmtSync->close();
            } else {
                // Insert new part if it doesn't exist
                $stmtSync = $conn->prepare("INSERT INTO engineering_item_parts (item_id, part_name, nsn_number, quantity, notes) VALUES (?, ?, '', NULL, '')");
                $stmtSync->bind_param('is', $item_id, $name);
                $stmtSync->execute();
                $stmtSync->close();
            }
            
            // Handle make in engineering_part_specifications
            if (!empty($make)) {
                if ($old_make) {
                    // Check if old make entry exists
                    $stmtCheckSpec = $conn->prepare("SELECT COUNT(*) as count FROM engineering_part_specifications WHERE part_name = ? AND make = ?");
                    $stmtCheckSpec->bind_param('ss', $old_name, $old_make);
                    $stmtCheckSpec->execute();
                    $resultCheckSpec = $stmtCheckSpec->get_result();
                    $rowCheckSpec = $resultCheckSpec->fetch_assoc();
                    $specExists = $rowCheckSpec['count'] > 0;
                    $stmtCheckSpec->close();
                    
                    if ($specExists) {
                        // Update existing make entry
                        $stmtSpec = $conn->prepare("UPDATE engineering_part_specifications SET part_name = ?, make = ? WHERE part_name = ? AND make = ?");
                        $stmtSpec->bind_param('ssss', $name, $make, $old_name, $old_make);
                        $stmtSpec->execute();
                        $stmtSpec->close();
                    } else {
                        // Insert new make entry (or keep existing tuple when already present)
                        $stmtSpec = $conn->prepare("INSERT INTO engineering_part_specifications (part_name, make, model, other_numbers, supplier, supplier_name, supplier_number, supplier_email, supplier_address, supplier_price) VALUES (?, ?, '', '', '', '', '', '', '', NULL) ON DUPLICATE KEY UPDATE make = VALUES(make)");
                        $stmtSpec->bind_param('ss', $name, $make);
                        $stmtSpec->execute();
                        $stmtSpec->close();
                    }
                } else {
                    // Insert new make entry if old make was empty (duplicate-safe)
                    $stmtSpec = $conn->prepare("INSERT INTO engineering_part_specifications (part_name, make, model, other_numbers, supplier, supplier_name, supplier_number, supplier_email, supplier_address, supplier_price) VALUES (?, ?, '', '', '', '', '', '', '', NULL) ON DUPLICATE KEY UPDATE make = VALUES(make)");
                    $stmtSpec->bind_param('ss', $name, $make);
                    $stmtSpec->execute();
                    $stmtSpec->close();
                }
            }

        }
        
        echo json_encode(['success' => true, 'message' => 'Part updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update part: ' . $stmt->error]);
        $stmt->close();
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
