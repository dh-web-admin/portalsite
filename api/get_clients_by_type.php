<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['email'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Not authenticated']);
  exit;
}

$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// Basic query - return clients matching client_type (case-insensitive). Optionally filter by query on name or employer.
$sql = 'SELECT client_id, client_name, current_employer, contact_phone, client_email, client_address, city, state, website, notes, client_type FROM clients';
$params = [];
$conds = [];
if ($type !== '') {
  $conds[] = 'LOWER(client_type) = LOWER(?)';
  $params[] = $type;
}
if ($q !== '') {
  $conds[] = '(LOWER(client_name) LIKE LOWER(?) OR LOWER(current_employer) LIKE LOWER(?))';
  $params[] = '%'.$q.'%';
  $params[] = '%'.$q.'%';
}
if (count($conds) > 0) $sql .= ' WHERE ' . implode(' AND ', $conds);
// When searching, limit results for performance; otherwise return all.
if ($q !== '') {
  $sql .= ' ORDER BY client_name ASC LIMIT 200';
} else {
  $sql .= ' ORDER BY client_name ASC';
}

$out = ['success' => true, 'clients' => []];
if ($stmt = $conn->prepare($sql)) {
  if (count($params) > 0) {
    // build types string
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) {
    $out['clients'][] = $r;
  }
  $stmt->close();
} else {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'DB prepare failed: ' . $conn->error]);
  exit;
}

echo json_encode($out);

?>
