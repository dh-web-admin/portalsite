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
  // Helper: find which column (if any) in a table stores the DHSS project number
  $candidateCols = ['dhss_project_number','DHSS_Project_Number','DHSSProjectNumber','dhss_project','DHSS_ProjectNumber'];
  $findDhssCol = function($table) use ($conn, $candidateCols) {
    foreach ($candidateCols as $c) {
      $esc = $conn->real_escape_string($c);
      $qr = "SHOW COLUMNS FROM `" . $table . "` LIKE '" . $esc . "'";
      $res = $conn->query($qr);
      if ($res && $res->num_rows > 0) return $c;
    }
    return null;
  };

  $bidsCol = $findDhssCol('bids');
  if ($bidsCol !== null) {
    $sql = 'DELETE FROM `bids` WHERE `' . $bidsCol . '` = ?';
    $delBids = $conn->prepare($sql);
    if ($delBids === false) throw new Exception('Prepare delete bids failed: ' . $conn->error);
    $delBids->bind_param('s', $dhss);
    if ($delBids->execute() === false) throw new Exception('Execute delete bids failed: ' . $delBids->error);
    $delBids->close();
  }

  // Delete general_contractor rows for this DHSS project (if column exists)
  $gcCol = $findDhssCol('general_contractor');
  if ($gcCol !== null) {
    $sql = 'DELETE FROM `general_contractor` WHERE `' . $gcCol . '` = ?';
    $delGc = $conn->prepare($sql);
    if ($delGc === false) throw new Exception('Prepare delete general_contractor failed: ' . $conn->error);
    $delGc->bind_param('s', $dhss);
    if ($delGc->execute() === false) throw new Exception('Execute delete general_contractor failed: ' . $delGc->error);
    $delGc->close();
  }

  // Also attempt to delete Projects entries that match (if column exists)
  $projCol = $findDhssCol('Projects');
  if ($projCol !== null) {
    $sql = 'DELETE FROM `Projects` WHERE `' . $projCol . '` = ?';
    $delProj = $conn->prepare($sql);
    if ($delProj === false) throw new Exception('Prepare delete Projects failed: ' . $conn->error);
    $delProj->bind_param('s', $dhss);
    if ($delProj->execute() === false) throw new Exception('Execute delete Projects failed: ' . $delProj->error);
    $delProj->close();
  }

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
