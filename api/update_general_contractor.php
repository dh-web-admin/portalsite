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

$existingName = '';
$existingStmt = $conn->prepare('SELECT general_contractor_name FROM general_contractor WHERE id = ? LIMIT 1');
if ($existingStmt) {
  $existingStmt->bind_param('i', $id);
  if ($existingStmt->execute()) {
    $res = $existingStmt->get_result();
    if ($res && $res->num_rows > 0) {
      $row = $res->fetch_assoc();
      $existingName = isset($row['general_contractor_name']) ? (string)$row['general_contractor_name'] : '';
    }
  }
  $existingStmt->close();
}

try {
  $client_win_price = isset($_POST['client_win_price']) ? trim($_POST['client_win_price']) : null;
  if ($client_win_price !== null && $client_win_price !== '') {
    $tmp = str_replace([',', '$', ' '], '', (string)$client_win_price);
    if (preg_match('/^[+-]?\d+(?:\.\d+)?$/', $tmp)) $client_win_price = $tmp;
  }
  $sql = 'UPDATE general_contractor SET dhss_project_number = ?, general_contractor = ?, general_contractor_name = ?, general_contractor_number = ?, general_contractor_email = ?, general_contractor_address = ?, is_union = ?, winner = ?, client_win_price = ? WHERE id = ?';
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'DB prepare failed','db_error' => $conn->error ?? '']);
    exit;
  }
  $iu = ($is_union === null) ? 0 : intval($is_union);
  $stmt->bind_param('ssssssiisi', $dhss, $gc, $name, $num, $email, $addr, $iu, $winner, $client_win_price, $id);
  if ($stmt->execute()) {
    $clientUpdated = 0;
    $lookupName = $existingName !== '' ? $existingName : (string)$name;
    $currentName = (string)$name;
    if ($lookupName !== '' && $currentName !== '') {
      $clientUnion = null;
      if ($is_union === 1 || $is_union === 0) {
        $clientUnion = $is_union === 1 ? 'Union' : 'Non-Union';
      }

      $clientStmt = $conn->prepare(
        'UPDATE clients SET client_name = ?, current_employer = ?, contact_phone = ?, client_email = ?, client_address = ?, union_status = ? WHERE LOWER(client_name) = LOWER(?) AND (client_type IS NULL OR LOWER(client_type) = "general contractor")'
      );
      if ($clientStmt) {
        $clientStmt->bind_param('sssssss', $currentName, $gc, $num, $email, $addr, $clientUnion, $lookupName);
        if ($clientStmt->execute()) {
          $clientUpdated = $clientStmt->affected_rows;
        }
        $clientStmt->close();
      }
    }

    echo json_encode(['success'=>true,'affected'=>$stmt->affected_rows,'client_updated'=>$clientUpdated]);
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
