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

$field = isset($_GET['field']) ? trim($_GET['field']) : '';

// Whitelist of allowed fields to prevent SQL injection
$allowedFields = ['name', 'material', 'location_type', 'sales_contact', 'contact_number', 'email', 'address', 'city', 'state'];

if (!in_array($field, $allowedFields)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Invalid field']);
  exit;
}

// Fetch distinct non-empty values for the specified field
$sql = "SELECT DISTINCT $field FROM suppliers WHERE $field IS NOT NULL AND $field != '' ORDER BY $field ASC";
$result = $conn->query($sql);

$values = [];
if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $values[] = $row[$field];
  }
}

echo json_encode(['success' => true, 'values' => $values]);
?>
