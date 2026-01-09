<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ob_start();

function ensure_filter_hours_column($conn) {
    static $ensuredHours = false;
    if ($ensuredHours) {
        return;
    }
    try {
        $check = $conn->query("SHOW COLUMNS FROM filter_info LIKE 'filter_hours'");
        $hasColumn = $check && $check->num_rows > 0;
        if ($check) {
            $check->close();
        }
        if (!$hasColumn) {
            $conn->query("ALTER TABLE filter_info ADD COLUMN filter_hours DECIMAL(10,1) NULL AFTER filter_life");
        }
        $ensuredHours = true;
    } catch (Throwable $e) {
        error_log('[reset_filter_hours] Unable to ensure filter_hours column: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/../session_init.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';

function json_exit_reset_filter($payload, $status = 200) {
    http_response_code($status);
    $buffer = ob_get_clean();
    if ($buffer && trim($buffer) !== '') {
        $payload['raw'] = $buffer;
    }
    echo json_encode($payload);
    exit();
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
    json_exit_reset_filter(['success' => false, 'error' => 'Unauthorized'], 401);
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

$GLOBALS['role'] = $role;
require_edit_api('equipments');

if (!can_access($role, 'equipments')) {
    json_exit_reset_filter(['success' => false, 'error' => 'Forbidden'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit_reset_filter(['success' => false, 'error' => 'Invalid request method'], 405);
}

ensure_filter_hours_column($conn);

$filter_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($filter_id <= 0) {
    json_exit_reset_filter(['success' => false, 'error' => 'Invalid filter id'], 400);
}

if (!$stmt = $conn->prepare('SELECT equipment_id FROM filter_info WHERE filter_id = ? LIMIT 1')) {
    json_exit_reset_filter(['success' => false, 'error' => 'Filter lookup failed'], 500);
}
$stmt->bind_param('i', $filter_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    json_exit_reset_filter(['success' => false, 'error' => 'Filter not found'], 404);
}

$equipment_id = isset($row['equipment_id']) ? (int) $row['equipment_id'] : 0;
$hours = fetch_equipment_hours($conn, $equipment_id);
$today = date('Y-m-d');

if (!$update = $conn->prepare('UPDATE filter_info SET hours = ?, filter_date = ?, filter_hours = 0 WHERE filter_id = ?')) {
    json_exit_reset_filter(['success' => false, 'error' => 'Unable to update filter'], 500);
}
$update->bind_param('dsi', $hours, $today, $filter_id);
$ok = $update->execute();
if (!$ok) {
    $err = $update->error;
    $update->close();
    json_exit_reset_filter(['success' => false, 'error' => $err ?: 'Update failed'], 500);
}
$update->close();

$latest = null;
if ($rowStmt = $conn->prepare('SELECT filter_id, equipment_id, filter_name, filter_date, hours, filter_life, part_number, make, filter_hours FROM filter_info WHERE filter_id = ? LIMIT 1')) {
    $rowStmt->bind_param('i', $filter_id);
    $rowStmt->execute();
    $res = $rowStmt->get_result();
    $latest = $res ? $res->fetch_assoc() : null;
    $rowStmt->close();
}

json_exit_reset_filter(['success' => true, 'row' => $latest]);
