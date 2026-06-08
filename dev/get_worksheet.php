<?php
require_once __DIR__ . '/../session_init.php';

header('Content-Type: application/json');

$week = $_GET['week_start'] ?? null; // expected YYYY-MM-DD (Sunday)
if (!$week) {
    echo json_encode(['success'=>false,'error'=>'week_start required']);
    exit;
}

$user = $_SESSION['email'] ?? 'anonymous';
$uid = $_SESSION['user_id'] ?? null;

$dir = __DIR__ . '/worksheets';
// First try database lookup (if config available)
try {
    require_once __DIR__ . '/../config/config.php'; // provides $conn
    if (isset($conn) && $conn instanceof mysqli) {
        $week_start = $week;
        $uid_param = $uid ? (int)$uid : 0;
        $stmt = $conn->prepare("SELECT payload FROM worksheets WHERE week_start = ? AND (user_id = ? OR user_email = ?) ORDER BY updated_at DESC, created_at DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('sis', $week_start, $uid_param, $user);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($res && $row = $res->fetch_assoc()) {
                    $payload = json_decode($row['payload'], true);
                    if ($payload && isset($payload['rows'])) {
                        echo json_encode(['success'=>true,'data'=>$payload]);
                        exit;
                    }
                }
            }
            $stmt->close();
        }
    }
} catch (Throwable $e) {
    // ignore DB errors and fall back to filesystem
}

if (!is_dir($dir)) {
    echo json_encode(['success'=>true,'data'=>null]);
    exit;
}

$files = glob($dir . '/worksheet_*');
if (!$files) { echo json_encode(['success'=>true,'data'=>null]); exit; }

$best = null;
foreach ($files as $f) {
    if (!is_readable($f)) continue;
    $txt = file_get_contents($f);
    $j = json_decode($txt, true);
    if (!$j || !isset($j['payload']['rows'])) continue;
    $payload = $j['payload'];
    // find first row date and compute its sunday
    $rows = $payload['rows'];
    if (!isset($rows[0]['date'])) continue;
    $d = $rows[0]['date'];
    $ts = strtotime($d);
    if ($ts === false) continue;
    // compute sunday of that date
    $dow = (int)date('w', $ts);
    $sundayTs = $ts - ($dow * 86400);
    $sunday = date('Y-m-d', $sundayTs);
    if ($sunday !== $week) continue;

    // optionally filter by user id/email in meta
    $metaOk = true;
    if ($uid && isset($j['meta']['user_id'])) { $metaOk = ($j['meta']['user_id'] == $uid); }
    elseif (isset($j['meta']['saved_by'])) { $metaOk = (stripos($j['meta']['saved_by'], $user) !== false); }
    if (!$metaOk) continue;

    // choose the newest by meta.ts or file mtime
    $tsKey = $j['meta']['ts'] ?? null;
    $fileTime = filemtime($f);
    $score = $fileTime;
    if ($tsKey) $score = max($score, intval($tsKey));
    if (!$best || $score > $best['score']) {
        $best = ['score'=>$score, 'file'=>$f, 'payload'=>$payload];
    }
}

if ($best) {
    echo json_encode(['success'=>true,'data'=>$best['payload']]);
} else {
    echo json_encode(['success'=>true,'data'=>null]);
}

exit;

?>
