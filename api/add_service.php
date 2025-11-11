<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// Check authentication and admin role
if (!isset($_SESSION['email'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Not authenticated']);
  exit;
}

$email = $_SESSION['email'];
$stmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$userRole = $user ? $user['role'] : 'laborer';
$stmt->close();

// Allow admin or developer
if ($userRole !== 'admin' && $userRole !== 'developer') {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Access denied']);
  exit;
}

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
