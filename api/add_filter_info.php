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

function json_exit_add_filter($payload, $status = 200) {
    http_response_code($status);
    $buffer = ob_get_clean();
    if ($buffer && trim($buffer) !== '') {
        $payload['raw'] = $buffer;
    }
    echo json_encode($payload);
    exit();
}

function ensure_filter_life_column($conn) {
    static $ensured = false;
    if ($ensured) {
        return;
    }
    try {
        $check = $conn->query("SHOW COLUMNS FROM filter_info LIKE 'filter_life'");
        $hasColumn = $check && $check->num_rows > 0;
        if ($check) {
            $check->close();
        }
        if (!$hasColumn) {
            $conn->query("ALTER TABLE filter_info ADD COLUMN filter_life DECIMAL(10,1) NULL AFTER hours");
        }
        $ensured = true;
    } catch (Throwable $e) {
        error_log('[add_filter_info] Unable to ensure filter_life column: ' . $e->getMessage());
    }
}

function fetch_equipment_hours($conn, $equipmentId) {
    $hours = 0;
    if ($equipmentId <= 0) {
        return $hours;
    }
    if ($stmt = $conn->prepare('SELECT COALESCE(current_hours, 0) AS ch FROM equipments WHERE equipment_id = ? LIMIT 1')) {
        $stmt->bind_param('i', $equipmentId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $row = $res->fetch_assoc();
            if ($row && isset($row['ch'])) {
                $hours = (float) $row['ch'];
            }
        }
        $stmt->close();
    }
    return $hours;
}

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    json_exit_add_filter(['success' => false, 'error' => 'Unauthorized'], 401);
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
    json_exit_add_filter(['success' => false, 'error' => 'Forbidden'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit_add_filter(['success' => false, 'error' => 'Invalid request method'], 405);
}

ensure_filter_life_column($conn);

$equipment_id = isset($_POST['equipment_id']) ? (int) $_POST['equipment_id'] : 0;
$filter_name = isset($_POST['filter_name']) ? trim((string) $_POST['filter_name']) : '';
$filter_date = isset($_POST['filter_date']) ? trim((string) $_POST['filter_date']) : '';
$hours_input = isset($_POST['hours']) ? trim((string) $_POST['hours']) : '';
$filter_life_input = isset($_POST['filter_life']) ? trim((string) $_POST['filter_life']) : '';
$part_number = isset($_POST['part_number']) ? trim((string) $_POST['part_number']) : '';
$make = isset($_POST['make']) ? trim((string) $_POST['make']) : '';

if (!$equipment_id || $filter_name === '') {
    json_exit_add_filter(['success' => false, 'error' => 'Missing equipment_id or filter_name'], 400);
}

if ($filter_date === '') {
    $filter_date = null;
}

if ($hours_input === '') {
    $hours_input = (string) fetch_equipment_hours($conn, $equipment_id);
}

$hours_param = $hours_input === '' ? '' : $hours_input;
$filter_life_param = $filter_life_input === '' ? '' : $filter_life_input;

$sql = 'INSERT INTO filter_info (equipment_id, filter_name, filter_date, hours, filter_life, part_number, make) VALUES (?, ?, ?, NULLIF(?, ""), NULLIF(?, ""), ?, ?)';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    json_exit_add_filter(['success' => false, 'error' => 'Failed to prepare statement'], 500);
}
$stmt->bind_param('issssss', $equipment_id, $filter_name, $filter_date, $hours_param, $filter_life_param, $part_number, $make);
$ok = $stmt->execute();
$insertId = $stmt->insert_id;
if (!$ok) {
    $err = $stmt->error;
    $stmt->close();
    json_exit_add_filter(['success' => false, 'error' => $err ?: 'Insert failed'], 500);
}
$stmt->close();

$row = null;
if ($rowStmt = $conn->prepare('SELECT filter_id, equipment_id, filter_name, filter_date, hours, filter_life, part_number, make FROM filter_info WHERE filter_id = ? LIMIT 1')) {
    $rowStmt->bind_param('i', $insertId);
    $rowStmt->execute();
    $res = $rowStmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $rowStmt->close();
}

json_exit_add_filter(['success' => true, 'row' => $row]);
