<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json; charset=utf-8');

require_edit_api('maps');

if (!isset($_POST['name']) || trim($_POST['name']) === '') {
  echo json_encode(['success' => false, 'message' => 'Supplier name required']);
  exit;
}
if (!isset($_POST['color']) || trim($_POST['color']) === '') {
  echo json_encode(['success' => false, 'message' => 'Color value required']);
  exit;
}

$name = trim($_POST['name']);
$color = trim($_POST['color']);

// Basic validation: allow hex (#rrggbb) or any short string; you can tighten as needed
if (!preg_match('/^#([0-9a-fA-F]{6})$/', $color)) {
  // reject invalid hex; return error
  echo json_encode(['success' => false, 'message' => 'Color must be a hex value like #RRGGBB']);
  exit;
}

try {
  $sql = "UPDATE suppliers SET pin_color = ? WHERE name = ?";
  $stmt = $conn->prepare($sql);
  if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
  $stmt->bind_param('ss', $color, $name);
  if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Color updated']);
  } else {
    echo json_encode(['success' => false, 'message' => 'Failed to update color: ' . $stmt->error]);
  }
  $stmt->close();
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

?>