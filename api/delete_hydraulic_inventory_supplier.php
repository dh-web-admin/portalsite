<?php
// Removes one supplier/price row on the Hydraulic Systems Inventory page —
// the necessary counterpart to being able to add several per part.
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json; charset=utf-8');

if (isset($conn)) $GLOBALS['conn'] = $conn;
require_edit_api('engineering');

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid id']);
    exit;
}

$stmt = $conn->prepare('DELETE FROM hydraulic_inventory_suppliers WHERE id = ?');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

echo json_encode(['success' => (bool) $ok, 'affected' => $affected]);
