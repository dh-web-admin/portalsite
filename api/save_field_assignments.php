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

$raw = $_POST['assignments'] ?? null;
if (!$raw) {
    echo json_encode(['success' => false, 'message' => 'Missing assignments']);
    exit;
}

$decoded = json_decode($raw, true);
if (!is_array($decoded)) {
    echo json_encode(['success' => false, 'message' => 'Invalid assignments payload']);
    exit;
}

// Ensure table exists
$createSql = "CREATE TABLE IF NOT EXISTS Project_field_assignment (
  id INT AUTO_INCREMENT PRIMARY KEY,
  field_name VARCHAR(191) NOT NULL,
  user_id INT DEFAULT NULL,
  assigned_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY (field_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($createSql);

// Use transaction for safety
$conn->begin_transaction();
try {
    // Upsert each assignment
    $assignStmt = $conn->prepare("INSERT INTO Project_field_assignment (field_name, user_id, assigned_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), assigned_by = VALUES(assigned_by), updated_at = CURRENT_TIMESTAMP");
    $assignedBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    foreach ($decoded as $field => $uid) {
        $f = trim((string)$field);
        $userId = is_null($uid) || $uid === '' ? null : (int)$uid;
        $assignStmt->bind_param('sii', $f, $userId, $assignedBy);
        $assignStmt->execute();
    }
    if ($assignStmt) $assignStmt->close();
    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    error_log('save_field_assignments error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
