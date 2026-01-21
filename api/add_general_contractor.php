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
$winner = isset($_POST['winner']) ? ($_POST['winner'] ? 1 : 0) : 0;

if (!$gc) {
  http_response_code(400);
  echo json_encode(['success'=>false,'message'=>'General contractor is required']);
  exit();
}

try {
  // prepare
  $sql = 'INSERT INTO general_contractor (dhss_project_number, general_contractor, general_contractor_name, general_contractor_number, general_contractor_email, general_contractor_address, winner) VALUES (?, ?, ?, ?, ?, ?, ?)';
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    error_log('add_general_contractor prepare failed: ' . ($conn->error ?? ''));
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'DB prepare failed', 'db_error' => $conn->error ?? '']);
    exit();
  }

  $stmt->bind_param('ssssssi', $dhss, $gc, $name, $num, $email, $addr, $winner);
  if ($stmt->execute()) {
    $out = ['success'=>true,'id'=>$stmt->insert_id];
    // write a simple log for debugging
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