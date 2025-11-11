<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

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

// Ensure services table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'services'");
if ($tableCheck->num_rows === 0) {
  echo json_encode(['success' => false, 'message' => 'Services table not found']);
  exit;
}

// Optional: also remove related suppliers if desired (currently not deleting suppliers)
$del = $conn->prepare('DELETE FROM services WHERE name = ? LIMIT 1');
if (!$del) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Database error']);
  exit;
}
$del->bind_param('s', $serviceName);
$del->execute();

if ($del->affected_rows > 0) {
  echo json_encode(['success' => true, 'message' => 'Service deleted', 'service_name' => $serviceName]);
} else {
  echo json_encode(['success' => false, 'message' => 'Service not found']);
}
$del->close();
?>
