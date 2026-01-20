<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0');
ob_start();

require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

require_edit_api('Bid_tracking');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  @ob_end_clean();
  echo json_encode(['success'=>false,'message'=>'Method not allowed']);
  exit();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
  $data = $_POST;
}

$bidId = isset($data['bid_id']) ? intval($data['bid_id']) : 0;
$clones = isset($data['clones']) && is_array($data['clones']) ? $data['clones'] : [];

if ($bidId <= 0 || empty($clones)) {
  http_response_code(400);
  @ob_end_clean();
  echo json_encode(['success'=>false,'message'=>'Missing bid_id or clones']);
  exit();
}

// Fetch original row
$orig = null;
$stmt = $conn->prepare('SELECT * FROM bids WHERE bid_id = ? LIMIT 1');
if (!$stmt) {
  http_response_code(500);
  @ob_end_clean();
  echo json_encode(['success'=>false,'message'=>'DB error: '.$conn->error]);
  exit();
}
$stmt->bind_param('i', $bidId);
$stmt->execute();
$res = $stmt->get_result();
$orig = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$orig) {
  http_response_code(404);
  @ob_end_clean();
  echo json_encode(['success'=>false,'message'=>'Original bid not found']);
  exit();
}

// Determine columns for insert
$colsRes = $conn->query('SHOW COLUMNS FROM bids');
$cols = [];
if ($colsRes) {
  while ($c = $colsRes->fetch_assoc()) {
    $f = $c['Field'];
    if (in_array($f, ['bid_id','created_at','updated_at'], true)) continue;
    $cols[] = $f;
  }
}

$inserted = [];
try {
  foreach ($clones as $clone) {
    // Build values from original row, overriding gc fields
    $values = [];
    foreach ($cols as $c) {
      $val = isset($orig[$c]) ? $orig[$c] : null;
      // override contractor fields if provided
      if (isset($clone['general_contractor']) && ($c === 'general_contractor' || strpos($c,'general_contractor')!==false || strpos($c,'gc')!==false)) {
        // attempt to map by exact names when present
        if ($c === 'general_contractor') $val = $clone['general_contractor'];
        elseif ($c === 'gc_name') $val = $clone['gc_name'] ?? $val;
        elseif ($c === 'gc_number') $val = $clone['gc_number'] ?? $val;
        else {
          // fallback: if field contains 'gc' use appropriate available value
          if (strpos($c,'name')!==false && isset($clone['gc_name'])) $val = $clone['gc_name'];
          if (strpos($c,'number')!==false && isset($clone['gc_number'])) $val = $clone['gc_number'];
        }
      }
      // allow explicit per-field overrides (e.g., clone provides fieldName keys)
      if (isset($clone[$c])) $val = $clone[$c];
      $values[] = $val;
    }

    // prepare and execute insert
    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $colList = implode(',', array_map(function($x){ return "`$x`"; }, $cols));
    $sql = "INSERT INTO bids ($colList) VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      throw new Exception('Prepare failed: '.$conn->error);
    }

    // Bind all as strings
    $types = str_repeat('s', count($values));
    $bindParams = array_merge([$types], $values);
    $refs = [];
    foreach ($bindParams as $k => $v) $refs[$k] = &$bindParams[$k];
    call_user_func_array([$stmt, 'bind_param'], $refs);

    if (!$stmt->execute()) {
      throw new Exception('Execute failed: '.$stmt->error);
    }
    $newId = $stmt->insert_id;
    $stmt->close();
    $inserted[] = $newId;
  }
} catch (Exception $e) {
  http_response_code(500);
  @ob_end_clean();
  echo json_encode(['success'=>false,'message'=>'Clone failed: '.$e->getMessage()]);
  exit();
}

@ob_end_clean();
echo json_encode(['success'=>true,'inserted'=>$inserted]);
exit();
