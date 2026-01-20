<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// require permission to edit Bid_tracking
try { require_edit_api('Bid_tracking'); } catch (Throwable $ex) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Permission denied']); exit(); }

$input = $_POST;
$bidId = isset($input['bid_id']) ? intval($input['bid_id']) : 0;
if ($bidId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid bid_id']);
    exit();
}

// Delete the bid
$stmt = $conn->prepare('DELETE FROM bids WHERE bid_id = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB prepare failed: ' . $conn->error]);
    exit();
}
$stmt->bind_param('i', $bidId);
try {
    $ok = $stmt->execute();
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Execute failed: ' . $e->getMessage()]);
    exit();
}

if ($ok === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $stmt->error]);
    exit();
}

$stmt->close();

echo json_encode(['success' => true]);
exit();
