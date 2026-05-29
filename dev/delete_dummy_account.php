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

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid id']);
    exit;
}

// Verify user exists and is a dummy (prefer is_dummy column, fallback to @dummy.com or +dev@example.test)
$rowStmt = $conn->prepare('SELECT email, name FROM users WHERE id = ? LIMIT 1');
$rowStmt->bind_param('i', $id);
$rowStmt->execute();
$res = $rowStmt->get_result();
if (!($res && $res->num_rows > 0)) {
    echo json_encode(['success' => false, 'error' => 'not found']);
    exit;
}
$user = $res->fetch_assoc();
$rowStmt->close();

$isDummy = false;
$r = $conn->query("SHOW COLUMNS FROM users LIKE 'is_dummy'");
if ($r && $r->num_rows > 0) {
    $chk = $conn->prepare('SELECT is_dummy FROM users WHERE id = ?');
    $chk->bind_param('i', $id);
    $chk->execute();
    $rr = $chk->get_result();
    if ($rr && $rr->num_rows > 0) {
        $d = $rr->fetch_assoc();
        $isDummy = (bool)$d['is_dummy'];
    }
    $chk->close();
} else {
    // fallback patterns
    if (stripos($user['email'], '@dummy.com') !== false || stripos($user['email'], '+dev@example.test') !== false) $isDummy = true;
}

if (!$isDummy) {
    echo json_encode(['success' => false, 'error' => 'not a dummy account']);
    exit;
}

$del = $conn->prepare('DELETE FROM users WHERE id = ? LIMIT 1');
$del->bind_param('i', $id);
if ($del->execute()) echo json_encode(['success' => true]); else echo json_encode(['success'=>false,'error'=>$del->error]);
