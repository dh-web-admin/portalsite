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

$service = isset($_GET['service']) ? trim($_GET['service']) : '';

if ($service === '') {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Service parameter required']);
  exit;
}

// Fetch suppliers missing coordinates for the specified service
$stmt = $conn->prepare('SELECT id, name, material, sales_contact, contact_number, email, address, city, state, service FROM suppliers WHERE service = ? AND (latitude IS NULL OR longitude IS NULL) ORDER BY name ASC');
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Database error']);
  exit;
}

$stmt->bind_param('s', $service);
$stmt->execute();
$result = $stmt->get_result();

$suppliers = [];
if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    // Identify which fields are missing
    $missingFields = [];
    if (empty($row['address'])) $missingFields[] = 'address';
    if (empty($row['city'])) $missingFields[] = 'city';
    if (empty($row['state'])) $missingFields[] = 'state';
    
    $row['missing_fields'] = $missingFields;
    $suppliers[] = $row;
  }
}

$stmt->close();

echo json_encode(['success' => true, 'suppliers' => $suppliers, 'count' => count($suppliers)]);
?>
