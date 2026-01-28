<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json; charset=utf-8');

try { require_edit_api('Bid_tracking'); } catch (Throwable $ex) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Permission denied']); exit(); }

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$dhss = isset($_POST['dhss_project_number']) ? trim($_POST['dhss_project_number']) : null;
$gc = isset($_POST['general_contractor']) ? trim($_POST['general_contractor']) : null;
$name = isset($_POST['general_contractor_name']) ? trim($_POST['general_contractor_name']) : null;
$num = isset($_POST['general_contractor_number']) ? trim($_POST['general_contractor_number']) : null;
$email = isset($_POST['general_contractor_email']) ? trim($_POST['general_contractor_email']) : null;
$addr = isset($_POST['general_contractor_address']) ? trim($_POST['general_contractor_address']) : null;
$is_union = isset($_POST['is_union']) ? intval($_POST['is_union']) : null;
$winner = isset($_POST['winner']) ? ($_POST['winner'] ? 1 : 0) : 0;

if (!$id) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'ID required']);
  exit;
}

try {
  $sql = 'UPDATE general_contractor SET dhss_project_number = ?, general_contractor = ?, general_contractor_name = ?, general_contractor_number = ?, general_contractor_email = ?, general_contractor_address = ?, is_union = ?, winner = ? WHERE id = ?';
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'DB prepare failed','db_error' => $conn->error ?? '']);
    exit;
  }
  $iu = ($is_union === null) ? 0 : intval($is_union);
  $stmt->bind_param('ssssssiii', $dhss, $gc, $name, $num, $email, $addr, $iu, $winner, $id);
  if ($stmt->execute()) {
    echo json_encode(['success'=>true,'affected'=>$stmt->affected_rows]);
  } else {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Update failed','db_error'=>$stmt->error ?? '']);
  }
  $stmt->close();
} catch (Throwable $ex) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Exception','exception'=>$ex->getMessage()]);
}

?>
