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

require_edit_api('equipments');

function json_exit_del($arr, $status = 200){
    http_response_code($status);
    $buf = ob_get_clean();
    if ($buf && is_string($buf) && trim($buf) !== '') $arr['raw'] = $buf;
    echo json_encode($arr);
    exit();
}

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    json_exit_del(['success' => false, 'message' => 'Unauthorized'], 401);
}

$role = 'laborer';
$email = $_SESSION['email'];
if ($stmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1')) {
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $role = $user && isset($user['role']) ? $user['role'] : 'laborer';
    $stmt->close();
}

if (!can_access($role, 'equipments')) {
    json_exit_del(['success' => false, 'message' => 'Forbidden'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit_del(['success' => false, 'message' => 'Method not allowed'], 405);
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) json_exit_del(['success' => false, 'message' => 'Invalid id'], 400);

$stmt = $conn->prepare('DELETE FROM equipment_oil_parts WHERE id=?');
if (!$stmt) json_exit_del(['success' => false, 'message' => 'DB prepare failed'], 500);
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
if (!$ok) { $err = $stmt->error; $stmt->close(); json_exit_del(['success'=>false,'message'=>'Delete failed: '.$err], 500); }
$stmt->close();

json_exit_del(['success' => true, 'id' => $id], 200);

?>
