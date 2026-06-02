<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['email'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Not authenticated']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Method not allowed']);
  exit;
}

$clientId = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
if ($clientId <= 0) {
  echo json_encode(['success' => false, 'message' => 'Invalid client_id']);
  exit;
}

// Allowed client columns to sync
$allowed = [
  'client_name','client_number','client_type','union_status','contact_phone','client_email','client_address','city','state','website','notes','family_details','current_employer','previous_employment','past_projects'
];

// Read supplier-provided values from POST and keep only allowed keys
$updates = [];
foreach ($allowed as $col) {
  if (isset($_POST[$col]) && trim($_POST[$col]) !== '') {
    $updates[$col] = trim($_POST[$col]);
  }
}

// Nothing to update
if (count($updates) === 0) {
  echo json_encode(['success' => true, 'message' => 'No fields to sync']);
  exit;
}

// Fetch existing client row
$stmt = $conn->prepare('SELECT ' . implode(',', $allowed) . ' FROM clients WHERE client_id = ? LIMIT 1');
$stmt->bind_param('i', $clientId);
$stmt->execute();
$res = $stmt->get_result();
$existing = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$existing) {
  echo json_encode(['success' => false, 'message' => 'Client not found']);
  exit;
}

// Build final set of columns to update: only update when existing value is NULL or empty
$toSet = [];
$params = [];
foreach ($updates as $col => $val) {
  $cur = isset($existing[$col]) ? trim((string)$existing[$col]) : '';
  if ($cur === '') {
    $toSet[$col] = $val;
    $params[] = $val;
  }
}

if (count($toSet) === 0) {
  echo json_encode(['success' => true, 'message' => 'No empty fields to fill']);
  exit;
}

$sql = 'UPDATE clients SET ' . implode(', ', array_map(function($c){ return "$c = ?"; }, array_keys($toSet))) . ' WHERE client_id = ?';
$types = str_repeat('s', count($params)) . 'i';
$params[] = $clientId;

$stmt = $conn->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'DB prepare failed: ' . $conn->error]);
  exit;
}
$stmt->bind_param($types, ...$params);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
  echo json_encode(['success' => true, 'message' => 'Client updated']);
} else {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
}

?>
