<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['id']) || !is_numeric($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid drawing id']);
    exit();
}

$id = intval($input['id']);

try {
    $isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;
    $uploadsMount = getenv('UPLOADS_MOUNT_PATH') ?: '/portalsite/uploads';
    $uploadsBase = $isProduction ? rtrim($uploadsMount, '/') : (__DIR__ . '/../uploads');

    // Fetch file_url to unlink
    $stmt = $conn->prepare('SELECT file_url FROM engineering_drawings WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Drawing not found']);
        exit();
    }
    $row = $res->fetch_assoc();
    $fileUrl = $row['file_url'];
    $stmt->close();

    // Delete DB row
    $stmt = $conn->prepare('DELETE FROM engineering_drawings WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    // Attempt to unlink file from uploads dir if possible
    if ($affected > 0 && $fileUrl) {
        // convert URL path to filesystem path
        $filename = basename($fileUrl);
        $filePath = $uploadsBase . '/engineering_drawings/' . $filename;
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Deleted']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
