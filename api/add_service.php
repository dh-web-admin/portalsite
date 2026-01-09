<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json; charset=utf-8');

if (isset($conn)) $GLOBALS['conn'] = $conn;
require_edit_api('maps');

$serviceName = isset($_POST['service_name']) ? trim($_POST['service_name']) : '';

if ($serviceName === '') {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Service name required']);
  exit;
}

// Check if services table exists; if not, create it
$tableCheck = $conn->query("SHOW TABLES LIKE 'services'");
if ($tableCheck->num_rows === 0) {
  $createTable = "CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )";
  if (!$conn->query($createTable)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create services table']);
    exit;
  }
}

// Insert new service (ignore if duplicate)
$insertStmt = $conn->prepare('INSERT IGNORE INTO services (name) VALUES (?)');
if (!$insertStmt) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Database error']);
  exit;
}

$insertStmt->bind_param('s', $serviceName);
if ($insertStmt->execute()) {
  if ($insertStmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Service added', 'service_name' => $serviceName]);
  } else {
    echo json_encode(['success' => false, 'message' => 'Service already exists']);
  }
} else {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Failed to add service']);
}

$insertStmt->close();
?>
