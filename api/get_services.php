<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// Basic auth: ensure user is logged in
if (!isset($_SESSION['email'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Not authenticated']);
  exit;
}

// Check if services table exists; if not, return empty list
$tableCheck = $conn->query("SHOW TABLES LIKE 'services'");
if ($tableCheck->num_rows === 0) {
  echo json_encode(['success' => true, 'services' => []]);
  exit;
}

// Fetch all services
$result = $conn->query('SELECT name FROM services ORDER BY name ASC');
$services = [];

if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $services[] = $row['name'];
  }
}

echo json_encode(['success' => true, 'services' => $services]);
?>
