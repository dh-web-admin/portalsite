<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
ob_clean();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check permissions
require_once __DIR__ . '/../partials/permissions.php';
if (!can_edit_page('engineering')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id']) || intval($data['id']) <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid material ID']);
    exit;
}

$id = intval($data['id']);

// First delete all associated parts
$stmtParts = $conn->prepare("DELETE FROM Engineering_material_parts WHERE material_id = ?");
$stmtParts->bind_param('i', $id);
$stmtParts->execute();
$stmtParts->close();

// Then delete the material
$stmt = $conn->prepare("DELETE FROM Engineering_materials WHERE id = ?");
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Material and associated parts deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete material: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
