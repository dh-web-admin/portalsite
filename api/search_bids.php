<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['email'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Not authenticated']);
  exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
  echo json_encode(['success' => true, 'results' => []]);
  exit;
}

// Search across multiple columns for the query
$like = '%' . $conn->real_escape_string($q) . '%';
$sql = "SELECT bid_id, dhss_project_number, project_name, city, general_contractor, general_contractor_name FROM bids WHERE 
  dhss_project_number LIKE '$like' OR 
  project_name LIKE '$like' OR 
  city LIKE '$like' OR 
  general_contractor LIKE '$like' OR 
  general_contractor_name LIKE '$like' 
ORDER BY bid_id DESC LIMIT 10";

$results = [];
$res = $conn->query($sql);
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $results[] = $row;
  }
}
echo json_encode(['success' => true, 'results' => $results]);
