<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['email'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Not authenticated']);
  exit;
}

try {
  $tres = $conn->query("SHOW TABLES LIKE 'bids'");
  if (!$tres || !$tres->num_rows) {
    echo json_encode(['success' => true, 'columns' => []]);
    exit;
  }

  $cols = [];
  $colResult = $conn->query("SHOW COLUMNS FROM bids");
  if ($colResult) {
    while ($c = $colResult->fetch_assoc()) {
      if (in_array($c['Field'], ['created_at','updated_at','bid_id'], true)) continue;
      $cols[] = $c['Field'];
    }
  }

  echo json_encode(['success' => true, 'columns' => $cols]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Failed to load columns']);
}
