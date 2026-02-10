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

// Fetch associated DHSS project number (if the column exists) so we can
// remove related general_contractor rows. Some installs of the Projects
// table do not have this column, so we feature-detect it first.
$dhss = null;
$hasDhssCol = false;
try {
  $colRes = $conn->query("SHOW COLUMNS FROM `Projects` LIKE 'dhss_project_number'");
  if ($colRes && $colRes->num_rows > 0) {
    $hasDhssCol = true;
  }
} catch (Throwable $e) {
  $hasDhssCol = false;
}

if ($hasDhssCol) {
  $sel = $conn->prepare('SELECT dhss_project_number FROM `Projects` WHERE `Project_ID` = ? LIMIT 1');
  if ($sel) {
    $sel->bind_param('i', $project_id);
    $sel->execute();
    $res = $sel->get_result();
    if ($res && $res->num_rows > 0) {
      $row = $res->fetch_assoc();
      $dhss = isset($row['dhss_project_number']) ? $row['dhss_project_number'] : null;
    }
    $sel->close();
  }
}

// Begin transaction so deletes are atomic
try {
  $conn->begin_transaction();

  // If we have a DHSS project number, delete related general_contractor rows
  if ($dhss !== null && $dhss !== '') {
    $delGc = $conn->prepare('DELETE FROM `general_contractor` WHERE `dhss_project_number` = ?');
    if ($delGc) {
      $delGc->bind_param('s', $dhss);
      $delGc->execute();
      $delGc->close();
    }
  }

  // Delete project row
  $stmt = $conn->prepare('DELETE FROM `Projects` WHERE `Project_ID` = ? LIMIT 1');
  if (!$stmt) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
    exit;
  }
  $stmt->bind_param('i', $project_id);
  if ($stmt->execute()) {
    $stmt->close();
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Deleted']);
  } else {
    $stmt->close();
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Delete failed']);
  }
} catch (Throwable $ex) {
  try { $conn->rollback(); } catch (Throwable $_) {}
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Exception during delete']);
}
?>