<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json; charset=utf-8');

try { require_edit_api('Bid_tracking'); } catch (Throwable $ex) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Permission denied']); exit(); }

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$dhss = isset($_POST['dhss_project_number']) ? trim($_POST['dhss_project_number']) : null;

try {
  // Use transaction to ensure only one winner per project
  $conn->begin_transaction();
  if ($dhss) {
    $stmt0 = $conn->prepare('UPDATE general_contractor SET winner = 0 WHERE dhss_project_number = ?');
    if ($stmt0) { $stmt0->bind_param('s', $dhss); $stmt0->execute(); $stmt0->close(); }
  }
  if ($id) {
    $stmt = $conn->prepare('UPDATE general_contractor SET winner = 1 WHERE id = ?');
    if (!$stmt) {
      $conn->rollback();
      http_response_code(500);
      echo json_encode(['success'=>false,'message'=>'DB prepare failed','db_error'=>$conn->error ?? '']);
      exit;
    }
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
      $stmt->close();
      $conn->rollback();
      http_response_code(500);
      echo json_encode(['success'=>false,'message'=>'Update failed','db_error'=>$stmt->error ?? '']);
      exit;
    }
    $stmt->close();
  }
  $conn->commit();
  echo json_encode(['success'=>true]);
} catch (Throwable $ex) {
  try { $conn->rollback(); } catch(Throwable $__){ }
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Exception','exception'=>$ex->getMessage()]);
}

?>
