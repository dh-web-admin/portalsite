<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
// require permissions: only editors may trigger via web
require_once __DIR__ . '/../partials/permissions.php';
require_edit_api('Bid_tracking');

try {
  $sql = "UPDATE bids SET status = 'pending' WHERE status = 'bidding' AND bid_date IS NOT NULL AND DATE(bid_date) < CURDATE()";
  $res = $conn->query($sql);
  if ($res === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $conn->error]);
    exit();
  }
  echo json_encode(['success' => true, 'updated' => $conn->affected_rows]);
  exit();
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
  exit();
}

