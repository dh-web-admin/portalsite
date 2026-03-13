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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$name = '';
$number = trim($data['number'] ?? '');
$type = trim($data['type'] ?? '');

// Create draft_equipment table if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS draft_equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_name VARCHAR(255) NOT NULL,
    equipment_number VARCHAR(100),
    equipment_type VARCHAR(100),
    created_by VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (!$conn->query($createTableSQL)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database setup error']);
    exit;
}

$email = $_SESSION['email'];
$stmt = $conn->prepare('INSERT INTO draft_equipment (equipment_name, equipment_number, equipment_type, created_by) VALUES (?, ?, ?, ?)');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}
$stmt->bind_param('ssss', $name, $number, $type, $email);
$success = $stmt->execute();
$draft_id = $stmt->insert_id;
$stmt->close();

if ($success) {
    echo json_encode(['success' => true, 'draft_id' => $draft_id]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save draft equipment']);
}
?>
