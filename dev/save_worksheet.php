<?php
require_once __DIR__ . '/../session_init.php';

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

$dir = __DIR__ . '/worksheets';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$ts = date('Ymd_His');
$fname = sprintf('%s/worksheet_%s_%s.json', $dir, $uid ? $uid : preg_replace('/[^a-z0-9_\-]/i','_', $user), $ts);

file_put_contents($fname, json_encode(['meta'=>['saved_by'=>$user,'user_id'=>$uid,'ts'=>$ts],'payload'=>$data], JSON_PRETTY_PRINT));

$db_inserted = false;
// Try to also persist to database if config is available
try {
    require_once __DIR__ . '/../config/config.php'; // provides $conn (mysqli)

    // derive week_start and week_end from rows if present
    $week_start = null;
    $week_end = null;
    if (isset($data['rows']) && is_array($data['rows'])) {
        $week_start = $data['rows'][0]['date'] ?? null;
        $week_end = end($data['rows'])['date'] ?? null;
    }

    $total_hours = isset($data['total_hours']) ? (float)$data['total_hours'] : 0.0;
    $payload_json = json_encode($data);

    if (isset($conn) && $conn instanceof mysqli) {
        $uid_param = $uid ? (int)$uid : 0;
        $stmt = $conn->prepare(
            "INSERT INTO worksheets (user_id, user_email, week_start, week_end, total_hours, payload, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
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

echo json_encode(['success'=>true,'path'=> $fname, 'db_inserted' => $db_inserted]);
