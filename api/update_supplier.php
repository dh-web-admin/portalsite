<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Check if user is authenticated and has admin/developer role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'developer'])) {
  echo json_encode([
    'success' => false,
    'message' => 'Unauthorized access'
  ]);
  exit;
}

// Validate required fields
if (!isset($_POST['id']) || empty($_POST['id'])) {
  echo json_encode([
    'success' => false,
    'message' => 'Supplier ID is required'
  ]);
  exit;
}

if (!isset($_POST['name']) || empty(trim($_POST['name']))) {
  echo json_encode([
    'success' => false,
    'message' => 'Supplier name is required'
  ]);
  exit;
}

try {
  // Get form data
  $id = intval($_POST['id']);
  $name = trim($_POST['name']);
  $material = isset($_POST['material']) ? trim($_POST['material']) : null;
  $sales_contact = isset($_POST['sales_contact']) ? trim($_POST['sales_contact']) : null;
  $contact_number = isset($_POST['contact_number']) ? trim($_POST['contact_number']) : null;
  $email = isset($_POST['email']) ? trim($_POST['email']) : null;
  $address = isset($_POST['address']) ? trim($_POST['address']) : null;
  $city = isset($_POST['city']) ? trim($_POST['city']) : null;
  $state = isset($_POST['state']) ? trim($_POST['state']) : null;
  $location_type = isset($_POST['location_type']) ? trim($_POST['location_type']) : null;
  $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
  $service = isset($_POST['service']) ? trim($_POST['service']) : null;
  
  // Prepare UPDATE statement
  $sql = "UPDATE suppliers SET 
          name = ?,
          material = ?,
          sales_contact = ?,
          contact_number = ?,
          email = ?,
          address = ?,
          city = ?,
          state = ?,
          location_type = ?,
          notes = ?,
          service = ?
          WHERE id = ?";
  
  $stmt = $conn->prepare($sql);
  $stmt->bind_param(
    "sssssssssssi",
    $name,
    $material,
    $sales_contact,
    $contact_number,
    $email,
    $address,
    $city,
    $state,
    $location_type,
    $notes,
    $service,
    $id
  );
  
  if ($stmt->execute()) {
    echo json_encode([
      'success' => true,
      'message' => 'Supplier updated successfully'
    ]);
  } else {
    echo json_encode([
      'success' => false,
      'message' => 'Failed to update supplier: ' . $stmt->error
    ]);
  }
  
  $stmt->close();
  $conn->close();
  
} catch (Exception $e) {
  echo json_encode([
    'success' => false,
    'message' => 'Error: ' . $e->getMessage()
  ]);
}
?>
