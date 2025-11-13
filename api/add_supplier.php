<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// Check authentication
if (!isset($_SESSION['email'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Not authenticated']);
  exit;
}

// Get form data
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$material = isset($_POST['material']) ? trim($_POST['material']) : '';
$sales_contact = isset($_POST['sales_contact']) ? trim($_POST['sales_contact']) : '';
$contact_number = isset($_POST['contact_number']) ? trim($_POST['contact_number']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$address = isset($_POST['address']) ? trim($_POST['address']) : '';
$city = isset($_POST['city']) ? trim($_POST['city']) : '';
$state = isset($_POST['state']) ? trim($_POST['state']) : '';
$location_type = isset($_POST['location_type']) ? trim($_POST['location_type']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
$service = isset($_POST['service']) ? trim($_POST['service']) : '';
$latitude = isset($_POST['latitude']) ? trim($_POST['latitude']) : '';
$longitude = isset($_POST['longitude']) ? trim($_POST['longitude']) : '';
// ...existing code...

// Validate required fields
if ($name === '' || $service === '') {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Name and service are required']);
  exit;
}

// Require latitude and longitude (manual entry)
if ($latitude === '' || $longitude === '') {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Latitude and longitude are required']);
  exit;
}

if (!is_numeric($latitude) || !is_numeric($longitude)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Latitude and longitude must be numeric']);
  exit;
}

$latVal = floatval($latitude);
$lngVal = floatval($longitude);

// Insert supplier
// Include latitude and longitude in the insert
$stmt = $conn->prepare('INSERT INTO suppliers (name, material, sales_contact, contact_number, email, address, city, state, location_type, notes, service, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

if (!$stmt) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Database error']);
  exit;
}

$stmt->bind_param('sssssssssssdd', $name, $material, $sales_contact, $contact_number, $email, $address, $city, $state, $location_type, $notes, $service, $latVal, $lngVal);

if ($stmt->execute()) {
  echo json_encode(['success' => true, 'message' => 'Supplier added successfully', 'supplier_id' => $stmt->insert_id]);
} else {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Failed to add supplier']);
}

$stmt->close();
?>
