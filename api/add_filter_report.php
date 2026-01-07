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

function filter_json_exit($arr, $status = 200) {
    http_response_code($status);
    $buf = ob_get_clean();
    if ($buf && is_string($buf) && trim($buf) !== '') {
        $arr['raw'] = $buf;
    }
    echo json_encode($arr);
    exit();
}

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    filter_json_exit(['success' => false, 'message' => 'Unauthorized'], 401);
}

$email = $_SESSION['email'];
$role = 'laborer';
if ($stmt = $conn->prepare('SELECT role FROM users WHERE email=? LIMIT 1')) {
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $role = $user && isset($user['role']) ? $user['role'] : 'laborer';
    $stmt->close();
}

if (!can_access($role, 'equipments')) {
    filter_json_exit(['success' => false, 'message' => 'Forbidden'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    filter_json_exit(['success' => false, 'message' => 'Method not allowed'], 405);
}

function ensure_filter_reports_table(mysqli $conn) {
    static $done = false;
    if ($done) return;
    $sql = "CREATE TABLE IF NOT EXISTS filter_reports (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        equipment_id INT UNSIGNED NOT NULL,
        filter_id INT UNSIGNED NOT NULL,
        filter_name VARCHAR(255) NOT NULL,
        make VARCHAR(255) DEFAULT '',
        part_number VARCHAR(255) DEFAULT '',
        change_date DATETIME NOT NULL,
        equipment_hours DECIMAL(10,2) NOT NULL,
        changed_by VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_equipment (equipment_id),
        INDEX idx_filter (filter_id),
        INDEX idx_change_date (change_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    try {
        $conn->query($sql);
    } catch (Throwable $e) {
        error_log('[filter_reports] create table failed: ' . $e->getMessage());
    }
    $done = true;
}

$equipment_id    = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
$filter_id       = isset($_POST['filter_id']) ? (int)$_POST['filter_id'] : 0;
$filter_name     = isset($_POST['filter_name']) ? trim((string)$_POST['filter_name']) : '';
$make            = isset($_POST['make']) ? trim((string)$_POST['make']) : '';
$part_number     = isset($_POST['part_number']) ? trim((string)$_POST['part_number']) : '';
$change_date_raw = isset($_POST['change_date']) ? trim((string)$_POST['change_date']) : '';
$hours_raw       = isset($_POST['equipment_hours']) ? (string)$_POST['equipment_hours'] : '';
$changed_by      = isset($_POST['changed_by']) ? trim((string)$_POST['changed_by']) : '';

if ($equipment_id <= 0 || $filter_id <= 0) {
    filter_json_exit(['success' => false, 'message' => 'Invalid equipment or filter id'], 400);
}
if ($filter_name === '' || $change_date_raw === '') {
    filter_json_exit(['success' => false, 'message' => 'Filter name and change date are required'], 400);
}

$hours = is_numeric($hours_raw) ? (float)$hours_raw : 0.0;
if ($hours < 0) $hours = 0.0;

$ts = strtotime($change_date_raw);
if ($ts === false) {
    filter_json_exit(['success' => false, 'message' => 'Invalid change date'], 400);
}
$change_dt = date('Y-m-d H:i:s', $ts);
$filter_date = date('Y-m-d', $ts);

// Verify the filter belongs to this equipment
$filterRow = null;
if ($stmt = $conn->prepare('SELECT * FROM filter_info WHERE filter_id=? AND equipment_id=? LIMIT 1')) {
    $stmt->bind_param('ii', $filter_id, $equipment_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $filterRow = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}
if (!$filterRow) {
    filter_json_exit(['success' => false, 'message' => 'Filter not found for this equipment'], 404);
}

// Get current equipment hours
$equip_hours = 0.0;
if ($stmt = $conn->prepare('SELECT COALESCE(current_hours,0) AS ch FROM equipments WHERE equipment_id=? LIMIT 1')) {
    $stmt->bind_param('i', $equipment_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $row = $res->fetch_assoc();
        if ($row && isset($row['ch'])) {
            $equip_hours = (float)$row['ch'];
        }
    }
    $stmt->close();
}

$filter_hours = $equip_hours - $hours;
if ($filter_hours < 0) $filter_hours = 0.0;

ensure_filter_reports_table($conn);

try {
    if (method_exists($conn, 'begin_transaction')) {
        $conn->begin_transaction();
    } else {
        $conn->query('START TRANSACTION');
    }

    // Insert into filter_reports
    if (!($stmt = $conn->prepare('INSERT INTO filter_reports (equipment_id, filter_id, filter_name, make, part_number, change_date, equipment_hours, changed_by) VALUES (?,?,?,?,?,?,?,?)'))) {
        throw new Exception('Prepare insert filter_reports failed: ' . $conn->error);
    }
    $stmt->bind_param('iissssds', $equipment_id, $filter_id, $filter_name, $make, $part_number, $change_dt, $hours, $changed_by);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new Exception('Execute insert filter_reports failed: ' . $err);
    }
    $report_id = $stmt->insert_id;
    $stmt->close();

    // Update filter_info baseline and derived hours
    if (!($stmt2 = $conn->prepare('UPDATE filter_info SET filter_date=?, hours=?, filter_hours=? WHERE filter_id=? AND equipment_id=?'))) {
        throw new Exception('Prepare update filter_info failed: ' . $conn->error);
    }
    $stmt2->bind_param('sddii', $filter_date, $hours, $filter_hours, $filter_id, $equipment_id);
    if (!$stmt2->execute()) {
        $err = $stmt2->error;
        $stmt2->close();
        throw new Exception('Execute update filter_info failed: ' . $err);
    }
    $stmt2->close();

    if (method_exists($conn, 'commit')) {
        $conn->commit();
    } else {
        $conn->query('COMMIT');
    }
} catch (Throwable $e) {
    error_log('[add_filter_report] ' . $e->getMessage());
    if (method_exists($conn, 'rollback')) {
        $conn->rollback();
    } else {
        $conn->query('ROLLBACK');
    }
    filter_json_exit(['success' => false, 'message' => 'Database error while saving filter change'], 500);
}

// Fetch updated filter row to send back to client
$updated = null;
if ($stmt = $conn->prepare('SELECT filter_id, equipment_id, filter_name, filter_date, hours, filter_life, part_number, make, filter_hours FROM filter_info WHERE filter_id=? AND equipment_id=? LIMIT 1')) {
    $stmt->bind_param('ii', $filter_id, $equipment_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $updated = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}

filter_json_exit([
    'success' => true,
    'row' => $updated,
    'report_id' => isset($report_id) ? $report_id : null
]);

?>
