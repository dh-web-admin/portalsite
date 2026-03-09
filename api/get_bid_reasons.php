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
  // Ensure the dedicated reasons table exists
  $conn->query("CREATE TABLE IF NOT EXISTS bid_reasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reason VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reason (reason(255))
  )");

  // Return union of dedicated reasons table AND distinct reasons already in bids
  $sql = "
    SELECT reason FROM bid_reasons WHERE reason IS NOT NULL AND TRIM(reason) != ''
    UNION
    SELECT DISTINCT reason FROM bids WHERE reason IS NOT NULL AND TRIM(reason) != ''
    ORDER BY reason ASC
  ";
  $result = $conn->query($sql);
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
