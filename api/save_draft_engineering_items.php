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
$selection_rows = isset($input['selection_rows']) && is_array($input['selection_rows']) ? $input['selection_rows'] : [];

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

// Child table for per-draft selected item/material/part/version rows
$conn->query("CREATE TABLE IF NOT EXISTS draft_equipment_selections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    draft_equipment_id INT NOT NULL,
    item_id INT NOT NULL,
    material_id INT NULL,
    part_id INT NULL,
    version VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_des_draft (draft_equipment_id),
    INDEX idx_des_item (item_id),
    INDEX idx_des_material (material_id),
    INDEX idx_des_part (part_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Replace all items for this draft
$stmt = $conn->prepare('DELETE FROM engineering_draft_items WHERE draft_id = ?');
$stmt->bind_param('i', $draft_id);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare('DELETE FROM draft_equipment_selections WHERE draft_equipment_id = ?');
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

$savedSelections = 0;
if (!empty($selection_rows)) {
    $stmt = $conn->prepare('INSERT INTO draft_equipment_selections (draft_equipment_id, item_id, material_id, part_id, version) VALUES (?, ?, ?, ?, ?)');
    foreach ($selection_rows as $row) {
        if (!is_array($row)) continue;
        $item_id_int = isset($row['item_id']) ? (int)$row['item_id'] : 0;
        if ($item_id_int <= 0) continue;

        $material_id_int = isset($row['material_id']) ? (int)$row['material_id'] : 0;
        $material_id_val = $material_id_int > 0 ? $material_id_int : null;

        $part_id_int = isset($row['part_id']) ? (int)$row['part_id'] : 0;
        $part_id_val = $part_id_int > 0 ? $part_id_int : null;

        $version_val = null;
        if (isset($row['version'])) {
            $version_raw = trim((string)$row['version']);
            if ($version_raw !== '') {
                $version_val = strtolower($version_raw);
            }
        }

        $stmt->bind_param('iiiis', $draft_id, $item_id_int, $material_id_val, $part_id_val, $version_val);
        if ($stmt->execute()) {
            $savedSelections++;
        }
    }
    $stmt->close();
}

echo json_encode(['success' => true, 'saved' => $inserted, 'saved_selections' => $savedSelections]);
