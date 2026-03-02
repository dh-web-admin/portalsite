<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['item_id']) || !is_numeric($_GET['item_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
    exit();
}

$itemId = intval($_GET['item_id']);

try {
    // Auto-create table with version support if needed
    $conn->query("CREATE TABLE IF NOT EXISTS `engineering_bom` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `item_id` INT NOT NULL,
        `document_name` VARCHAR(255) NOT NULL,
        `filename` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(500) NOT NULL,
        `version` VARCHAR(10) NOT NULL DEFAULT 'v1',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`item_id`) REFERENCES `engineering_items` (`id`) ON DELETE CASCADE,
        INDEX `item_id_idx` (`item_id`)
    )");

    // Add version column if it does not exist (for tables created before versioning)
    $colCheck = $conn->query("SHOW COLUMNS FROM `engineering_bom` LIKE 'version'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE `engineering_bom` ADD COLUMN `version` VARCHAR(10) NOT NULL DEFAULT 'v1' AFTER `file_path`");
    }

    $stmt = $conn->prepare("SELECT id, document_name, filename, file_path, version, created_at FROM engineering_bom WHERE item_id = ? ORDER BY CAST(SUBSTRING(version, 2) AS UNSIGNED) DESC, id DESC");
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $result = $stmt->get_result();

    $boms = [];
    while ($row = $result->fetch_assoc()) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $row['file_url'] = ($host === 'localhost')
            ? '/PortalSite/uploads/engineering_bom/' . $row['filename']
            : '/uploads/engineering_bom/' . $row['filename'];
        $boms[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'boms' => $boms]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
