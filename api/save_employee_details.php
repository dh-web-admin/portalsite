<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'error' => 'invalid_method']);
  exit;
}

$input = file_get_contents('php://input');
if (!$input) {
  echo json_encode(['success' => false, 'error' => 'no_input']);
  exit;
}

$data = json_decode($input, true);
if (!$data) {
  echo json_encode(['success' => false, 'error' => 'invalid_json']);
  exit;
}

$user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;
$payload = isset($data['payload']) && is_array($data['payload']) ? $data['payload'] : [];

if (!$user_id) {
  echo json_encode(['success' => false, 'error' => 'missing_user_id']);
  exit;
}

// Basic permission: ensure current user is logged in
if (!isset($_SESSION['email'])) {
  echo json_encode(['success' => false, 'error' => 'not_authenticated']);
  exit;
}

// We'll update entries for the details provided. Avoid wiping other details unintentionally.
try {
  $conn->begin_transaction();

  // prepare statements: delete by user+detail, and insert
  $delDetail = $conn->prepare('DELETE FROM employee_details WHERE user_id = ? AND detail = ?');
  $ins = $conn->prepare('INSERT INTO employee_details (user_id, detail, items) VALUES (?,?,?)');

  // Track whether we processed any detail keys
  $processed = 0;
  foreach ($payload as $detail => $items) {
    if (!is_array($items)) continue;
    $processed++;
    // normalize detail name (remove trailing plus signs and accidental 'Save' text)
    $clean_detail = (string)$detail;
    // remove trailing occurrences of 'Save' (with optional preceding +/space)
    $clean_detail = preg_replace('/(?:\s|\+)*(?:Save)?$/i', '', $clean_detail);
    // remove any remaining trailing pluses and trim
    $clean_detail = trim(preg_replace('/\++$/', '', $clean_detail));

    // remove existing rows for this detail
    $delDetail->bind_param('is', $user_id, $clean_detail);
    $delDetail->execute();

    // If items array is empty, that means user cleared this detail — keep it deleted.
    if (count($items) === 0) {
      continue;
    }

    // insert the new ones
    foreach ($items as $it) {
      $it = trim((string)$it);
      if ($it === '') continue;
      $ins->bind_param('iss', $user_id, $clean_detail, $it);
      $ins->execute();
    }
  }

  // If payload did not include any detail keys, do nothing (avoid deleting)
  // If payload included keys but all arrays were empty, nothing was deleted.
  $delDetail->close();
  $ins->close();
  $conn->commit();
  echo json_encode(['success' => true]);
} catch (Exception $e) {
  if ($conn->errno) $conn->rollback();
  echo json_encode(['success' => false, 'error' => 'exception', 'msg' => $e->getMessage()]);
}
