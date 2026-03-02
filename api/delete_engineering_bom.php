<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['id']) || !is_numeric($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing BOM id']);
    exit();
}

$bomId = intval($input['id']);

$stmt = $conn->prepare("SELECT id, file_path FROM engineering_bom WHERE id = ?");
$stmt->bind_param('i', $bomId);
$stmt->execute();
$result = $stmt->get_result();
$bom = $result->fetch_assoc();
$stmt->close();

if (!$bom) {
    echo json_encode(['success' => false, 'message' => 'BOM not found']);
    exit();
}

if (file_exists($bom['file_path'])) { unlink($bom['file_path']); }

$stmt = $conn->prepare("DELETE FROM engineering_bom WHERE id = ?");
$stmt->bind_param('i', $bomId);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'BOM deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete BOM']);
}
$stmt->close();
?>
