<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
// Ensure session contains identifying info. If user_id is missing but email exists in session,
// attempt to populate user_id and role from the database as a safe fallback.
if (!isset($_SESSION['user_id']) && isset($_SESSION['email']) && isset($conn)) {
  $emailLookup = $_SESSION['email'];
  $stmtu = $conn->prepare('SELECT id, role FROM users WHERE email = ? LIMIT 1');
  if ($stmtu) {
    $stmtu->bind_param('s', $emailLookup);
    $stmtu->execute();
    $resu = $stmtu->get_result();
    if ($resu && ($urow = $resu->fetch_assoc())) {
      $_SESSION['user_id'] = intval($urow['id']);
      $_SESSION['role'] = $urow['role'] ?? null;
    }
    $stmtu->close();
  }
}

// Only admins, developers, or data_entry users may update coordinates
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'developer', 'data_entry'])) {
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$lat = isset($_POST['latitude']) ? trim($_POST['latitude']) : null;
$lng = isset($_POST['longitude']) ? trim($_POST['longitude']) : null;

if ($id <= 0) {
  echo json_encode(['success' => false, 'message' => 'Invalid supplier id']);
  exit;
}

if ($lat === null || $lng === null || $lat === '' || $lng === '') {
  echo json_encode(['success' => false, 'message' => 'Latitude and longitude are required']);
  exit;
}

// Validate numeric
if (!is_numeric($lat) || !is_numeric($lng)) {
  echo json_encode(['success' => false, 'message' => 'Latitude and longitude must be numeric']);
  exit;
}

$latF = floatval($lat);
$lngF = floatval($lng);

try {
  $stmt = $conn->prepare('UPDATE suppliers SET latitude = ?, longitude = ? WHERE id = ?');
  $stmt->bind_param('ddi', $latF, $lngF, $id);
  if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Coordinates updated']);
  } else {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $stmt->error]);
  }
  $stmt->close();
  $conn->close();
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
}

?>
