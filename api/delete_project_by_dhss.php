<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';
header('Content-Type: application/json; charset=utf-8');

// require edit permission for Bid_tracking
try { require_edit_api('Bid_tracking'); } catch (Throwable $ex) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Permission denied']); exit(); }

$dhss = isset($_POST['dhss_project_number']) ? trim((string)$_POST['dhss_project_number']) : '';
if ($dhss === '') {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'Missing dhss_project_number']);
  exit;
}

try {
  if (!method_exists($conn, 'begin_transaction')) {
    // older PHP/MySQLi fallback
    $conn->query('START TRANSACTION');
  } else {
    $conn->begin_transaction();
  }

  // Delete bids that match this DHSS project number
  $delBids = $conn->prepare('DELETE FROM `bids` WHERE `dhss_project_number` = ?');
  if ($delBids === false) throw new Exception('Prepare delete bids failed: ' . $conn->error);
  $delBids->bind_param('s', $dhss);
  if ($delBids->execute() === false) throw new Exception('Execute delete bids failed: ' . $delBids->error);
  $delBids->close();

  // Delete general_contractor rows for this DHSS project
  $delGc = $conn->prepare('DELETE FROM `general_contractor` WHERE `dhss_project_number` = ?');
  if ($delGc === false) throw new Exception('Prepare delete general_contractor failed: ' . $conn->error);
  $delGc->bind_param('s', $dhss);
  if ($delGc->execute() === false) throw new Exception('Execute delete general_contractor failed: ' . $delGc->error);
  $delGc->close();

  // Also attempt to delete Projects entries that match
  $delProj = $conn->prepare('DELETE FROM `Projects` WHERE `dhss_project_number` = ?');
  if ($delProj === false) throw new Exception('Prepare delete Projects failed: ' . $conn->error);
  $delProj->bind_param('s', $dhss);
  if ($delProj->execute() === false) throw new Exception('Execute delete Projects failed: ' . $delProj->error);
  $delProj->close();

  $conn->commit();
  echo json_encode(['success' => true]);
  exit;
} catch (Throwable $ex) {
  try { if (method_exists($conn, 'rollback')) $conn->rollback(); else $conn->query('ROLLBACK'); } catch (Throwable $_) {}
  error_log('delete_project_by_dhss error: ' . $ex->getMessage());
  http_response_code(500);
  // Return exception message to help local debugging
  echo json_encode(['success'=>false,'message'=>'Exception during delete: ' . $ex->getMessage()]);
  exit;
}

?>
