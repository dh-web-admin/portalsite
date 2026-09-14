<?php
// Adds one supplier/price row for a part on the Hydraulic Systems Inventory
// page. A part can have several of these — that's the point, to compare
// suppliers — so there's no uniqueness constraint to fight here.
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

$supplierName = trim((string) ($_POST['supplier_name'] ?? ''));
$unitPriceRaw = trim((string) ($_POST['unit_price'] ?? ''));
$unitPrice = $unitPriceRaw === '' ? null : (string) $unitPriceRaw;

$stmt = $conn->prepare(
    'INSERT INTO hydraulic_inventory_suppliers (part_key, supplier_name, unit_price) VALUES (?, ?, ?)'
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}
$stmt->bind_param('sss', $partKey, $supplierName, $unitPrice);
$ok = $stmt->execute();
$id = $stmt->insert_id;
$stmt->close();

if ($ok) {
    echo json_encode(['success' => true, 'id' => $id]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to add supplier']);
}
