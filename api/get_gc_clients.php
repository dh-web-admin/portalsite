<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

require_edit_api('Bid_tracking');

try {
    $sql = "SELECT client_id, client_name, current_employer, client_email, client_address, union_status, contact_phone, client_type FROM clients WHERE LOWER(client_type) = 'general contractor'";
    $res = $conn->query($sql);
    $clients = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $clients[] = $row;
        }
    }
    echo json_encode(['success' => true, 'clients' => $clients]);
} catch (Throwable $ex) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load clients']);
}
