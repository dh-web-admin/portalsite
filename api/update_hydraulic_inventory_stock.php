<?php
// Upserts the "on hand" stock count for one distinct part on the Hydraulic
// Systems inventory page. Keyed by part_key (a hash of part_no + manufacturer
// description + description computed identically in hydraulic_inventory.php),
// not by an editable field, since that's what ties this row back to the
// live rollup of hydraulic_component_specs.
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

header('Content-Type: application/json; charset=utf-8');

if (isset($conn)) $GLOBALS['conn'] = $conn;
require_edit_api('engineering');

$partKey = trim((string) ($_POST['part_key'] ?? ''));
if (!preg_match('/^[0-9a-f]{40}$/', $partKey)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid part key']);
    exit;
}

$onHand = (int) ($_POST['on_hand'] ?? 0);
if ($onHand < 0) { $onHand = 0; }

$stmt = $conn->prepare(
    'INSERT INTO hydraulic_inventory_stock (part_key, on_hand) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE on_hand = VALUES(on_hand)'
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}
$stmt->bind_param('si', $partKey, $onHand);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => (bool) $ok]);
