<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';
header('Content-Type: application/json');

require_edit_api('equipments');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

 $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing file id']);
    exit;
}

// Get file path before deleting (use new `uploads` table)
$stmt = $conn->prepare('SELECT file_url, filename FROM uploads WHERE id=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'File not found']);
    exit;
}

$fileUrl = $row['file_url'];
$isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;
// Resolve filesystem path using UPLOADS_MOUNT_PATH or fallback
$uploads_mount = getenv('UPLOADS_MOUNT_PATH') ?: '/portalsite/uploads';
$isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;
if ($isProduction) {
    $filePath = rtrim($uploads_mount, '/') . '/equipment/' . basename($row['filename'] ?: $fileUrl);
} else {
    $filePath = __DIR__ . '/../uploads/equipment/' . basename($row['filename'] ?: $fileUrl);
}

// Delete DB record from uploads
$stmt = $conn->prepare('DELETE FROM uploads WHERE id=?');
$stmt->bind_param('i', $id);
$success = $stmt->execute();
$stmt->close();

// Delete file from disk (ignore errors)
if (file_exists($filePath)) {
    @unlink($filePath);
}

echo json_encode(['success' => $success]);
