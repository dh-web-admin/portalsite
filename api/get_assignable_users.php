<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (empty($_SESSION['email'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'developer'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

$users = [];
if ($stmt = $conn->prepare('SELECT id, name, email FROM users ORDER BY name')) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $users[] = ['id' => (int)$row['id'], 'name' => $row['name'], 'email' => $row['email']];
    }
    $stmt->close();
}

echo json_encode(['success' => true, 'users' => $users]);
