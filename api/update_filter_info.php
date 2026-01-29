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
        error_log('[update_filter_info] Unable to ensure filter_hours column: ' . $e->getMessage());
    }
}

// Gracefully surface fatal errors as JSON so the client gets a useful message instead of an empty 500 body
register_shutdown_function(function(){
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        http_response_code(500);
        $payload = [
            'success' => false,
            'error' => 'Server error: ' . $err['message']
        ];
        $buffer = ob_get_clean();
        if ($buffer && trim($buffer) !== '') {
            $payload['raw'] = $buffer;
        }
        echo json_encode($payload);
    }
});

require_once __DIR__ . '/../session_init.php';
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../partials/permissions.php';

require_edit_api('equipments');

function json_exit_update_filter($payload, $status = 200) {
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
        error_log('[update_filter_info] Unable to ensure filter_life column: ' . $e->getMessage());
    }
}

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    json_exit_update_filter(['success' => false, 'error' => 'Unauthorized'], 401);
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
    json_exit_update_filter(['success' => false, 'error' => 'Forbidden'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit_update_filter(['success' => false, 'error' => 'Invalid request method.'], 405);
}

ensure_filter_life_column($conn);
ensure_filter_hours_column($conn);

$filter_id = isset($_POST['filter_id']) ? intval($_POST['filter_id']) : 0;
$filter_name = isset($_POST['filter_name']) ? trim((string) $_POST['filter_name']) : '';
$filter_date = isset($_POST['filter_date']) ? trim((string) $_POST['filter_date']) : '';
$hours_input = isset($_POST['hours']) ? trim((string) $_POST['hours']) : '';
$filter_life_input = isset($_POST['filter_life']) ? trim((string) $_POST['filter_life']) : '';
$part_number_1 = isset($_POST['part_number_1']) ? trim((string) $_POST['part_number_1']) : '';
$make_1 = isset($_POST['make_1']) ? trim((string) $_POST['make_1']) : '';
$part_number_2 = isset($_POST['part_number_2']) ? trim((string) $_POST['part_number_2']) : '';
$make_2 = isset($_POST['make_2']) ? trim((string) $_POST['make_2']) : '';

if ($filter_id <= 0) {
    json_exit_update_filter(['success' => false, 'error' => 'Missing filter_id.'], 400);
}

$filter_date = $filter_date === '' ? null : $filter_date;
$hours_param = $hours_input === '' ? '' : $hours_input;
$filter_life_param = $filter_life_input === '' ? '' : $filter_life_input;

// Look up equipment to calculate persisted filter_hours
$equipment_id = 0;
if ($lookup = $conn->prepare('SELECT equipment_id FROM filter_info WHERE filter_id = ? LIMIT 1')) {
    $lookup->bind_param('i', $filter_id);
    $lookup->execute();
    $res = $lookup->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $equipment_id = $row ? (int) ($row['equipment_id'] ?? 0) : 0;
    $lookup->close();
}

$equipment_hours = 0.0;
if ($equipment_id > 0 && ($hStmt = $conn->prepare('SELECT COALESCE(current_hours,0) AS ch FROM equipments WHERE equipment_id = ? LIMIT 1'))) {
    $hStmt->bind_param('i', $equipment_id);
    $hStmt->execute();
    $r = $hStmt->get_result();
    if ($r) {
        $hr = $r->fetch_assoc();
        if ($hr && isset($hr['ch'])) { $equipment_hours = (float) $hr['ch']; }
    }
    $hStmt->close();
}

$base_hours = $hours_input === '' ? 0.0 : (float) $hours_input;
$filter_hours_val = max(0, $equipment_hours - $base_hours);

$sql = 'UPDATE filter_info SET filter_name = ?, filter_date = ?, hours = NULLIF(?, ""), filter_life = NULLIF(?, ""), part_number_1 = ?, make_1 = ?, part_number_2 = ?, make_2 = ?, filter_hours = ? WHERE filter_id = ?';
$stmt = $conn->prepare($sql);
if (!$stmt) {
    json_exit_update_filter(['success' => false, 'error' => 'Prepare failed: ' . $conn->error], 500);
}
$stmt->bind_param('ssssssssdi', $filter_name, $filter_date, $hours_param, $filter_life_param, $part_number_1, $make_1, $part_number_2, $make_2, $filter_hours_val, $filter_id);
$ok = $stmt->execute();
if (!$ok) {
    $err = $stmt->error;
    $stmt->close();
    json_exit_update_filter(['success' => false, 'error' => $err ?: 'Update failed'], 500);
}
$stmt->close();

$row = null;
if ($rowStmt = $conn->prepare('SELECT filter_id, equipment_id, filter_name, filter_date, hours, filter_life, part_number_1, make_1, part_number_2, make_2, filter_hours FROM filter_info WHERE filter_id = ? LIMIT 1')) {
    $rowStmt->bind_param('i', $filter_id);
    $rowStmt->execute();
    $res = $rowStmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $rowStmt->close();
}

json_exit_update_filter(['success' => true, 'row' => $row]);
