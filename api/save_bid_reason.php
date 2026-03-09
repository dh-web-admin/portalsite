<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$reason = trim($_POST['reason'] ?? '');
if ($reason === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Reason is required']);
    exit;
}

try {
    // Ensure table exists
    $conn->query("CREATE TABLE IF NOT EXISTS bid_reasons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reason VARCHAR(500) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_reason (reason(255))
    )");

    // Check if it already exists
    $check = $conn->prepare('SELECT id FROM bid_reasons WHERE LOWER(reason) = LOWER(?) LIMIT 1');
    $check->bind_param('s', $reason);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();

    if ($exists) {
        echo json_encode(['success' => true, 'is_new' => false, 'message' => 'Reason already exists']);
        exit;
    }

    $stmt = $conn->prepare('INSERT INTO bid_reasons (reason) VALUES (?)');
    $stmt->bind_param('s', $reason);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'is_new' => true, 'id' => $conn->insert_id]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save reason']);
}
