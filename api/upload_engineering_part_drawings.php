<?php
define('IS_API', true);
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json');

require_edit_api('engineering');

$itemId = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
$partId = isset($_POST['part_id']) ? (int) $_POST['part_id'] : 0;

if ($itemId <= 0 || $partId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid item or part']);
    exit();
}

if (!isset($_FILES['drawings']) || empty($_FILES['drawings']['name'][0])) {
    echo json_encode(['success' => false, 'message' => 'No files uploaded']);
    exit();
}

$uploadedBy = $_SESSION['email'];
$isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;
$uploadsMount = getenv('UPLOADS_MOUNT_PATH') ?: '/portalsite/uploads';
$uploadDir = $isProduction
    ? rtrim($uploadsMount, '/') . '/engineering_drawings/'
    : __DIR__ . '/../uploads/engineering_drawings/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

try {
    // Self-healing schema: this table previously existed only out-of-band in
    // the live database, with no CREATE TABLE anywhere in the codebase.
    $conn->query("CREATE TABLE IF NOT EXISTS `engineering_drawings` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `item_id` INT(11) NOT NULL,
        `part_id` INT(11) DEFAULT NULL,
        `file_url` VARCHAR(500) NOT NULL,
        `filename` VARCHAR(255) NOT NULL,
        `version` VARCHAR(20) NOT NULL DEFAULT 'v1',
        `file_size` INT(11) DEFAULT NULL COMMENT 'File size in bytes',
        `file_type` VARCHAR(50) DEFAULT NULL COMMENT 'File extension/type',
        `description` TEXT DEFAULT NULL COMMENT 'Optional description of the drawing',
        `uploaded_by` VARCHAR(255) NOT NULL,
        `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        `status` ENUM('active','archived','deleted') DEFAULT 'active',
        PRIMARY KEY (`id`),
        KEY `idx_item_id` (`item_id`),
        KEY `idx_version` (`version`),
        KEY `idx_status` (`status`),
        KEY `idx_uploaded_at` (`uploaded_at`),
        KEY `idx_item_version` (`item_id`,`version`),
        KEY `idx_uploaded_by` (`uploaded_by`),
        KEY `idx_part_id` (`part_id`),
        KEY `idx_item_part_version` (`item_id`,`part_id`,`version`),
        CONSTRAINT `fk_engineering_drawings_item` FOREIGN KEY (`item_id`) REFERENCES `engineering_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_engineering_drawings_part` FOREIGN KEY (`part_id`) REFERENCES `engineering_item_parts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $partColumnCheck = $conn->query("SHOW COLUMNS FROM engineering_drawings LIKE 'part_id'");
    if (!$partColumnCheck || $partColumnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE engineering_drawings ADD COLUMN part_id INT(11) NULL DEFAULT NULL AFTER item_id");
        $conn->query("CREATE INDEX idx_part_id ON engineering_drawings (part_id)");
        $conn->query("CREATE INDEX idx_item_part_version ON engineering_drawings (item_id, part_id, version)");
    }

    $stmt = $conn->prepare('SELECT MAX(CAST(SUBSTRING(version, 2) AS UNSIGNED)) AS max_version FROM engineering_drawings WHERE item_id = ? AND part_id = ?');
    $stmt->bind_param('ii', $itemId, $partId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $maxVersion = $row && $row['max_version'] ? (int) $row['max_version'] : 0;
    $nextVersion = $maxVersion > 0 ? ($maxVersion + 1) : 1;
    $batchVersion = 'v' . $nextVersion;
    $stmt->close();

    $uploadedFiles = [];
    $files = $_FILES['drawings'];
    $fileCount = count($files['name']);

    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Upload error for file: ' . $files['name'][$i]]);
            exit();
        }

        $filename = basename($files['name'][$i]);
        $tmpName = $files['tmp_name'][$i];
        $fileSize = (int) $files['size'][$i];
        require_once __DIR__ . '/../partials/upload_guard.php';
        $ext = safe_upload_extension(['tmp_name' => $tmpName, 'name' => $filename]);
        if ($ext === null) {
            echo json_encode(['success' => false, 'message' => 'File type not allowed: ' . $filename]);
            exit();
        }
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $uniqueName = $baseName . '_' . time() . '_' . uniqid() . '.' . $ext;
        $targetPath = $uploadDir . $uniqueName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file: ' . $filename]);
            exit();
        }

        $fileUrl = ($_SERVER['HTTP_HOST'] === 'localhost')
            ? '/PortalSite/uploads/engineering_drawings/' . $uniqueName
            : '/uploads/engineering_drawings/' . $uniqueName;

        $stmt = $conn->prepare('INSERT INTO engineering_drawings (item_id, part_id, file_url, filename, version, file_size, uploaded_by, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->bind_param('iisssis', $itemId, $partId, $fileUrl, $filename, $batchVersion, $fileSize, $uploadedBy);
        $stmt->execute();
        $stmt->close();

        $uploadedFiles[] = [
            'filename' => $filename,
            'version' => $batchVersion,
            'file_url' => $fileUrl,
            'part_id' => $partId,
        ];
    }

    echo json_encode([
        'success' => true,
        'message' => count($uploadedFiles) . ' file(s) uploaded successfully',
        'version' => $batchVersion,
        'files' => $uploadedFiles,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}