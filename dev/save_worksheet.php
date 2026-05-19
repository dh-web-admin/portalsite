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

echo json_encode(['success'=>true,'path'=> $fname]);
