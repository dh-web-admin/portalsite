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

if (!isset($_FILES['drawings']) || empty($_FILES['drawings']['name'][0])) {
    echo json_encode(['success' => false, 'message' => 'No files uploaded']);
    exit();
}

$itemId = intval($_POST['item_id']);
$uploadedBy = $_SESSION['email'];
$uploadDir = __DIR__ . '/../uploads/engineering_drawings/';

// Create upload directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

try {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'engineering_drawings'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Table engineering_drawings does not exist. Please create it first.']);
        exit();
    }

    $partColumnCheck = $conn->query("SHOW COLUMNS FROM engineering_drawings LIKE 'part_id'");
    if (!$partColumnCheck || $partColumnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE engineering_drawings ADD COLUMN part_id INT(11) NULL DEFAULT NULL AFTER item_id");
        $conn->query("CREATE INDEX idx_part_id ON engineering_drawings (part_id)");
        $conn->query("CREATE INDEX idx_item_part_version ON engineering_drawings (item_id, part_id, version)");
    }

    // Get the current highest version for this item
    $stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(version, 2) AS UNSIGNED)) as max_version FROM engineering_drawings WHERE item_id = ? AND part_id IS NULL");
    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $maxVersion = $row['max_version'] ? intval($row['max_version']) : 0;
    $batchVersion = 'v' . ($maxVersion + 1);
    $stmt->close();

    $uploadedFiles = [];
    $files = $_FILES['drawings'];
    $fileCount = count($files['name']);

    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $filename = basename($files['name'][$i]);
            $tmpName = $files['tmp_name'][$i];
            $fileSize = $files['size'][$i];
            
            // Generate unique filename
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $baseName = pathinfo($filename, PATHINFO_FILENAME);
            $uniqueName = $baseName . '_' . time() . '_' . uniqid() . '.' . $ext;
            $targetPath = $uploadDir . $uniqueName;
            
            if (move_uploaded_file($tmpName, $targetPath)) {
                // Determine file URL based on hostname
                $fileUrl = ($_SERVER['HTTP_HOST'] === 'localhost') 
                    ? '/PortalSite/uploads/engineering_drawings/' . $uniqueName
                    : '/uploads/engineering_drawings/' . $uniqueName;
                
                // Insert into database
                $partId = null;
                $stmt = $conn->prepare("INSERT INTO engineering_drawings (item_id, part_id, file_url, filename, version, file_size, uploaded_by, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param('iisssis', $itemId, $partId, $fileUrl, $filename, $batchVersion, $fileSize, $uploadedBy);
                $stmt->execute();
                $stmt->close();
                
                $uploadedFiles[] = [
                    'filename' => $filename,
                    'version' => $batchVersion,
                    'file_url' => $fileUrl
                ];
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file: ' . $filename]);
                exit();
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Upload error for file: ' . $files['name'][$i]]);
            exit();
        }
    }

    echo json_encode([
        'success' => true, 
        'message' => count($uploadedFiles) . ' file(s) uploaded successfully',
        'version' => $batchVersion,
        'files' => $uploadedFiles
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
