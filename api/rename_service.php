<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json; charset=utf-8');

if (isset($conn)) $GLOBALS['conn'] = $conn;
require_edit_api('maps');

$oldName = isset($_POST['old_name']) ? trim((string)$_POST['old_name']) : '';
$newName = isset($_POST['new_name']) ? trim((string)$_POST['new_name']) : '';

if ($oldName === '' || $newName === '') {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Old and new service names are required']);
  exit;
}

if ($oldName === $newName) {
  echo json_encode(['success' => true, 'message' => 'No changes']);
  exit;
}

// Ensure services table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'services'");
if (!$tableCheck || $tableCheck->num_rows === 0) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Services table not found']);
  exit;
}

try {
  $conn->begin_transaction();

  // Ensure old exists
  $selOld = $conn->prepare('SELECT id FROM services WHERE name = ? LIMIT 1');
  if (!$selOld) throw new Exception('DB prepare failed');
  $selOld->bind_param('s', $oldName);
  $selOld->execute();
  $resOld = $selOld->get_result();
  $oldRow = $resOld ? $resOld->fetch_assoc() : null;
  $selOld->close();
  if (!$oldRow) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Service not found']);
    exit;
  }

  // Ensure new doesn't already exist
  $selNew = $conn->prepare('SELECT id FROM services WHERE name = ? LIMIT 1');
  if (!$selNew) throw new Exception('DB prepare failed');
  $selNew->bind_param('s', $newName);
  $selNew->execute();
  $resNew = $selNew->get_result();
  $newRow = $resNew ? $resNew->fetch_assoc() : null;
  $selNew->close();
  if ($newRow) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'A service with that name already exists']);
    exit;
  }

  $updSvc = $conn->prepare('UPDATE services SET name = ? WHERE name = ? LIMIT 1');
  if (!$updSvc) throw new Exception('DB prepare failed');
  $updSvc->bind_param('ss', $newName, $oldName);
  if (!$updSvc->execute()) {
    $err = $updSvc->error;
    $updSvc->close();
    throw new Exception('Service update failed: ' . $err);
  }
  $svcChanged = $updSvc->affected_rows;
  $updSvc->close();

  // Keep suppliers consistent
  $supChanged = 0;
  $supUpdate = $conn->prepare('UPDATE suppliers SET service = ? WHERE service = ?');
  if ($supUpdate) {
    $supUpdate->bind_param('ss', $newName, $oldName);
    if ($supUpdate->execute()) {
      $supChanged = $supUpdate->affected_rows;
    }
    $supUpdate->close();
  }

  $conn->commit();
  echo json_encode([
    'success' => true,
    'message' => 'Service renamed',
    'old_name' => $oldName,
    'new_name' => $newName,
    'services_updated' => $svcChanged,
    'suppliers_updated' => $supChanged,
  ]);
} catch (Throwable $e) {
  try { $conn->rollback(); } catch (Throwable $e2) {}
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Rename failed', 'error' => $e->getMessage()]);
}
