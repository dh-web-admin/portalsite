<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

try {
    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $partNumber = isset($_POST['part_number']) ? trim($_POST['part_number']) : '';
    $nsnNumber = isset($_POST['nsn_number']) ? trim($_POST['nsn_number']) : '';
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $makes = isset($_POST['makes']) ? json_decode($_POST['makes'], true) : [];
    $editMode = isset($_POST['edit_mode']) ? (int)$_POST['edit_mode'] : 0;
    $originalPartName = isset($_POST['original_part_name']) ? trim($_POST['original_part_name']) : '';

    if ($itemId <= 0 || empty($partNumber)) {
        echo json_encode(['success' => false, 'error' => 'Item ID and Part Number are required']);
        exit();
    }

    $conn->begin_transaction();

    // If editing, clear existing records for this item/part to avoid duplicates
    if ($editMode === 1 && $itemId > 0 && $originalPartName !== '') {
        // Remove existing engineering_item_parts mapping for this item + original part name
        $stmt = $conn->prepare("DELETE FROM engineering_item_parts WHERE item_id = ? AND part_name = ?");
        $stmt->bind_param('is', $itemId, $originalPartName);
        if (!$stmt->execute()) {
            throw new Exception('Failed to update part mapping: ' . $stmt->error);
        }
        $stmt->close();

        // Remove existing specifications tied to the original part name
        $stmt = $conn->prepare("DELETE FROM engineering_part_specifications WHERE part_name = ?");
        $stmt->bind_param('s', $originalPartName);
        if (!$stmt->execute()) {
            throw new Exception('Failed to update part specifications: ' . $stmt->error);
        }
        $stmt->close();
    }

    // Insert into engineering_item_parts (new or updated name)
    $stmt = $conn->prepare("INSERT INTO engineering_item_parts (item_id, part_name, nsn_number, quantity, notes) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('issis', $itemId, $partNumber, $nsnNumber, $quantity, $notes);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to add part: ' . $stmt->error);
    }
    $stmt->close();

    // Insert into engineering_part_specifications for each make
    if (!empty($makes) && is_array($makes)) {
        $stmt = $conn->prepare("INSERT INTO engineering_part_specifications (part_name, make, model, other_numbers, supplier, supplier_name, supplier_number, supplier_email, supplier_address, supplier_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE make = VALUES(make), model = VALUES(model), other_numbers = VALUES(other_numbers), supplier = VALUES(supplier), supplier_name = VALUES(supplier_name), supplier_number = VALUES(supplier_number), supplier_email = VALUES(supplier_email), supplier_address = VALUES(supplier_address), supplier_price = VALUES(supplier_price)");
        
        foreach ($makes as $make) {
            if (!empty($make['make']) && !empty($make['partNumber'])) {
                $otherNumbers = isset($make['otherNumbers']) ? trim($make['otherNumbers']) : '';
                $supplier = isset($make['supplier']) ? trim($make['supplier']) : '';
                $supplierName = isset($make['supplierName']) ? trim($make['supplierName']) : '';
                $supplierNumber = isset($make['supplierNumber']) ? trim($make['supplierNumber']) : '';
                $supplierEmail = isset($make['supplierEmail']) ? trim($make['supplierEmail']) : '';
                $supplierAddress = isset($make['supplierAddress']) ? trim($make['supplierAddress']) : '';
                $supplierPrice = null;
                if (array_key_exists('supplierPrice', $make)) {
                    $supplierPrice = $make['supplierPrice'];
                    if (is_string($supplierPrice)) {
                        $supplierPrice = trim($supplierPrice);
                        if ($supplierPrice === '') {
                            $supplierPrice = null;
                        }
                    }
                }
                
                $stmt->bind_param('ssssssssss', $partNumber, $make['make'], $make['partNumber'], $otherNumbers, $supplier, $supplierName, $supplierNumber, $supplierEmail, $supplierAddress, $supplierPrice);
                $stmt->execute();
            }
        }
        $stmt->close();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => $editMode === 1 ? 'Part updated successfully' : 'Part added successfully']);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
