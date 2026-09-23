<?php
define('IS_API', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../partials/permissions.php';
header('Content-Type: application/json');

if (empty($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
$role = get_current_role();
if (!$role || !can_access((string)$role, 'equipments')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

// Self-healing schema — see api/add_equipment_upload.php for the full definition.
$conn->query("CREATE TABLE IF NOT EXISTS `uploads` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `upload_key` VARCHAR(48) NOT NULL,
    `equipment_id` INT(10) UNSIGNED DEFAULT NULL,
    `field` VARCHAR(64) DEFAULT NULL,
    `file_url` VARCHAR(1024) NOT NULL,
    `filename` VARCHAR(255) DEFAULT NULL,
    `original_name` VARCHAR(255) DEFAULT NULL,
    `mime_type` VARCHAR(255) DEFAULT NULL,
    `size_bytes` INT(10) UNSIGNED DEFAULT 0,
    `uploaded_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `u_upload_key` (`upload_key`),
    KEY `idx_equipment` (`equipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$equipment_id = isset($_GET['equipment_id']) ? (int)$_GET['equipment_id'] : 0;
if (!$equipment_id) {
    echo json_encode(['success' => false, 'message' => 'Missing equipment_id']);
    exit;
}

$stmt = $conn->prepare("SELECT id, field, file_url, filename, created_at FROM uploads WHERE equipment_id = ?");
$stmt->bind_param('i', $equipment_id);
$stmt->execute();
$res = $stmt->get_result();
$uploads = [
    'air_filters' => [],
    'warranty' => [],
    'tires' => [],
    'dimension' => []
];

// Determine mount path for server-side checks
$isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;
$uploads_mount = getenv('UPLOADS_MOUNT_PATH') ?: '/portalsite/uploads';

while ($row = $res->fetch_assoc()) {
    $f = $row['field'];
    $filename = $row['filename'] ?? basename($row['file_url'] ?? '');
    if (!$filename) continue;

    // Canonical public URL
    $publicUrl = '/uploads/equipment/' . $filename;

    // Server-side file path
    $filePath = rtrim($uploads_mount, '/') . '/equipment/' . $filename;

    // If the physical file doesn't exist, skip
    if (!file_exists($filePath)) {
        continue;
    }

    // Return canonical public URL regardless of what is stored
    $row['file_url'] = $publicUrl;

    if (isset($uploads[$f])) {
        $uploads[$f][] = $row;
    }
}
$stmt->close();

echo json_encode(['success' => true, 'uploads' => $uploads]);
