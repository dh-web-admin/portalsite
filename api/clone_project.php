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
if ($project_id <= 0) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Missing project id']);
  exit;
}

// Fetch the original row
$sel = $conn->prepare('SELECT * FROM `Projects` WHERE `Project_ID` = ? LIMIT 1');
if (!$sel) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
  exit;
}
$sel->bind_param('i', $project_id);
$sel->execute();
$res = $sel->get_result();
if (!$res || $res->num_rows === 0) {
  http_response_code(404);
  echo json_encode(['success' => false, 'message' => 'Project not found']);
  exit;
}
$row = $res->fetch_assoc();
$sel->close();

// Remove primary key and prepare new name
unset($row['Project_ID']);
$origName = isset($row['Project_Name']) ? $row['Project_Name'] : 'Project';
$newName = 'Copy of ' . $origName;
$row['Project_Name'] = $newName;

// Build insert query dynamically
$cols = array_keys($row);
$placeholders = array_fill(0, count($cols), '?');
$sql = 'INSERT INTO `Projects` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', $placeholders) . ')';
$stmt = $conn->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'DB prepare failed: ' . $conn->error]);
  exit;
}

// Bind params as strings (safe fallback)
$types = str_repeat('s', count($cols));
$params = array();
$params[] = & $types;
foreach ($cols as $c) {
  $val = isset($row[$c]) ? $row[$c] : null;
  // convert to string safely
  $params[] = & $row[$c];
}
// call_user_func_array requires references
call_user_func_array(array($stmt, 'bind_param'), $params);

if ($stmt->execute()) {
  $newId = $stmt->insert_id;
  echo json_encode(['success' => true, 'message' => 'Cloned', 'new_id' => $newId]);
} else {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Clone failed: ' . $stmt->error]);
}
$stmt->close();
?>