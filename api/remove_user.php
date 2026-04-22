<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';
header('Content-Type: application/json; charset=utf-8');

require_edit_api('admin_panel');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);
    exit();
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$email = isset($_POST['email']) ? trim($_POST['email']) : '';

if ($id <= 0 && $email === '') {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Missing id or email']);
    exit();
}

$adminEmail = $_SESSION['email'] ?? '';

// If id provided, delete by id. This supports users without emails (non-user employees).
if ($id > 0) {
    // Prevent deleting self: fetch current admin id and compare
    $adminId = 0;
    if ($adminEmail) {
        $aq = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $aq->bind_param('s', $adminEmail);
        $aq->execute();
        $ares = $aq->get_result();
        if ($ares && $ares->num_rows) {
            $adminId = intval($ares->fetch_assoc()['id']);
        }
        $aq->close();
    }

    if ($adminId && $id === $adminId) {
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'You cannot delete your own account']);
        exit();
    }

    $del = $conn->prepare('DELETE FROM users WHERE id = ? LIMIT 1');
    $del->bind_param('i', $id);
    if ($del->execute()) {
        if ($del->affected_rows > 0) {
            echo json_encode(['success'=>true,'message'=>'User removed']);
        } else {
            http_response_code(404);
            echo json_encode(['success'=>false,'error'=>'User not found']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success'=>false,'error'=>'Delete failed: '.$conn->error]);
    }
    $del->close();
    $conn->close();
    exit();
}

// If no id, fall back to deleting by email (existing behavior)
if ($email === '') {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Missing email']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Invalid email']);
    exit();
}

if ($email === $adminEmail) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'You cannot delete your own account']);
    exit();
}

// delete by email
$del = $conn->prepare('DELETE FROM users WHERE email = ? LIMIT 1');
$del->bind_param('s', $email);
if ($del->execute()) {
    if ($del->affected_rows > 0) {
        echo json_encode(['success'=>true,'message'=>'User removed']);
    } else {
        http_response_code(404);
        echo json_encode(['success'=>false,'error'=>'User not found']);
    }
} else {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Delete failed: '.$conn->error]);
}
$del->close();
$conn->close();
$del->close();
$conn->close();

?>
