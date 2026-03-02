<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_POST['item_id']) || !is_numeric($_POST['item_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
    exit();
}
if (empty(trim($_POST['document_name'] ?? ''))) {
    echo json_encode(['success' => false, 'message' => 'Document name is required']);
    exit();
}
if (!isset($_FILES['bom_file']) || $_FILES['bom_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload failed']);
    exit();
}
if ($_FILES['bom_file']['size'] > 50 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 50MB limit']);
    exit();
}

$itemId = intval($_POST['item_id']);
$documentName = trim($_POST['document_name']);
$file = $_FILES['bom_file'];

// Auto-create table with version column
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

// Add version column if missing (existing tables)
$colCheck = $conn->query("SHOW COLUMNS FROM `engineering_bom` LIKE 'version'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE `engineering_bom` ADD COLUMN `version` VARCHAR(10) NOT NULL DEFAULT 'v1' AFTER `file_path`");
}

// Calculate next version for this item
$stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(version, 2) AS UNSIGNED)) as max_version FROM engineering_bom WHERE item_id = ?");
$stmt->bind_param('i', $itemId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$maxVersion = $row['max_version'] ? intval($row['max_version']) : 0;
$batchVersion = 'v' . ($maxVersion + 1);
$stmt->close();

$uploadDir = __DIR__ . '/../uploads/engineering_bom/';
if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$baseFilename = preg_replace('/[^a-z0-9_-]/i', '', str_replace(' ', '_', $documentName));
$filename = $baseFilename . '_' . $batchVersion . '_' . time() . '.' . $ext;
$filepath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit();
}

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$fileUrl = ($host === 'localhost')
    ? '/PortalSite/uploads/engineering_bom/' . $filename
    : '/uploads/engineering_bom/' . $filename;

$stmt = $conn->prepare("INSERT INTO engineering_bom (item_id, document_name, filename, file_path, version) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param('issss', $itemId, $documentName, $filename, $filepath, $batchVersion);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'BOM uploaded successfully', 'version' => $batchVersion]);
} else {
    if (file_exists($filepath)) { unlink($filepath); }
    echo json_encode(['success' => false, 'message' => 'Failed to save to database']);
}
$stmt->close();
?>
