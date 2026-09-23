
<?php
define('IS_API', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../partials/permissions.php';

require_edit_api('equipments');

// Self-healing schema: this table previously existed only out-of-band in the
// live database, with no CREATE TABLE anywhere in the codebase.
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

// Do not expose PHP warnings in the JSON response
ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

// Start with a clean output buffer to avoid accidental output corrupting JSON
while (ob_get_level()) { ob_end_clean(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['equipment_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing equipment_id']);
    exit;
}


$equipment_id = intval($_POST['equipment_id']);
$field = isset($_POST['field']) ? $_POST['field'] : 'dimension';


// Support both single and multiple file uploads
$files = [];
if (isset($_FILES['files'])) {
    // Multiple files (from files[])
    for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
        if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
            $files[] = [
                'name' => $_FILES['files']['name'][$i],
                'tmp_name' => $_FILES['files']['tmp_name'][$i],
                'type' => $_FILES['files']['type'][$i],
                'size' => $_FILES['files']['size'][$i],
            ];
        }
    }
} elseif (isset($_FILES['file'])) {
    // Single file (from file)
    if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $files[] = [
            'name' => $_FILES['file']['name'],
            'tmp_name' => $_FILES['file']['tmp_name'],
            'type' => $_FILES['file']['type'],
            'size' => $_FILES['file']['size'],
        ];
    }
}

if (empty($files)) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}


// Use Railway volume mount in production

$isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;
// Allow overrides via environment variables; sensible defaults for local and Railway
$uploads_mount = getenv('UPLOADS_MOUNT_PATH') ?: '/portalsite/uploads';
$uploads_web_prefix = getenv('UPLOADS_WEB_PREFIX') ?: '/PortalSite/uploads/equipment';
if ($isProduction) {
    $uploadDir = rtrim($uploads_mount, '/') . '/equipment/';
    $fileUrlPrefix = rtrim($uploads_web_prefix, '/');
} else {
    $uploadDir = __DIR__ . '/../uploads/equipment/';
    $fileUrlPrefix = rtrim($uploads_web_prefix, '/');
}

// Ensure upload directory exists and is writable
if (!is_dir($uploadDir)) {
    // Create with recursive flag
    if (!@mkdir($uploadDir, 0777, true)) {
        $err = error_get_last();
        error_log('add_equipment_upload: unable to create ' . $uploadDir . ' - ' . json_encode($err));
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Unable to create upload directory']);
        exit;
    }
}
if (!is_writable($uploadDir)) {
    error_log('add_equipment_upload: directory not writable: ' . $uploadDir);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Upload directory not writable']);
    exit;
}

$successCount = 0;
$errors = [];
$uploadedFiles = [];

$seenFiles = [];
require_once __DIR__ . '/../partials/upload_guard.php';
foreach ($files as $file) {
    $ext = safe_upload_extension($file);
    if ($ext === null) {
        $errors[] = 'File type not allowed: ' . $file['name'];
        continue;
    }
    $baseName = $field . '_' . uniqid() . '.' . $ext;
    // Ensure filename is safe (no directory traversal)
    $baseName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $baseName);
    if (strpos($baseName, '..') !== false) {
        $errors[] = 'Invalid filename';
        continue;
    }
    // Always store canonical public path
    $fileUrl = '/uploads/equipment/' . $baseName;
    $targetPath = rtrim($uploadDir, '/') . '/' . $baseName;
    // Prevent duplicate DB insert for the same file in a single request
    if (isset($seenFiles[$fileUrl])) continue;
    $seenFiles[$fileUrl] = true;
    $moved = false;
    if (is_uploaded_file($file['tmp_name'])) {
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $moved = true;
        } else {
            // Try fallback copy (some container setups disallow move_uploaded_file)
            if (@copy($file['tmp_name'], $targetPath)) {
                $moved = true;
                @unlink($file['tmp_name']);
            } else {
                error_log('add_equipment_upload: move/copy failed for ' . $file['tmp_name'] . ' -> ' . $targetPath);
            }
        }
        if ($moved) {
            // Ensure reasonable permissions
            @chmod($targetPath, 0644);
        }
    }

    if ($moved) {
        // Insert metadata into the new `uploads` table
        $upload_key = bin2hex(random_bytes(12));
        $uploaded_by = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $stmt = $conn->prepare('INSERT INTO uploads (upload_key, equipment_id, field, file_url, filename, original_name, mime_type, size_bytes, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        if (!$stmt) {
            error_log('add_equipment_upload: DB prepare failed: ' . $conn->error);
            $errors[] = 'DB prepare failed: ' . $conn->error;
            continue;
        }
        // bind types: s (upload_key), i (equipment_id), s (field), s (file_url), s (filename), s (original_name), s (mime_type), i (size_bytes), i (uploaded_by)
        $stmt->bind_param('sisssssii', $upload_key, $equipment_id, $field, $fileUrl, $baseName, $file['name'], $file['type'], $file['size'], $uploaded_by);
        if (!$stmt->execute()) {
            error_log('add_equipment_upload: DB error: ' . $stmt->error);
            $errors[] = 'DB error: ' . $stmt->error;
            $stmt->close();
            continue;
        }
        $stmt->close();
        $uploadedFiles[] = $fileUrl;
        $successCount++;
    } else {
        $errors[] = 'Failed to move uploaded file: ' . $file['name'];
    }
}

if ($successCount > 0) {
    echo json_encode(['success' => true, 'uploaded' => $uploadedFiles, 'errors' => $errors]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => $errors]);
}
