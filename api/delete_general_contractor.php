<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json; charset=utf-8');

try { require_edit_api('Bid_tracking'); } catch (Throwable $ex) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Permission denied']); exit(); }

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if (!$id) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'ID required']);
  exit;
}

try {
  $stmt = $conn->prepare('DELETE FROM general_contractor WHERE id = ?');
  if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'DB prepare failed','db_error'=>$conn->error ?? '']);
    exit;
  }
  $stmt->bind_param('i', $id);
  if ($stmt->execute()) {
    echo json_encode(['success'=>true,'affected'=>$stmt->affected_rows]);
  } else {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Delete failed','db_error'=>$stmt->error ?? '']);
  }
  $stmt->close();
} catch (Throwable $ex) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Exception','exception'=>$ex->getMessage()]);
}

?>
