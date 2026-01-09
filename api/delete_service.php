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
