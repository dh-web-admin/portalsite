<?php
// Temporary debug: log incoming requests to help diagnose HTML responses
// (Remove this block after debugging)
$__dbg = [];
$__dbg['time'] = date('c');
$__dbg['script'] = __FILE__;
$__dbg['request_uri'] = $_SERVER['REQUEST_URI'] ?? '';
$__dbg['method'] = $_SERVER['REQUEST_METHOD'] ?? '';
$__dbg['remote_addr'] = $_SERVER['REMOTE_ADDR'] ?? '';
$__dbg['server_name'] = $_SERVER['SERVER_NAME'] ?? '';
$__dbg['cookie_header'] = isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : null;
$__dbg['cookies'] = $_COOKIE ?? [];
$__dbg['get'] = $_GET ?? [];
$__dbg['post_raw_length'] = strlen(file_get_contents('php://input'));
$__hdrs = [];
foreach ($_SERVER as $k => $v) {
    if (strpos($k, 'HTTP_') === 0) {
        $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
        $__hdrs[$name] = $v;
    }
}
$__dbg['headers'] = $__hdrs;
@file_put_contents(__DIR__ . '/update_bid_access.log', json_encode($__dbg) . PHP_EOL, FILE_APPEND);

header('Content-Type: application/json; charset=utf-8');

// Ensure no PHP warnings or whitespace break JSON output
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ob_start();

require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

// (Optional) Better mysqli error reporting during development
// mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_edit_api('Bid_tracking');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    // clear any buffered output to avoid mixing HTML with JSON
    @ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Accept both form-encoded and JSON
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (is_array($data)) $input = $data;
}

$bidId = isset($input['bid_id']) ? intval($input['bid_id']) : 0;
if ($bidId <= 0) {
    http_response_code(400);
    @ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Missing or invalid bid_id']);
    exit();
}

// Discover updatable columns from the table schema (exclude id and timestamps)
$colsRes = $conn->query("SHOW COLUMNS FROM bids");
$allowed = [];
$numericCols = [];
if ($colsRes) {
    while ($c = $colsRes->fetch_assoc()) {
        $f = $c['Field'];
        if (in_array($f, ['bid_id','created_at','updated_at'], true)) continue;
        $allowed[] = $f;
        $t = isset($c['Type']) ? strtolower((string)$c['Type']) : '';
        if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|numeric|float|double|real)\b/', $t)) {
            $numericCols[$f] = true;
        }
    }
}

function normalize_numeric_like_value($raw) {
    if ($raw === null) return null;
    $s = trim((string)$raw);
    if ($s === '') return $raw;
    // Accept comma-separated decimal strings (e.g. 123,123.4565)
    if (!preg_match('/^\$?\s*[+-]?\d[\d,]*(?:\.\d+)?\s*$/', $s)) return $raw;
    $cleaned = str_replace([',', '$', ' '], '', $s);
    if (preg_match('/^[+-]?\d+(?:\.\d+)?$/', $cleaned)) return $cleaned;
    return $raw;
}

// DEBUG: log the schema info for the `status` column so we know its runtime type
$stypeRes = $conn->query("SHOW FULL COLUMNS FROM bids LIKE 'status'");
if ($stypeRes) {
    $sinfo = $stypeRes->fetch_assoc();
    @file_put_contents(__DIR__ . '/update_bid_debug.log', date('c') . " STATUS_COLUMN: " . json_encode($sinfo) . "\n", FILE_APPEND);
}

// Build update list from provided input keys intersecting allowed columns
$updateFields = [];
$values = [];

foreach ($allowed as $col) {
    if (array_key_exists($col, $input)) {
        $updateFields[] = $col;
        $v = $input[$col];
        if ($v !== '' && isset($numericCols[$col])) {
            $v = normalize_numeric_like_value($v);
        }
        if ($v === '') $v = null;
        $values[] = $v;
    }
}

// DEBUG: dump input and computed update fields to a debug log to diagnose missing keys
@file_put_contents(__DIR__ . '/update_bid_debug.log', date('c') . " INPUT: " . json_encode(array_keys($input)) . "\n", FILE_APPEND);
@file_put_contents(__DIR__ . '/update_bid_debug.log', date('c') . " UPDATE_FIELDS: " . json_encode($updateFields) . "\n", FILE_APPEND);
@file_put_contents(__DIR__ . '/update_bid_debug.log', date('c') . " VALUES: " . json_encode($values) . "\n", FILE_APPEND);

if (empty($updateFields)) {
    http_response_code(400);
    @ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'No updatable fields provided']);
    exit();
}

$setParts = array_map(function($c){ return "`" . $c . "` = ?"; }, $updateFields);
$sql = 'UPDATE bids SET ' . implode(', ', $setParts) . ' WHERE bid_id = ? LIMIT 1';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    @ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'DB prepare failed: ' . $conn->error]);
    exit();
}

// Bind params (treat all update fields as strings, bid_id as int)
$types = str_repeat('s', count($values)) . 'i';
$params = array_merge($values, [$bidId]);

$bindParams = array_merge([$types], $params);
$refs = [];
foreach ($bindParams as $k => $v) { $refs[$k] = &$bindParams[$k]; }

call_user_func_array([$stmt, 'bind_param'], $refs);

try {
    $ok = $stmt->execute();
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    @ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Execute failed: ' . $e->getMessage(), 'code' => $e->getCode()]);
    $stmt->close();
    exit();
}

if ($ok === false) {
    http_response_code(500);
    @ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
    $stmt->close();
    exit();
}

$stmt->close();

// Return the updated row
$rstmt = $conn->prepare('SELECT * FROM bids WHERE bid_id = ? LIMIT 1');
if ($rstmt) {
    $rstmt->bind_param('i', $bidId);
    $rstmt->execute();
    $res = $rstmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    @file_put_contents(__DIR__ . '/update_bid_debug.log', date('c') . " RETURNED_ROW: " . json_encode($row) . "\n", FILE_APPEND);
    $rstmt->close();

    @ob_end_clean();
    echo json_encode(['success' => true, 'bid' => $row]);
    exit();
}

@ob_end_clean();
echo json_encode(['success' => true]);
exit();
