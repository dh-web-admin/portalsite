<?php
require_once __DIR__ . '/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

if (!empty($isProduction)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not allowed in production']);
    exit;
}

header('Content-Type: application/json');

// prefer explicit marker
$hasDummy = ($conn->query("SHOW COLUMNS FROM users LIKE 'is_dummy'") && $conn->query("SHOW COLUMNS FROM users LIKE 'is_dummy'")->num_rows > 0);

if ($hasDummy) {
    $stmt = $conn->prepare('DELETE FROM users WHERE is_dummy = 1');
    $ok = $stmt->execute();
    if ($ok) echo json_encode(['success' => true]); else echo json_encode(['success'=>false,'error'=>$stmt->error]);
    exit;
} else {
    // fallback to email tag
    $like = '%+dev@example.test';
    $stmt = $conn->prepare('DELETE FROM users WHERE email LIKE ?');
    $stmt->bind_param('s', $like);
    $ok = $stmt->execute();
    if ($ok) echo json_encode(['success' => true]); else echo json_encode(['success'=>false,'error'=>$stmt->error]);
    exit;
}
