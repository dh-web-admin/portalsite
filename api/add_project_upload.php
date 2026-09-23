<?php
define('IS_API', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../partials/permissions.php';
require_once __DIR__ . '/../pages/scheduling/project_uploads.php';

ini_set('display_errors', '0');
while (ob_get_level()) { ob_end_clean(); }

project_uploads_require_access($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
$draftKey  = isset($_POST['draft_key']) ? trim((string)$_POST['draft_key']) : '';

// A staged upload (Add Project modal) has no project row yet, so it needs a key.
if ($projectId <= 0) {
    $projectId = 0;
    if ($draftKey === '' || !preg_match('/^[A-Za-z0-9]{8,48}$/', $draftKey)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing project_id or draft_key']);
        exit;
    }
} else {
    $draftKey = '';
    $exists = $conn->prepare('SELECT project_id FROM scheduled_projects WHERE project_id = ? LIMIT 1');
    $exists->bind_param('i', $projectId);
    $exists->execute();
    $found = $exists->get_result()->num_rows > 0;
    $exists->close();
    if (!$found) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Project not found']);
        exit;
    }
}

// Accept either files[] or a single file, like the equipment uploader does.
$files = [];
if (isset($_FILES['files']) && is_array($_FILES['files']['name'])) {
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
} elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $files[] = [
        'name' => $_FILES['file']['name'],
        'tmp_name' => $_FILES['file']['tmp_name'],
        'type' => $_FILES['file']['type'],
        'size' => $_FILES['file']['size'],
    ];
}

if (empty($files)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

if (!project_uploads_ensure_dir()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Upload directory not writable']);
    exit;
}
project_uploads_ensure_schema($conn);

$uploadDir  = project_uploads_dir();
$uploadedBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$uploaded   = [];
$errors     = [];

foreach ($files as $file) {
    // Rejects non-images, disguised images and anything executable.
    $ext = safe_upload_extension($file, PROJECT_UPLOAD_ALLOWED_EXTS);
    if ($ext === null) {
        $errors[] = 'Not a supported image: ' . $file['name'];
        continue;
    }

    $baseName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', 'note_' . uniqid() . '.' . $ext);
    $targetPath = $uploadDir . $baseName;

    $moved = false;
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $moved = true;
    } elseif (@copy($file['tmp_name'], $targetPath)) {
        // Some container setups disallow move_uploaded_file().
        $moved = true;
        @unlink($file['tmp_name']);
    }
    if (!$moved) {
        $errors[] = 'Failed to store ' . $file['name'];
        continue;
    }
    @chmod($targetPath, 0644);

    $fileUrl = project_uploads_url($baseName);
    $stmt = $conn->prepare('INSERT INTO scheduled_project_uploads (project_id, draft_key, file_url, filename, original_name, mime_type, size_bytes, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        @unlink($targetPath);
        $errors[] = 'Database error';
        continue;
    }
    $projectIdOrNull = $projectId > 0 ? $projectId : null;
    $draftOrNull = $draftKey !== '' ? $draftKey : null;
    $stmt->bind_param('isssssii', $projectIdOrNull, $draftOrNull, $fileUrl, $baseName, $file['name'], $file['type'], $file['size'], $uploadedBy);
    if (!$stmt->execute()) {
        $stmt->close();
        @unlink($targetPath);
        $errors[] = 'Database error';
        continue;
    }
    $newId = (int)$stmt->insert_id;
    $stmt->close();

    $uploaded[] = [
        'id' => $newId,
        'file_url' => $fileUrl,
        'filename' => $baseName,
        'original_name' => $file['name'],
    ];
}

if (empty($uploaded)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Staged uploads have no project yet; they get synced when the draft is claimed.
project_uploads_sync_project_urls($conn, $projectId);

echo json_encode(['success' => true, 'uploaded' => $uploaded, 'errors' => $errors]);
