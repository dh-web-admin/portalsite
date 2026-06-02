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
$service = isset($_GET['service']) ? trim($_GET['service']) : '';

// Whitelist of allowed fields to prevent SQL injection
$allowedFields = ['name', 'material', 'location_type', 'sales_contact', 'contact_number', 'email', 'address', 'city', 'state', 'supply_method', 'location_phone'];

if (!in_array($field, $allowedFields)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Invalid field']);
  exit;
}

// Fetch distinct non-empty values for the specified field, optionally scoped to a service
$sql = "SELECT DISTINCT $field FROM suppliers WHERE $field IS NOT NULL AND $field != ''";
if ($service !== '') {
  $sql .= " AND service = ?";
}
$sql .= " ORDER BY $field ASC";

$values = [];
if ($stmt = $conn->prepare($sql)) {
  if ($service !== '') {
    $stmt->bind_param('s', $service);
  }
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
      $values[] = $row[$field];
    }
  }
  $stmt->close();
} else {
  // Fallback: run a direct query (shouldn't happen because field is whitelisted)
  $result = $conn->query($sql);
  if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $values[] = $row[$field];
    }
  }
}
echo json_encode(['success' => true, 'values' => $values]);
?>
