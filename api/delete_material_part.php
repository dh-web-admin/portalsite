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

if ($part_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Part ID is required']);
    exit;
}

try {
    // First get the part info before deleting (to sync with Parts and Suppliers)
    $stmtInfo = $conn->prepare("SELECT emp.name, em.item_id 
                                FROM Engineering_material_parts emp 
                                JOIN Engineering_materials em ON emp.material_id = em.id 
                                WHERE emp.id = ?");
    $stmtInfo->bind_param('i', $part_id);
    $stmtInfo->execute();
    $resultInfo = $stmtInfo->get_result();
    $partInfo = $resultInfo->fetch_assoc();
    $stmtInfo->close();
    
    $part_name = $partInfo ? $partInfo['name'] : null;
    $item_id = $partInfo ? $partInfo['item_id'] : null;
    $engineering_part_id = null;

    if ($part_name && $item_id) {
        $stmtMap = $conn->prepare("SELECT id FROM engineering_item_parts WHERE item_id = ? AND part_name = ? LIMIT 1");
        $stmtMap->bind_param('is', $item_id, $part_name);
        $stmtMap->execute();
        $resultMap = $stmtMap->get_result();
        $mapRow = $resultMap ? $resultMap->fetch_assoc() : null;
        $engineering_part_id = $mapRow ? (int)$mapRow['id'] : null;
        $stmtMap->close();
    }
    
    // Delete from Engineering_material_parts
    $stmt = $conn->prepare("DELETE FROM Engineering_material_parts WHERE id = ?");
    $stmt->bind_param('i', $part_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Also delete from Parts and Suppliers section
        if ($part_name && $item_id) {
            // Delete from engineering_item_parts
            $stmtSync = $conn->prepare("DELETE FROM engineering_item_parts WHERE item_id = ? AND part_name = ?");
            $stmtSync->bind_param('is', $item_id, $part_name);
            $stmtSync->execute();
            $stmtSync->close();
            
            // Delete from engineering_part_specifications
            $stmtSpec = $conn->prepare("DELETE FROM engineering_part_specifications WHERE part_name = ?");
            $stmtSpec->bind_param('s', $part_name);
            $stmtSpec->execute();
            $stmtSpec->close();

            $tableCheck = $conn->query("SHOW TABLES LIKE 'engineering_drawings'");
            if ($tableCheck && $tableCheck->num_rows > 0 && $engineering_part_id) {
                $stmtDrawings = $conn->prepare("DELETE FROM engineering_drawings WHERE item_id = ? AND part_id = ?");
                $stmtDrawings->bind_param('ii', $item_id, $engineering_part_id);
                $stmtDrawings->execute();
                $stmtDrawings->close();
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Part deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete part: ' . $stmt->error]);
        $stmt->close();
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
