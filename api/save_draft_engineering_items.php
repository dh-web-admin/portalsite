<?php
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$draft_id = isset($input['draft_id']) ? (int)$input['draft_id'] : 0;
$item_ids  = isset($input['item_ids']) && is_array($input['item_ids']) ? $input['item_ids'] : [];

if (!$draft_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid draft ID']);
    exit();
}

// Validate that this draft belongs to the current user
$email = $_SESSION['email'];
$stmt = $conn->prepare('SELECT id FROM draft_equipment WHERE id = ? AND created_by = ? LIMIT 1');
$stmt->bind_param('is', $draft_id, $email);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || !$result->fetch_assoc()) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Draft not found or access denied']);
    exit();
}
$stmt->close();

// Create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS engineering_draft_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    draft_id INT NOT NULL,
    engineering_item_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_draft_id (draft_id)
)");

// Replace all items for this draft
$stmt = $conn->prepare('DELETE FROM engineering_draft_items WHERE draft_id = ?');
$stmt->bind_param('i', $draft_id);
$stmt->execute();
$stmt->close();

$inserted = 0;
if (!empty($item_ids)) {
    $stmt = $conn->prepare('INSERT INTO engineering_draft_items (draft_id, engineering_item_id) VALUES (?, ?)');
    foreach ($item_ids as $raw_id) {
        $item_id_int = (int)$raw_id;
        if ($item_id_int > 0) {
            $stmt->bind_param('ii', $draft_id, $item_id_int);
            $stmt->execute();
            $inserted++;
        }
    }
    $stmt->close();
}

echo json_encode(['success' => true, 'saved' => $inserted]);
