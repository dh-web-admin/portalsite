<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json; charset=utf-8');

try { require_edit_api('Bid_tracking'); } catch (Throwable $ex) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Permission denied']); exit(); }

// Read POST data
$dhss = isset($_POST['dhss_project_number']) ? trim($_POST['dhss_project_number']) : null;
$gc = isset($_POST['general_contractor']) ? trim($_POST['general_contractor']) : null;
$name = isset($_POST['general_contractor_name']) ? trim($_POST['general_contractor_name']) : null;
$num = isset($_POST['general_contractor_number']) ? trim($_POST['general_contractor_number']) : null;
$email = isset($_POST['general_contractor_email']) ? trim($_POST['general_contractor_email']) : null;
$addr = isset($_POST['general_contractor_address']) ? trim($_POST['general_contractor_address']) : null;
$client_win_price = isset($_POST['client_win_price']) ? trim($_POST['client_win_price']) : null;
$is_union = isset($_POST['is_union']) ? intval($_POST['is_union']) : null;
$winner = isset($_POST['winner']) ? ($_POST['winner'] ? 1 : 0) : 0;

if ($client_win_price !== null && $client_win_price !== '') {
  $tmp = str_replace([',', '$', ' '], '', (string)$client_win_price);
  if (preg_match('/^[+-]?\d+(?:\.\d+)?$/', $tmp)) $client_win_price = $tmp;
}

if (!$gc && !$name) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'General contractor is required']);
  exit();
}

try {
  // Try to find an existing contractor for this project by name+number or by name+number+project
  $foundId = null;
  $checkSql = 'SELECT id FROM general_contractor WHERE dhss_project_number = ? AND ((general_contractor_name = ? AND general_contractor_number = ?) OR (general_contractor = ? AND general_contractor_number = ?)) LIMIT 1';
  $chk = $conn->prepare($checkSql);
  if ($chk) {
    $chk->bind_param('sssss', $dhss, $name, $num, $gc, $num);
    $chk->execute();
    $cres = $chk->get_result();
    if ($cres && $cres->num_rows) {
      $r = $cres->fetch_assoc();
      $foundId = $r['id'];
    }
    $chk->close();
  }

  if ($foundId) {
    // update existing with any provided fields
    $updates = [];
    $types = '';
    $params = [];
    if ($dhss !== null) { $updates[] = 'dhss_project_number = ?'; $types .= 's'; $params[] = $dhss; }
    if ($gc !== null) { $updates[] = 'general_contractor = ?'; $types .= 's'; $params[] = $gc; }
    if ($name !== null) { $updates[] = 'general_contractor_name = ?'; $types .= 's'; $params[] = $name; }
    if ($num !== null) { $updates[] = 'general_contractor_number = ?'; $types .= 's'; $params[] = $num; }
    if ($email !== null) { $updates[] = 'general_contractor_email = ?'; $types .= 's'; $params[] = $email; }
    if ($addr !== null) { $updates[] = 'general_contractor_address = ?'; $types .= 's'; $params[] = $addr; }
    if ($client_win_price !== null) { $updates[] = 'client_win_price = ?'; $types .= 's'; $params[] = $client_win_price; }
    if ($is_union !== null) { $updates[] = 'is_union = ?'; $types .= 'i'; $params[] = $is_union; }
    if ($winner !== null) { $updates[] = 'winner = ?'; $types .= 'i'; $params[] = $winner; }

    if (!empty($updates)) {
      $sql = 'UPDATE general_contractor SET ' . implode(', ', $updates) . ' WHERE id = ?';
      $types .= 'i';
      $params[] = $foundId;
      $stmt = $conn->prepare($sql);
      if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
      }
    }

    $out = ['success'=>true,'id'=>$foundId,'existing'=>true];
    @file_put_contents(__DIR__ . '/add_general_contractor.log', date('c') . " UPSERT " . json_encode(['post'=>$_POST, 'result'=>$out]) . PHP_EOL, FILE_APPEND);
    echo json_encode($out);
    exit;
  }

  // Insert new record
  $sql = 'INSERT INTO general_contractor (dhss_project_number, general_contractor, general_contractor_name, general_contractor_number, general_contractor_email, general_contractor_address, client_win_price, is_union, winner) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    error_log('add_general_contractor prepare failed: ' . ($conn->error ?? ''));
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'DB prepare failed', 'db_error' => $conn->error ?? '']);
    exit();
  }

  $iu = ($is_union === null) ? 0 : intval($is_union);
  $stmt->bind_param('sssssssii', $dhss, $gc, $name, $num, $email, $addr, $client_win_price, $iu, $winner);
  if ($stmt->execute()) {
    $out = ['success'=>true,'id'=>$stmt->insert_id,'existing'=>false];
    @file_put_contents(__DIR__ . '/add_general_contractor.log', date('c') . " OK " . json_encode(['post'=>$_POST, 'result'=>$out]) . PHP_EOL, FILE_APPEND);
    echo json_encode($out);
  } else {
    $err = ['success'=>false,'message'=>'Failed to insert','db_error'=>$stmt->error ?? ''];
    @file_put_contents(__DIR__ . '/add_general_contractor.log', date('c') . " ERR " . json_encode(['post'=>$_POST, 'error'=>$err]) . PHP_EOL, FILE_APPEND);
    error_log('add_general_contractor execute failed: ' . ($stmt->error ?? '') . ' | SQL: ' . $sql);
    http_response_code(500);
    echo json_encode($err);
  }
  $stmt->close();
} catch (Throwable $ex) {
  error_log('add_general_contractor exception: ' . $ex->getMessage());
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Exception', 'exception' => $ex->getMessage()]);
}
?>
