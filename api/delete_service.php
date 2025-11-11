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

// First, delete all suppliers associated with this service
$delSuppliers = $conn->prepare('DELETE FROM suppliers WHERE service = ?');
if (!$delSuppliers) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Database error deleting suppliers']);
  exit;
}
$delSuppliers->bind_param('s', $serviceName);
$delSuppliers->execute();
$suppliersDeleted = $delSuppliers->affected_rows;
$delSuppliers->close();

// Then, delete the service itself
$del = $conn->prepare('DELETE FROM services WHERE name = ? LIMIT 1');
if (!$del) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Database error deleting service']);
  exit;
}
$del->bind_param('s', $serviceName);
$del->execute();

if ($del->affected_rows > 0) {
  $message = 'Service deleted';
  if ($suppliersDeleted > 0) {
    $message .= ' and ' . $suppliersDeleted . ' associated supplier(s) removed';
  }
  echo json_encode(['success' => true, 'message' => $message, 'service_name' => $serviceName, 'suppliers_deleted' => $suppliersDeleted]);
} else {
  echo json_encode(['success' => false, 'message' => 'Service not found']);
}
$del->close();
?>
