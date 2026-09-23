<?php
define('IS_API', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../partials/permissions.php';
require_once __DIR__ . '/../pages/scheduling/project_uploads.php';

ini_set('display_errors', '0');
while (ob_get_level()) { ob_end_clean(); }

project_uploads_require_access($conn);

$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$draftKey  = isset($_GET['draft_key']) ? trim((string)$_GET['draft_key']) : '';

if ($projectId <= 0 && $draftKey === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing project_id or draft_key']);
    exit;
}

project_uploads_ensure_schema($conn);

echo json_encode([
    'success' => true,
    'uploads' => project_uploads_list($conn, $projectId, $draftKey),
]);
