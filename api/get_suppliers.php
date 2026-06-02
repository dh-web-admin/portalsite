<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

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

// Fetch suppliers filtered by service (case-insensitive fallback)
$stmt = $conn->prepare('SELECT id, name, material, sales_contact, contact_number, location_phone, email, address, city, state, location_type, supply_method, notes, service, latitude, longitude, pin_color AS color, location_name FROM suppliers WHERE service = ? ORDER BY name ASC');
if ($stmt) {
  $stmt->bind_param('s', $service);
  $stmt->execute();
  $result = $stmt->get_result();
  $suppliers = [];
  if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $suppliers[] = $row;
    }
  }
  $stmt->close();
} else {
  $suppliers = [];
}

// If no suppliers found, try a case-insensitive match as a fallback
if (empty($suppliers)) {
  $stmt2 = $conn->prepare('SELECT id, name, material, sales_contact, contact_number, location_phone, email, address, city, state, location_type, supply_method, notes, service, latitude, longitude, pin_color AS color, location_name FROM suppliers WHERE LOWER(service) = LOWER(?) ORDER BY name ASC');
  if ($stmt2) {
    $stmt2->bind_param('s', $service);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    if ($res2 && $res2->num_rows > 0) {
      while ($r = $res2->fetch_assoc()) $suppliers[] = $r;
    }
    $stmt2->close();
  }
}

// Also include clients that have the same client_type as this service, to surface companies stored only in `clients`
$merged = $suppliers;
$stmt3 = $conn->prepare('SELECT client_id, client_name, contact_phone, client_email, client_address, city, state, website, notes, client_type, current_employer FROM clients WHERE LOWER(client_type) = LOWER(?)');
if ($stmt3) {
  $stmt3->bind_param('s', $service);
  $stmt3->execute();
  $res3 = $stmt3->get_result();
  if ($res3) {
    while ($c = $res3->fetch_assoc()) {
      $company = trim($c['current_employer'] ?: $c['client_name']);
      if ($company === '') continue;
      // avoid duplicates by name (case-insensitive)
      $found = false;
      foreach ($merged as $ms) {
        if (isset($ms['name']) && strcasecmp(trim($ms['name']), $company) === 0) { $found = true; break; }
      }
      if ($found) continue;
      $merged[] = [
        'id' => null,
        'name' => $company,
        'material' => '',
        'sales_contact' => $c['client_name'] ?: '',
        'contact_number' => $c['contact_phone'] ?: '',
        'location_phone' => '',
        'email' => $c['client_email'] ?: '',
        'address' => $c['client_address'] ?: '',
        'city' => $c['city'] ?: '',
        'state' => $c['state'] ?: '',
        'location_type' => '',
        'supply_method' => '',
        'notes' => $c['notes'] ?: '',
        'service' => $c['client_type'] ?: $service,
        'latitude' => null,
        'longitude' => null,
        'color' => null,
        'location_name' => $c['current_employer'] ?: ''
      ];
    }
  }
  $stmt3->close();
}

echo json_encode(['success' => true, 'suppliers' => $merged]);
?>
