<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
header('Content-Type: application/json');

if (empty($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$part_name = isset($_POST['part_name']) ? trim($_POST['part_name']) : '';
$make = isset($_POST['make']) ? trim($_POST['make']) : '';
$model = isset($_POST['model']) ? trim($_POST['model']) : '';

if ($part_name === '' || $make === '' || $model === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing part name, make, or model.']);
    exit();
}

$stmt = $conn->prepare('DELETE FROM part_specifications WHERE part_name = ? AND make = ? AND model = ?');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare delete statement.']);
    exit();
}

$stmt->bind_param('sss', $part_name, $make, $model);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete make specification.']);
    exit();
}

echo json_encode(['success' => true]);
