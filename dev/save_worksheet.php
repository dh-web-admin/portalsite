<?php
require_once __DIR__ . '/../session_init.php';

header('Content-Type: application/json');

// Only accept JSON POST
$raw = file_get_contents('php://input');
if (empty($raw)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'No input']);
    exit;
}

$data = json_decode($raw, true);
if ($data === null) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Invalid JSON']);
    exit;
}

$user = $_SESSION['email'] ?? 'anonymous';
$uid = $_SESSION['user_id'] ?? null;

$week_start = null;
$week_end = null;
if (isset($data['week_start']) && is_string($data['week_start'])) {
    $week_start = trim($data['week_start']);
}
if (isset($data['week_end']) && is_string($data['week_end'])) {
    $week_end = trim($data['week_end']);
}

if ((!$week_start || !$week_end) && isset($data['rows']) && is_array($data['rows']) && count($data['rows']) > 0) {
    $week_start = $week_start ?: ($data['rows'][0]['date'] ?? null);
    $lastRow = end($data['rows']);
    $week_end = $week_end ?: ($lastRow['date'] ?? null);
}

$dateRegex = '/^\d{4}-\d{2}-\d{2}$/';
if (!$week_start || !preg_match($dateRegex, $week_start)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Missing or invalid week_start']);
    exit;
}
if (!$week_end || !preg_match($dateRegex, $week_end)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Missing or invalid week_end']);
    exit;
}

$dir = __DIR__ . '/worksheets';
if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    error_log('save_worksheet: unable to create worksheets directory: ' . $dir);
}

$ts = date('Ymd_His');
$identity = $uid ? (string)$uid : preg_replace('/[^a-z0-9_\-]/i', '_', $user);
$fname = sprintf('%s/worksheet_%s_%s_%s.json', $dir, $identity, str_replace('-', '', $week_start), $ts);
$stableWeekFile = sprintf('%s/worksheet_%s_%s_latest.json', $dir, $identity, str_replace('-', '', $week_start));

$payloadForFile = json_encode([
    'meta'=>[
        'saved_by'=>$user,
        'user_id'=>$uid,
        'ts'=>$ts,
        'week_start'=>$week_start,
        'week_end'=>$week_end
    ],
    'payload'=>$data
], JSON_PRETTY_PRINT);

$file_saved = false;
if (is_string($payloadForFile)) {
    $bytesVersioned = @file_put_contents($fname, $payloadForFile);
    $bytesStable = @file_put_contents($stableWeekFile, $payloadForFile);
    $file_saved = ($bytesVersioned !== false) || ($bytesStable !== false);
}

if (!$file_saved) {
    error_log('save_worksheet: file write failed for ' . $fname);
}

$db_inserted = false;
// Try to also persist to database if config is available
try {
    require_once __DIR__ . '/../config/config.php'; // provides $conn (mysqli)

    $total_hours = isset($data['total_hours']) ? (float)$data['total_hours'] : 0.0;
    $payload_json = json_encode($data);

    if (isset($conn) && $conn instanceof mysqli) {
        $uid_param = $uid ? (int)$uid : 0;
        $stmt = $conn->prepare(
            "INSERT INTO worksheets (user_id, user_email, week_start, week_end, total_hours, payload, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE week_end = VALUES(week_end), total_hours = VALUES(total_hours), payload = VALUES(payload), updated_at = NOW()"
        );
        if ($stmt) {
            $stmt->bind_param('isssds', $uid_param, $user, $week_start, $week_end, $total_hours, $payload_json);
            $ok = $stmt->execute();
            if ($ok) {
                $db_inserted = true;
            } else {
                error_log('save_worksheet: db insert failed: ' . $stmt->error);
            }
            $stmt->close();
        } else {
            error_log('save_worksheet: prepare failed: ' . $conn->error);
        }
    }
} catch (Throwable $e) {
    error_log('save_worksheet: exception ' . $e->getMessage());
}

$saved = $file_saved || $db_inserted;
if (!$saved) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to persist worksheet to file system or database'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'path' => $fname,
    'db_inserted' => $db_inserted,
    'file_saved' => $file_saved
]);
