<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

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

if (!isset($data['name']) || trim($data['name']) === '') {
    echo json_encode(['success' => false, 'message' => 'Material name is required']);
    exit;
}

$name = trim($data['name']);
$item_id = isset($data['item_id']) ? intval($data['item_id']) : null;

// Create table if it doesn't exist
$createTableSql = "CREATE TABLE IF NOT EXISTS Engineering_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    number INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    item_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_item_id (item_id)
)";

if (!$conn->query($createTableSql)) {
    echo json_encode(['success' => false, 'message' => 'Failed to create table: ' . $conn->error]);
    exit;
}

// Get the next auto-incremented number
$stmt = $conn->prepare("SELECT COALESCE(MAX(number), 0) + 1 as next_number FROM Engineering_materials");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$nextNumber = $row['next_number'];
$stmt->close();

// Insert the material
$stmt = $conn->prepare("INSERT INTO Engineering_materials (number, name, item_id) VALUES (?, ?, ?)");
$stmt->bind_param('isi', $nextNumber, $name, $item_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Material added successfully',
        'id' => $conn->insert_id,
        'number' => $nextNumber,
        'name' => $name
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add material: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
