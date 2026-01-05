<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../session_init.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';

function json_exit_delete_filter($payload, $status = 200) {
    http_response_code($status);
    $buffer = ob_get_clean();
    if ($buffer && trim($buffer) !== '') {
        $payload['raw'] = $buffer;
    }
    echo json_encode($payload);
    exit();
}

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    json_exit_delete_filter(['success' => false, 'error' => 'Unauthorized'], 401);
}

$role = 'laborer';
$email = $_SESSION['email'];
if ($stmt = $conn->prepare('SELECT role FROM users WHERE email = ? LIMIT 1')) {
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    if ($user && isset($user['role'])) {
        $role = $user['role'];
    }
    $stmt->close();
}

if (!can_access($role, 'equipments')) {
    json_exit_delete_filter(['success' => false, 'error' => 'Forbidden'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit_delete_filter(['success' => false, 'error' => 'Invalid request method'], 405);
}

$filter_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($filter_id <= 0) {
    json_exit_delete_filter(['success' => false, 'error' => 'Invalid filter id'], 400);
}

if (!$stmt = $conn->prepare('DELETE FROM filter_info WHERE filter_id = ? LIMIT 1')) {
    json_exit_delete_filter(['success' => false, 'error' => 'Delete prepare failed'], 500);
}
$stmt->bind_param('i', $filter_id);
$ok = $stmt->execute();
if (!$ok) {
    $err = $stmt->error;
    $stmt->close();
    json_exit_delete_filter(['success' => false, 'error' => $err ?: 'Delete failed'], 500);
}
$affected = $stmt->affected_rows;
$stmt->close();

if ($affected === 0) {
    json_exit_delete_filter(['success' => false, 'error' => 'Filter not found'], 404);
}

json_exit_delete_filter(['success' => true]);
