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

$mechanic_diagnosis = $_POST['mechanic_diagnosis'] ?? '';
$date_repaired = $_POST['date_repaired'] ?? null;
$repair_mechanic = $_POST['repair_mechanic'] ?? '';
$parts_fixed = $_POST['parts_fixed'] ?? '';
$pictures = $_POST['pictures'] ?? '';

// Update the issue record
$stmt = $conn->prepare('UPDATE equipment_history SET mechanic_diagnosis = ?, date_repaired = ?, repair_mechanic = ?, parts_fixed = ?, pictures = ? WHERE id = ?');
$stmt->bind_param('sssssi', $mechanic_diagnosis, $date_repaired, $repair_mechanic, $parts_fixed, $pictures, $issue_id);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update issue.']);
    exit();
}

echo json_encode(['success' => true]);

