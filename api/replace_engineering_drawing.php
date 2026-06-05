<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_POST['drawing_id']) || !is_numeric($_POST['drawing_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid drawing id']);
    exit();
}

$drawingId = intval($_POST['drawing_id']);

if (!isset($_FILES['drawing']) || empty($_FILES['drawing']['name'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit();
}

$isProduction = getenv('RAILWAY_ENVIRONMENT') !== false;
$uploadsMount = getenv('UPLOADS_MOUNT_PATH') ?: '/portalsite/uploads';
$uploadDir = $isProduction
    ? rtrim($uploadsMount, '/') . '/engineering_drawings/'
    : __DIR__ . '/../uploads/engineering_drawings/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

try {
    // Get existing record (the row being replaced remains as previous version)
    $stmt = $conn->prepare('SELECT item_id, part_id, filename, version FROM engineering_drawings WHERE id = ?');
    $stmt->bind_param('i', $drawingId);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Drawing not found']);
        exit();
    }
    $row = $res->fetch_assoc();
    $itemId = intval($row['item_id']);
    $partId = isset($row['part_id']) ? $row['part_id'] : null;
    // Keep logical filename stable so versions stay grouped in UI
    $logicalFilename = $row['filename'];
    $stmt->close();

    // Compute next version for this logical drawing
    $stmt = $conn->prepare('SELECT MAX(CAST(SUBSTRING(version, 2) AS UNSIGNED)) AS max_ver FROM engineering_drawings WHERE item_id = ? AND (part_id <=> ?) AND filename = ?');
    $stmt->bind_param('iis', $itemId, $partId, $logicalFilename);
    $stmt->execute();
    $verRes = $stmt->get_result();
    $verRow = $verRes ? $verRes->fetch_assoc() : null;
    $maxVer = ($verRow && $verRow['max_ver']) ? intval($verRow['max_ver']) : 0;
    $nextVersion = 'v' . ($maxVer + 1);
    $stmt->close();

    $file = $_FILES['drawing'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Upload error']);
        exit();
    }

    $uploadedFilename = basename($file['name']);
    $filename = $logicalFilename ?: $uploadedFilename;
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $baseName = pathinfo($filename, PATHINFO_FILENAME);
    $uniqueName = $baseName . '_' . time() . '_' . uniqid() . '.' . $ext;
    $targetPath = $uploadDir . $uniqueName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
        exit();
    }

    $fileUrl = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/PortalSite/uploads/engineering_drawings/' . $uniqueName : '/uploads/engineering_drawings/' . $uniqueName;
    $fileSize = filesize($targetPath);
    $uploadedBy = $_SESSION['email'];

    // Insert new row as the next version so previous versions remain available
    $stmt = $conn->prepare('INSERT INTO engineering_drawings (item_id, part_id, file_url, filename, version, file_size, uploaded_by, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
    $stmt->bind_param('iisssis', $itemId, $partId, $fileUrl, $filename, $nextVersion, $fileSize, $uploadedBy);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Replaced',
        'file' => ['id' => $newId, 'filename' => $filename, 'file_url' => $fileUrl, 'version' => $nextVersion]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
