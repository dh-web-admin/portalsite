<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json');

if (isset($conn)) $GLOBALS['conn'] = $conn;
require_edit_api('maps');

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
  $location_name = isset($_POST['location_name']) ? trim($_POST['location_name']) : null;
  $color = isset($_POST['color']) ? trim($_POST['color']) : null;
  $sales_contact = isset($_POST['sales_contact']) ? trim($_POST['sales_contact']) : null;
  $contact_number = isset($_POST['contact_number']) ? trim($_POST['contact_number']) : null;
  $email = isset($_POST['email']) ? trim($_POST['email']) : null;
  $address = isset($_POST['address']) ? trim($_POST['address']) : null;
  $city = isset($_POST['city']) ? trim($_POST['city']) : null;
  $state = isset($_POST['state']) ? trim($_POST['state']) : null;
  $location_type = isset($_POST['location_type']) ? trim($_POST['location_type']) : null;
  $supply_method = isset($_POST['supply_method']) ? trim($_POST['supply_method']) : null;
  $location_phone = isset($_POST['location_phone']) ? trim($_POST['location_phone']) : null;
  $latitude = isset($_POST['latitude']) ? trim($_POST['latitude']) : null;
  $longitude = isset($_POST['longitude']) ? trim($_POST['longitude']) : null;
  $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
  $service = isset($_POST['service']) ? trim($_POST['service']) : null;
  
  // Prepare UPDATE statement (include location_name and color)
      $sql = "UPDATE suppliers SET 
        name = ?,
        location_name = ?,
        pin_color = ?,
        material = ?,
        sales_contact = ?,
        contact_number = ?,
        location_phone = ?,
        email = ?,
        address = ?,
        city = ?,
        state = ?,
        location_type = ?,
        supply_method = ?,
        notes = ?,
        service = ?,
        latitude = ?,
        longitude = ?
        WHERE id = ?";
  
  $stmt = $conn->prepare($sql);
  $latParam = is_numeric($latitude) ? floatval($latitude) : null;
  $lngParam = is_numeric($longitude) ? floatval($longitude) : null;

  // Types: 15 strings, 2 doubles, 1 integer (added location_name and color)
  $types = "sssssssssssssssddi";
  $stmt->bind_param(
    $types,
    $name,
    $location_name,
    $color,
    $material,
    $sales_contact,
    $contact_number,
    $location_phone,
    $email,
    $address,
    $city,
    $state,
    $location_type,
    $supply_method,
    $notes,
    $service,
    $latParam,
    $lngParam,
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
