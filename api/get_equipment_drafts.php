<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$email = $_SESSION['email'];

$stmt = $conn->prepare('SELECT id, equipment_name, equipment_number, equipment_type, created_at FROM draft_equipment WHERE created_by = ? ORDER BY created_at DESC');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

$drafts = [];
while ($row = $result->fetch_assoc()) {
    $drafts[] = $row;
}
$stmt->close();

echo json_encode(['success' => true, 'drafts' => $drafts]);
?>
