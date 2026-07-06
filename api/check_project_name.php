<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

$name = '';
if (isset($_POST['project_name'])) $name = trim((string)$_POST['project_name']);
if (!$name && isset($_GET['project_name'])) $name = trim((string)$_GET['project_name']);

if ($name === '') {
  echo json_encode(['success' => false, 'message' => 'Missing project_name']);
  exit;
}

try {
  $stmt = $conn->prepare("SELECT project_id FROM projects WHERE project_name = ? LIMIT 1");
  $stmt->bind_param('s', $name);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res && $row = $res->fetch_assoc()) {
    echo json_encode(['success' => true, 'exists' => true, 'project_id' => $row['project_id']]);
  } else {
    echo json_encode(['success' => true, 'exists' => false]);
  }
  $stmt->close();
} catch (Throwable $ex) {
  echo json_encode(['success' => false, 'message' => 'DB error']);
}

exit;
