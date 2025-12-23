<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

// Validate and collect POST data
$issue_id = isset($_POST['issue_id']) ? (int)$_POST['issue_id'] : 0;
if ($issue_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid issue ID.']);
    exit();
}

// Delete the issue record
$stmt = $conn->prepare('DELETE FROM equipment_history WHERE id = ?');
$stmt->bind_param('i', $issue_id);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete issue.']);
    exit();
}

echo json_encode(['success' => true]);

