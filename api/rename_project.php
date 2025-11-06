<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['email'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Not authenticated']);
  exit;
}

$project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
$new_name = isset($_POST['new_name']) ? trim($_POST['new_name']) : '';

if ($project_id <= 0 || $new_name === '') {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Missing parameters']);
  exit;
}

$stmt = $conn->prepare('UPDATE `Projects` SET `Project_Name` = ? WHERE `Project_ID` = ? LIMIT 1');
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
  exit;
}
$stmt->bind_param('si', $new_name, $project_id);
if ($stmt->execute()) {
  echo json_encode(['success' => true, 'message' => 'Renamed']);
} else {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Rename failed']);
}
$stmt->close();
?>