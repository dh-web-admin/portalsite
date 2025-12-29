
<?php
// Debug log function
function log_upload_debug($msg) {
    $logfile = __DIR__ . '/../uploads/equipment/upload_debug.log';
    file_put_contents($logfile, date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
}

require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['equipment_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing equipment_id']);
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
if ($isProduction) {
    // Use the actual volume mount path in Railway
    $uploadDir = '/app/PortalSite/uploads/equipment/';
    $fileUrlPrefix = '/PortalSite/uploads/equipment/';
} else {
    $uploadDir = __DIR__ . '/../uploads/equipment/';
    $fileUrlPrefix = '/PortalSite/uploads/equipment/';
}
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$successCount = 0;
$errors = [];
$uploadedFiles = [];

$seenFiles = [];
foreach ($files as $file) {
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $baseName = $field . '_' . uniqid() . '.' . $ext;
    $fileUrl = $fileUrlPrefix . $baseName;
    $targetPath = $uploadDir . $baseName;
    // Prevent duplicate DB insert for the same file in a single request
    if (isset($seenFiles[$fileUrl])) continue;
    $seenFiles[$fileUrl] = true;
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        log_upload_debug("File uploaded: $targetPath for equipment_id=$equipment_id, field=$field");
        $stmt = $conn->prepare('INSERT INTO equipment_uploads (equipment_id, field, file_url, uploaded_at) VALUES (?, ?, ?, NOW())');
        if (!$stmt) {
            log_upload_debug("DB prepare failed: " . $conn->error);
            $errors[] = 'DB prepare failed: ' . $conn->error;
            continue;
        }
        $stmt->bind_param('iss', $equipment_id, $field, $fileUrl);
        if (!$stmt->execute()) {
            log_upload_debug("DB error: " . $stmt->error);
            $errors[] = 'DB error: ' . $stmt->error;
            $stmt->close();
            continue;
        }
        $stmt->close();
        log_upload_debug("DB insert success for $fileUrl");
        $uploadedFiles[] = $fileUrl;
        $successCount++;
    } else {
        log_upload_debug("Failed to move uploaded file: $targetPath");
        $errors[] = 'Failed to move uploaded file: ' . $file['name'];
    }
}

if ($successCount > 0) {
    echo json_encode(['success' => true, 'uploaded' => $uploadedFiles, 'errors' => $errors]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => $errors]);
}
