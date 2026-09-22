<?php
define('IS_API', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../partials/permissions.php';
require_once __DIR__ . '/../partials/project_uploads.php';

ini_set('display_errors', '0');
while (ob_get_level()) { ob_end_clean(); }

project_uploads_require_access($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

project_uploads_ensure_schema($conn);

$id       = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$draftKey = isset($_POST['draft_key']) ? trim((string)$_POST['draft_key']) : '';

// Discarding an unsaved "Add Project" modal purges everything staged for it.
if ($id <= 0 && $draftKey !== '') {
    $stmt = $conn->prepare('SELECT id, filename FROM scheduled_project_uploads WHERE draft_key = ? AND project_id IS NULL');
    $stmt->bind_param('s', $draftKey);
    $stmt->execute();
    $res = $stmt->get_result();
    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int)$row['id'];
        project_uploads_unlink((string)$row['filename']);
    }
    $stmt->close();
    if ($ids) {
        $conn->query('DELETE FROM scheduled_project_uploads WHERE id IN (' . implode(',', $ids) . ')');
    }
    echo json_encode(['success' => true, 'deleted' => count($ids)]);
    exit;
}

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing id']);
    exit;
}

$stmt = $conn->prepare('SELECT filename, project_id FROM scheduled_project_uploads WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Image not found']);
    exit;
}

$del = $conn->prepare('DELETE FROM scheduled_project_uploads WHERE id = ? LIMIT 1');
$del->bind_param('i', $id);
$ok = $del->execute();
$del->close();

if ($ok) {
    project_uploads_unlink((string)$row['filename']);
    project_uploads_sync_project_urls($conn, (int)$row['project_id']);
}

echo json_encode(['success' => (bool)$ok]);
