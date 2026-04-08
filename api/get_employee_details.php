<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!$user_id) {
  echo json_encode(['success' => false, 'error' => 'missing_user_id']);
  exit;
}

if (!isset($_SESSION['email'])) {
  echo json_encode(['success' => false, 'error' => 'not_authenticated']);
  exit;
}

try {
  $stmt = $conn->prepare('SELECT detail, items FROM employee_details WHERE user_id = ? ORDER BY detail ASC, id ASC');
  $stmt->bind_param('i', $user_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $out = [];
  while ($row = $res->fetch_assoc()) {
    // normalize detail name: remove trailing plus signs and accidental 'Save' suffix
    $d = $row['detail'];
    // remove trailing occurrences of 'Save' (with optional preceding +/space)
    $d = preg_replace('/(?:\s|\+)*(?:Save)?$/i', '', $d);
    // remove any remaining trailing pluses
    $d = trim(preg_replace('/\++$/', '', $d));
    $d = trim($d);
    $it = $row['items'];
    if ($d === '') continue;
    if (!isset($out[$d])) $out[$d] = [];
    $out[$d][] = $it;
  }
  $stmt->close();
  echo json_encode(['success' => true, 'data' => $out]);
} catch (Exception $e) {
  echo json_encode(['success' => false, 'error' => 'exception', 'msg' => $e->getMessage()]);
}

