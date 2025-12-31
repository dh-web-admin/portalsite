<?php
header('Content-Type: application/json; charset=utf-8');
// Keep errors logged but don't output HTML to client
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
// capture unexpected output and return as JSON 'raw' field
ob_start();

// Convert warnings/notices to exceptions so we can return JSON
set_error_handler(function($severity, $message, $file, $line) {
    // Respect error_reporting level
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Shutdown handler to catch fatal errors and return JSON instead of blank 500
register_shutdown_function(function(){
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // clear any buffering
        if (ob_get_length()) ob_end_clean();
        http_response_code(500);
        $payload = ['success' => false, 'message' => 'Internal server error', 'fatal' => $err['message'], 'file' => $err['file'], 'line' => $err['line']];
        // Log full error for server operators
        error_log("[add_equipment_oil_part] FATAL: " . $err['message'] . " in " . $err['file'] . ":" . $err['line']);
        echo json_encode($payload);
    }
});
require_once __DIR__ . '/../session_init.php';
require_once __DIR__ . '/../config/config.php';
// mark API context so permissions partial won't emit UI scripts/styles
if (!defined('IS_API')) define('IS_API', true);
require_once __DIR__ . '/../partials/permissions.php';

function json_exit_add($arr, $status = 200){
    http_response_code($status);
    $buf = ob_get_clean();
    if ($buf && is_string($buf) && trim($buf) !== '') $arr['raw'] = $buf;
    echo json_encode($arr);
    exit();
}

if (!isset($_SESSION['email']) || !isset($_SESSION['name'])) {
    json_exit_add(['success' => false, 'message' => 'Unauthorized'], 401);
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
    json_exit_add(['success' => false, 'message' => 'Forbidden'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit_add(['success' => false, 'message' => 'Method not allowed'], 405);
}

$equipment_id = isset($_POST['equipment_id']) ? intval($_POST['equipment_id']) : 0;
$part = isset($_POST['part']) ? trim($_POST['part']) : '';
$approx_capacity = isset($_POST['approx_capacity']) ? trim($_POST['approx_capacity']) : null;
$fluid_type = isset($_POST['fluid_type']) ? trim($_POST['fluid_type']) : null;
$oil_life = isset($_POST['oil_life']) ? trim($_POST['oil_life']) : 0;
$weight = isset($_POST['weight']) ? trim($_POST['weight']) : null;
$mfg = isset($_POST['mfg']) ? trim($_POST['mfg']) : null;
$supplier = isset($_POST['supplier']) ? trim($_POST['supplier']) : null;
$unit_cost = isset($_POST['unit_cost']) ? trim($_POST['unit_cost']) : null;
$unit = isset($_POST['unit']) ? trim($_POST['unit']) : null;
$total = isset($_POST['total']) ? trim($_POST['total']) : null;
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;

// Accept any values as-is (no validation) per UI request; allow equipment_id=0 and empty fields

$now = date('Y-m-d H:i:s');
// get current equipment hours to set current_hours for this part
$equipment_hours = 0;
if ($equipment_id && $qr = $conn->query('SELECT COALESCE(current_hours,0) AS ch FROM equipments WHERE equipment_id=' . intval($equipment_id) . ' LIMIT 1')) {
    $rrow = $qr->fetch_assoc();
    $equipment_hours = isset($rrow['ch']) ? $rrow['ch'] : 0;
    $qr->free();
}

$stmt = $conn->prepare('INSERT INTO equipment_oil_parts (equipment_id, part, approx_capacity, fluid_type, weight, mfg, supplier, unit_cost, unit, total, notes, current_hours, oil_life, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
if (!$stmt) {
    json_exit_add(['success' => false, 'message' => 'DB prepare failed'], 500);
}
if (!$stmt) {
    json_exit_add(['success' => false, 'message' => 'DB prepare failed'], 500);
}
$stmt->bind_param('issssssssssddss', $equipment_id, $part, $approx_capacity, $fluid_type, $weight, $mfg, $supplier, $unit_cost, $unit, $total, $notes, $equipment_hours, $oil_life, $now, $now);
$ok = $stmt->execute();
$err = null;
if (!$ok) {
    $err = $stmt->error;
    $stmt->close();
    json_exit_add(['success' => false, 'message' => 'Insert failed: ' . $err], 500);
}
$insertId = $stmt->insert_id;
$stmt->close();

$row = [
    'id' => $insertId,
    'equipment_id' => $equipment_id,
    'part' => $part,
    'approx_capacity' => $approx_capacity,
    'fluid_type' => $fluid_type,
    'weight' => $weight,
    'mfg' => $mfg,
    'supplier' => $supplier,
    'unit_cost' => $unit_cost,
    'unit' => $unit,
    'total' => $total,
    'notes' => $notes,
    'current_hours' => $equipment_hours,
    'oil_life' => $oil_life,
    'reset_at' => null,
    'created_at' => $now,
    'updated_at' => $now
];

json_exit_add(['success' => true, 'row' => $row], 200);

// After insertion, recalculate equipment oil_status based on parts
if ($equipment_id) {
    $eq_hours = 0;
    $q = $conn->query('SELECT COALESCE(current_hours,0) AS ch FROM equipments WHERE equipment_id=' . intval($equipment_id) . ' LIMIT 1');
    if ($q) { $r = $q->fetch_assoc(); $eq_hours = isset($r['ch']) ? (float)$r['ch'] : 0; $q->free(); }

    $qr = $conn->query('SELECT current_hours, oil_life FROM equipment_oil_parts WHERE equipment_id=' . intval($equipment_id));
    $status = 'green';
    if ($qr) {
        while ($p = $qr->fetch_assoc()) {
            $p_ch = isset($p['current_hours']) ? (float)$p['current_hours'] : 0;
            $p_life = isset($p['oil_life']) ? (float)$p['oil_life'] : 0;
            $diff = $eq_hours - $p_ch; if ($diff < 0) $diff = 0;
            if ($p_life > 0) {
                $cond = round(100 - (($diff / $p_life) * 100));
                if ($cond <= 0) { $status = 'red'; break; }
                if ($cond < 20 && $status !== 'red') { $status = 'yellow'; }
            }
        }
        $qr->free();
    }
    // update equipments.oil_status
    if ($stmt2 = $conn->prepare('UPDATE equipments SET oil_status=? WHERE equipment_id=?')) {
        $stmt2->bind_param('si', $status, $equipment_id);
        $stmt2->execute();
        $stmt2->close();
    }
}

?>
