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
  $result = $conn->query("SELECT DISTINCT reason FROM bids WHERE reason IS NOT NULL AND TRIM(reason) != '' ORDER BY reason ASC");
  $reasons = [];
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $reasons[] = $row['reason'];
    }
  }
  echo json_encode(['success' => true, 'reasons' => $reasons]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Failed to load reasons']);
}
