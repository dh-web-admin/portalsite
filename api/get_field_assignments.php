<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (empty($_SESSION['email'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$assignments = [];
$userIds = [];
$userMap = [];
// Table may not exist yet; guard with information_schema
$check = $conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'Project_field_assignment' LIMIT 1");
if ($check && $check->num_rows > 0) {
    if ($stmt = $conn->prepare('SELECT field_name, user_id FROM Project_field_assignment')) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $k = strtolower((string)$row['field_name']);
            $uid = is_null($row['user_id']) ? null : (int)$row['user_id'];
            $assignments[$k] = $uid;
            if ($uid) $userIds[$uid] = $uid;
        }
        $stmt->close();
    }
}

// Resolve user names for assigned ids (if any)
if (!empty($userIds)) {
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $types = str_repeat('i', count($userIds));
    $vals = array_values($userIds);
    $sql = "SELECT id, name, email FROM users WHERE id IN ($placeholders)";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param($types, ...$vals);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $id = (int)$r['id'];
            $userMap[$id] = $r['name'] ?: $r['email'] ?: (string)$id;
        }
        $stmt->close();
    }
}

echo json_encode(['success' => true, 'assignments' => $assignments, 'user_map' => $userMap]);
