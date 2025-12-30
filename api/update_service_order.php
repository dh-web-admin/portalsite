<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json; charset=utf-8');

// Only admin may change service order
if (!is_admin()) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Admin required']);
  exit;
}

// Expect order as JSON array in POST 'order' (JSON string) or raw POST body
$orderJson = null;
if (!empty($_POST['order'])) $orderJson = $_POST['order'];
else {
  // read raw body
  $raw = file_get_contents('php://input');
  if ($raw) {
    // try parse as urlencoded
    parse_str($raw, $p);
    if (!empty($p['order'])) $orderJson = $p['order'];
    else $orderJson = $raw;
  }
}

if (empty($orderJson)) {
  echo json_encode(['success' => false, 'message' => 'Missing order parameter']);
  exit;
}

$order = json_decode($orderJson, true);
if (!is_array($order)) {
  echo json_encode(['success' => false, 'message' => 'Invalid order JSON']);
  exit;
}

// Ensure services table has display_order column; add if missing
$colCheck = $conn->query("SHOW COLUMNS FROM `services` LIKE 'display_order'");
if (!($colCheck && $colCheck->num_rows > 0)) {
  $alter = $conn->query("ALTER TABLE `services` ADD COLUMN `display_order` INT NULL AFTER `name`");
  if (!$alter) {
    echo json_encode(['success' => false, 'message' => 'Failed to add display_order column: ' . $conn->error]);
    exit;
  }
}

// Begin transaction and apply order values
$conn->begin_transaction();
try {
  // Clear display_order for all services to avoid duplicates
  $conn->query("UPDATE `services` SET display_order = NULL");

  $updateStmt = $conn->prepare('UPDATE `services` SET display_order = ? WHERE `name` = ?');
  if (!$updateStmt) throw new Exception('Prepare failed: ' . $conn->error);

  foreach ($order as $idx => $svc) {
    if (!is_string($svc)) continue;
    $orderVal = (int)$idx;
    $updateStmt->bind_param('is', $orderVal, $svc);
    if (!$updateStmt->execute()) {
      throw new Exception('Update failed for ' . $svc . ': ' . $updateStmt->error);
    }
  }

  $conn->commit();
  echo json_encode(['success' => true, 'message' => 'Order saved']);
} catch (Throwable $e) {
  $conn->rollback();
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
