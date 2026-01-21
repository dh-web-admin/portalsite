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

$dhss = isset($_GET['dhss_project_number']) ? trim($_GET['dhss_project_number']) : '';

try {
  if ($dhss !== '') {
    $stmt = $conn->prepare('SELECT id, dhss_project_number, general_contractor, general_contractor_name, general_contractor_number, general_contractor_email, general_contractor_address, winner, created_at FROM general_contractor WHERE dhss_project_number = ? ORDER BY general_contractor ASC');
    if ($stmt) {
      $stmt->bind_param('s', $dhss);
      $stmt->execute();
      $res = $stmt->get_result();
      $items = [];
      while ($row = $res->fetch_assoc()) $items[] = $row;
      $stmt->close();
      echo json_encode(['success' => true, 'contractors' => $items]);
      exit;
    }
  }

  // fallback: return all contractors
  $result = $conn->query('SELECT id, dhss_project_number, general_contractor, general_contractor_name, general_contractor_number, general_contractor_email, general_contractor_address, winner, created_at FROM general_contractor ORDER BY general_contractor ASC');
  $items = [];
  if ($result) {
    while ($row = $result->fetch_assoc()) $items[] = $row;
  }
  echo json_encode(['success' => true, 'contractors' => $items]);
} catch (Throwable $ex) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>