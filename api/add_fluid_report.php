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

function fluid_json_exit($arr, $status = 200) {
    http_response_code($status);
    $buf = ob_get_clean();
    if ($buf && is_string($buf) && trim($buf) !== '') {
        $arr['raw'] = $buf;
    }
    echo json_encode($arr);
    exit();
}

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    fluid_json_exit(['success' => false, 'message' => 'Unauthorized'], 401);
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

$GLOBALS['role'] = $role;
require_edit_api('equipments');

if (!can_access($role, 'equipments')) {
    fluid_json_exit(['success' => false, 'message' => 'Forbidden'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fluid_json_exit(['success' => false, 'message' => 'Method not allowed'], 405);
}

function ensure_fluid_reports_table(mysqli $conn) {
    static $done = false;
    if ($done) return;
    $sql = "CREATE TABLE IF NOT EXISTS fluid_reports (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        equipment_id INT UNSIGNED NOT NULL,
        oil_part_id INT UNSIGNED NOT NULL,
        part VARCHAR(255) NOT NULL,
        fluid_type VARCHAR(255) NOT NULL,
        change_date DATETIME NOT NULL,
        equipment_hours DECIMAL(10,2) NOT NULL,
        changed_by VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_equipment (equipment_id),
        INDEX idx_part (oil_part_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    try {
        $conn->query($sql);
    } catch (Throwable $e) {
        error_log('[fluid_reports] create table failed: ' . $e->getMessage());
    }
    $done = true;
}

$equipment_id = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
$part_id      = isset($_POST['part_id']) ? (int)$_POST['part_id'] : 0;
$part_label   = isset($_POST['part']) ? trim((string)$_POST['part']) : '';
$fluid_type   = isset($_POST['fluid_type']) ? trim((string)$_POST['fluid_type']) : '';
$change_date  = isset($_POST['change_date']) ? trim((string)$_POST['change_date']) : '';
$hours_raw    = isset($_POST['equipment_hours']) ? (string)$_POST['equipment_hours'] : '';
$changed_by   = isset($_POST['changed_by']) ? trim((string)$_POST['changed_by']) : '';

if ($equipment_id <= 0 || $part_id <= 0) {
    fluid_json_exit(['success' => false, 'message' => 'Invalid equipment or part id'], 400);
}
if ($part_label === '' || $fluid_type === '' || $change_date === '') {
    fluid_json_exit(['success' => false, 'message' => 'Part, fluid type, and change date are required'], 400);
}

$hours = is_numeric($hours_raw) ? (float)$hours_raw : 0.0;
if ($hours < 0) $hours = 0.0;

$ts = strtotime($change_date);
if ($ts === false) {
    fluid_json_exit(['success' => false, 'message' => 'Invalid change date'], 400);
}
$change_dt = date('Y-m-d H:i:s', $ts);

// Verify the part belongs to this equipment
$partRow = null;
if ($stmt = $conn->prepare('SELECT * FROM equipment_oil_parts WHERE id=? AND equipment_id=? LIMIT 1')) {
    $stmt->bind_param('ii', $part_id, $equipment_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $partRow = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}
if (!$partRow) {
    fluid_json_exit(['success' => false, 'message' => 'Part not found for this equipment'], 404);
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

$oil_hours = $equip_hours - $hours;
if ($oil_hours < 0) $oil_hours = 0.0;

ensure_fluid_reports_table($conn);

try {
    if (method_exists($conn, 'begin_transaction')) {
        $conn->begin_transaction();
    } else {
        $conn->query('START TRANSACTION');
    }

    // Insert into fluid_reports
    if (!($stmt = $conn->prepare('INSERT INTO fluid_reports (equipment_id, oil_part_id, part, fluid_type, change_date, equipment_hours, changed_by) VALUES (?,?,?,?,?,?,?)'))) {
        throw new Exception('Prepare insert fluid_reports failed: ' . $conn->error);
    }
    $stmt->bind_param('iisssds', $equipment_id, $part_id, $part_label, $fluid_type, $change_dt, $hours, $changed_by);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        // Log bound values for diagnostics
        error_log('[add_fluid_report] Insert failed. Bind values: equipment_id=' . var_export($equipment_id, true)
            . ', part_id=' . var_export($part_id, true)
            . ', part_label=' . var_export($part_label, true)
            . ', fluid_type=' . var_export($fluid_type, true)
            . ', change_dt=' . var_export($change_dt, true)
            . ', equipment_hours=' . var_export($hours, true)
            . ', changed_by=' . var_export($changed_by, true)
            . ' -- stmt error: ' . $err);
        $stmt->close();
        throw new Exception('Execute insert fluid_reports failed: ' . $err);
    }
    $report_id = $stmt->insert_id;
    $stmt->close();

    // Update equipment_oil_parts baseline and derived hours
    if (!($stmt2 = $conn->prepare('UPDATE equipment_oil_parts SET reset_at=?, current_hours=?, oil_hours=?, updated_at=? WHERE id=? AND equipment_id=?'))) {
        throw new Exception('Prepare update equipment_oil_parts failed: ' . $conn->error);
    }
    $now = date('Y-m-d H:i:s');
    $stmt2->bind_param('sddsii', $change_dt, $hours, $oil_hours, $now, $part_id, $equipment_id);
    if (!$stmt2->execute()) {
        $err = $stmt2->error;
        $stmt2->close();
        throw new Exception('Execute update equipment_oil_parts failed: ' . $err);
    }
    $stmt2->close();

    if (method_exists($conn, 'commit')) {
        $conn->commit();
    } else {
        $conn->query('COMMIT');
    }
} catch (Throwable $e) {
    error_log('[add_fluid_report] ' . $e->getMessage());
    if (method_exists($conn, 'rollback')) {
        $conn->rollback();
    } else {
        $conn->query('ROLLBACK');
    }
    fluid_json_exit(['success' => false, 'message' => 'Database error while saving fluid change'], 500);
}

// Fetch updated part row to send back to client
$updated = null;
if ($stmt = $conn->prepare('SELECT * FROM equipment_oil_parts WHERE id=? LIMIT 1')) {
    $stmt->bind_param('i', $part_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $updated = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}

fluid_json_exit([
    'success' => true,
    'row' => $updated,
    'report_id' => isset($report_id) ? $report_id : null
]);

?>
