<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
// Indicate this is an API endpoint to prevent UI injection from permissions helper
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';
header('Content-Type: application/json; charset=utf-8');

require_edit_api('project_checklist');

$project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
if ($project_id <= 0) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Missing project id']);
  exit;
}

// Optional: TODO - check permissions/role before delete
$stmt = $conn->prepare('DELETE FROM `Projects` WHERE `Project_ID` = ? LIMIT 1');
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
  exit;
}
$stmt->bind_param('i', $project_id);
if ($stmt->execute()) {
  echo json_encode(['success' => true, 'message' => 'Deleted']);
} else {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Delete failed']);
}
$stmt->close();
?>